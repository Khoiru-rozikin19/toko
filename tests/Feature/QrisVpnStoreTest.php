<?php

use App\Models\Product;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use App\Services\QrisService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Clear and set default settings
    Setting::truncate();
    Product::truncate();
    Order::truncate();

    Setting::set(
        'qris_static_string',
        '00020101021126570011ID.CO.GOPAY.WWW011893600912345678901202151234567890123450303UME51440014ID.CO.QRIS.WWW02151234567890123450303UME5204599953033605802ID5915GoPay Merchant6009TANGERANG61051511162070703A016304'
    );
    Setting::set('api_secret_key', 'rahasiahappy123');
});

test('qris service helper correctly parses and updates amount', function () {
    $staticQris = Setting::get('qris_static_string');
    $amount = 25042;

    $dynamicQris = QrisService::generateDynamicQris($staticQris, $amount);
    
    expect($dynamicQris)->not->toBeEmpty();
    expect($dynamicQris)->toContain('540525042'); // Tag 54 length 05 value 25042
    expect($dynamicQris)->toContain('5303360');   // Tag 53 length 03 value 360
    
    // Check CRC16 (ends with 6304 + 4 hex digits)
    $crcStart = substr($dynamicQris, -8, 4);
    expect($crcStart)->toBe('6304');
    
    $crcValue = substr($dynamicQris, -4);
    expect(strlen($crcValue))->toBe(4);
});

test('user checkout buy route registers order with unique code and returns json', function () {
    $user = User::create([
        'name' => 'Test Buyer',
        'email' => 'buyer@example.com',
        'password' => Hash::make('password'),
        'role' => 'buyer',
        'is_verified' => true,
    ]);

    $product = Product::create([
        'name' => 'Test Packet',
        'price' => 5000,
        'duration_days' => 30,
        'config_template' => 'vpn-config-data',
        'stock' => 5,
    ]);

    $response = $this->actingAs($user)->postJson(route('buy'), [
        'product_id' => $product->id,
        'email_or_whatsapp' => 'tester@whatsapp.com',
    ]);

    $response->assertStatus(200)
             ->assertJsonPath('success', true)
             ->assertJsonStructure([
                 'success',
                 'order' => [
                     'id',
                     'product_name',
                     'email_or_whatsapp',
                     'total_amount',
                     'qris_payload',
                     'expired_at',
                 ]
             ]);

    $order = Order::first();
    expect($order)->not->toBeNull();
    expect($order->email_or_whatsapp)->toBe('tester@whatsapp.com');
    expect($order->base_amount)->toBe(5000);
    expect($order->unique_code)->toBeGreaterThanOrEqual(1);
    expect($order->unique_code)->toBeLessThanOrEqual(99);
    expect($order->total_amount)->toBe($order->base_amount + $order->unique_code);
    expect($order->status)->toBe('pending');
});

test('api callback notification processes successful payments and updates order and stock', function () {
    $product = Product::create([
        'name' => 'Premium SG',
        'price' => 20000,
        'duration_days' => 30,
        'config_template' => 'vpn-config-data',
        'stock' => 10,
    ]);

    // Create a pending order
    $orderId = 'ORD-TEST1234';
    $uniqueCode = 42;
    $totalAmount = 20042;
    
    $order = Order::create([
        'id' => $orderId,
        'product_id' => $product->id,
        'email_or_whatsapp' => 'buyer@example.com',
        'base_amount' => 20000,
        'unique_code' => $uniqueCode,
        'total_amount' => $totalAmount,
        'status' => 'pending',
        'qris_payload' => 'mock-qris-payload',
        'vpn_config' => 'config-text',
        'expired_at' => Carbon::now()->addMinutes(15),
    ]);

    // Send successful callback
    $response = $this->postJson(route('api.payment.callback'), [
        'raw_text' => 'GOPAY TRANSFER RECEIVED Rp 20.042 FROM ANNE',
        'amount' => 20042,
        'secret_key' => 'rahasiahappy123',
    ]);

    $response->assertStatus(200)
             ->assertJson([
                 'success' => true,
                 'order_id' => $orderId,
             ]);

    // Check order status updated to success
    $order->refresh();
    expect($order->status)->toBe('success');

    // Check stock decremented
    $product->refresh();
    expect($product->stock)->toBe(9);

    // Check log is created and linked
    $log = \App\Models\PaymentLog::first();
    expect($log)->not->toBeNull();
    expect($log->amount)->toBe(20042);
    expect($log->matched_order_id)->toBe($orderId);
});

test('api callback notification returns unauthorized for invalid api keys', function () {
    $response = $this->postJson(route('api.payment.callback'), [
        'raw_text' => 'Some notify text',
        'amount' => 50030,
        'secret_key' => 'wrong-secret-key',
    ]);

    $response->assertStatus(401)
             ->assertJson([
                 'success' => false,
             ]);
});
