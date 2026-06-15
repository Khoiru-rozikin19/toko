<?php

use App\Models\Product;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use App\Services\QrisService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

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

test('admin can manage supplier settings', function () {
    $admin = User::create([
        'name' => 'Admin User',
        'email' => 'admin@example.com',
        'password' => Hash::make('password'),
        'role' => 'admin',
        'is_verified' => true,
    ]);

    // Access supplier settings page
    $response = $this->actingAs($admin)->get(route('admin.supplier_settings'));
    $response->assertStatus(200)
             ->assertSee('Pengaturan API Supplier');

    // Update settings
    $updateResponse = $this->actingAs($admin)->post(route('admin.supplier_settings.update'), [
        'orderkuota_member_id' => 'MEMBER123',
        'orderkuota_api_key' => 'token-key-abc',
        'orderkuota_mode' => 'production',
    ]);

    $updateResponse->assertRedirect(route('admin.supplier_settings'));
    
    expect(Setting::get('orderkuota_member_id'))->toBe('MEMBER123');
    expect(Setting::get('orderkuota_api_key'))->toBe('token-key-abc');
    expect(Setting::get('orderkuota_mode'))->toBe('production');
});

test('admin can add and update product with supplier code', function () {
    $admin = User::create([
        'name' => 'Admin User',
        'email' => 'admin@example.com',
        'password' => Hash::make('password'),
        'role' => 'admin',
        'is_verified' => true,
    ]);

    // Store product with code
    $storeResponse = $this->actingAs($admin)->post(route('admin.products.store'), [
        'name' => 'Supplier Product',
        'price' => 12000,
        'duration_days' => 30,
        'stock' => 10,
        'orderkuota_product_code' => 'TSEL10',
    ]);

    $storeResponse->assertRedirect(route('admin.products'));
    
    $product = Product::where('name', 'Supplier Product')->first();
    expect($product)->not->toBeNull();
    expect($product->orderkuota_product_code)->toBe('TSEL10');

    // Update product code
    $updateResponse = $this->actingAs($admin)->post(route('admin.products.update', $product->id), [
        'name' => 'Supplier Product Updated',
        'price' => 13000,
        'duration_days' => 30,
        'stock' => 15,
        'orderkuota_product_code' => 'TSEL10-NEW',
    ]);

    $updateResponse->assertRedirect(route('admin.products'));
    
    $product->refresh();
    expect($product->name)->toBe('Supplier Product Updated');
    expect($product->orderkuota_product_code)->toBe('TSEL10-NEW');
});

test('successful payment callback triggers Orderkuota H2H API request', function () {
    Http::fake([
        'h2h.okeconnect.com/*' => Http::response('SUCCESS', 200),
    ]);
    Log::spy();

    $product = Product::create([
        'name' => 'Premium ML',
        'price' => 15000,
        'duration_days' => 30,
        'stock' => 10,
        'orderkuota_product_code' => 'ML86',
    ]);

    $orderId = 'ORD-SUPPLIER123';
    $totalAmount = 15042;
    
    $order = Order::create([
        'id' => $orderId,
        'product_id' => $product->id,
        'email_or_whatsapp' => '081234567890',
        'target_phone' => '081234567890',
        'base_amount' => 15000,
        'unique_code' => 42,
        'total_amount' => $totalAmount,
        'status' => 'pending',
        'qris_payload' => 'mock-qris-payload',
        'vpn_config' => 'config-text',
        'expired_at' => Carbon::now()->addMinutes(15),
    ]);

    // Send successful callback
    $response = $this->postJson(route('api.payment.callback'), [
        'raw_text' => 'GOPAY TRANSFER RECEIVED Rp 15.042 FROM BOB',
        'amount' => 15042,
        'secret_key' => 'rahasiahappy123',
    ]);

    $response->assertStatus(200);

    // Verify H2H request was sent directly to Okeconnect
    Http::assertSent(function ($request) use ($orderId) {
        return str_starts_with($request->url(), 'https://h2h.okeconnect.com/trx?') &&
               $request->method() === 'GET' &&
               str_contains($request->url(), 'OK1988589') &&
               str_contains($request->url(), 'ML86.081234567890.R');
    });
});

test('OrderkuotaService sends H2H request using Http facade', function () {
    Http::fake([
        'h2h.okeconnect.com/*' => Http::response('SUCCESS', 200),
    ]);

    Setting::set('orderkuota_member_id', 'OK999999');
    Setting::set('orderkuota_pin', '4321');

    $product = Product::create([
        'name' => 'Premium Free Fire',
        'price' => 20000,
        'duration_days' => 30,
        'stock' => 10,
        'orderkuota_product_code' => 'FF50',
    ]);

    $order = Order::create([
        'id' => 'ORD-TESTHTTP',
        'product_id' => $product->id,
        'email_or_whatsapp' => '08777777777',
        'base_amount' => 20000,
        'unique_code' => 5,
        'total_amount' => 20005,
        'status' => 'pending',
        'expired_at' => Carbon::now()->addMinutes(15),
    ]);

    $service = new \App\Services\OrderkuotaService();
    $service->kirimPesananKeOrderkuota($order->id);

    Http::assertSent(function ($request) {
        return str_starts_with($request->url(), 'https://h2h.okeconnect.com/trx?') &&
               $request->method() === 'GET' &&
               str_contains($request->url(), 'OK999999.4321.FF50.08777777777.R');
    });
});

test('okeconnect callback route updates status and stores sn on success', function () {
    $product = Product::create([
        'name' => 'Premium MLBB',
        'price' => 10000,
        'duration_days' => 30,
        'stock' => 10,
        'orderkuota_product_code' => 'ML50',
    ]);

    $order = Order::create([
        'id' => 'ORD-CALLBACK123',
        'product_id' => $product->id,
        'email_or_whatsapp' => '0888888888',
        'base_amount' => 10000,
        'unique_code' => 12,
        'total_amount' => 10012,
        'status' => 'pending',
        'expired_at' => Carbon::now()->addMinutes(15),
    ]);

    $response = $this->postJson(route('api.callback.okeconnect'), [
        'ref_id' => 'ORD-CALLBACK123',
        'status' => 'SUCCESS',
        'sn' => 'SN-OKE-999222',
    ]);

    $response->assertStatus(200)
             ->assertJsonPath('success', true);

    $order->refresh();
    expect($order->status)->toBe('success');
    expect($order->sn)->toBe('SN-OKE-999222');
});

test('okeconnect callback route updates status to failed on failure report', function () {
    $product = Product::create([
        'name' => 'Premium MLBB',
        'price' => 10000,
        'duration_days' => 30,
        'stock' => 10,
        'orderkuota_product_code' => 'ML50',
    ]);

    $order = Order::create([
        'id' => 'ORD-CALLBACK456',
        'product_id' => $product->id,
        'email_or_whatsapp' => '0888888888',
        'base_amount' => 10000,
        'unique_code' => 12,
        'total_amount' => 10012,
        'status' => 'pending',
        'expired_at' => Carbon::now()->addMinutes(15),
    ]);

    $response = $this->postJson(route('api.callback.okeconnect'), [
        'refid' => 'ORD-CALLBACK456',
        'status' => 'FAILED',
    ]);

    $response->assertStatus(200)
             ->assertJsonPath('success', true);

    $order->refresh();
    expect($order->status)->toBe('failed');
});

test('checkout requires target_phone for supplier products with correct format', function () {
    $user = User::create([
        'name' => 'Test Buyer',
        'email' => 'buyer2@example.com',
        'password' => Hash::make('password'),
        'role' => 'buyer',
        'is_verified' => true,
    ]);

    $product = Product::create([
        'name' => 'Supplier Pulsa',
        'price' => 10000,
        'duration_days' => 30,
        'stock' => 10,
        'orderkuota_product_code' => 'P10',
    ]);

    // Checkout without target_phone should fail
    $response1 = $this->actingAs($user)->post(route('buy'), [
        'product_id' => $product->id,
        'email_or_whatsapp' => '08123456789',
    ]);
    $response1->assertStatus(302);
    $response1->assertSessionHasErrors('target_phone');

    // Checkout with non-numeric target_phone should fail
    $response2 = $this->actingAs($user)->post(route('buy'), [
        'product_id' => $product->id,
        'email_or_whatsapp' => '08123456789',
        'target_phone' => '0812abc3456',
    ]);
    $response2->assertStatus(302);
    $response2->assertSessionHasErrors('target_phone');

    // Checkout with too short target_phone should fail
    $response3 = $this->actingAs($user)->post(route('buy'), [
        'product_id' => $product->id,
        'email_or_whatsapp' => '08123456789',
        'target_phone' => '0812',
    ]);
    $response3->assertStatus(302);
    $response3->assertSessionHasErrors('target_phone');

    // Checkout with correct target_phone should succeed
    $response4 = $this->actingAs($user)->postJson(route('buy'), [
        'product_id' => $product->id,
        'email_or_whatsapp' => 'buyer@example.com',
        'target_phone' => '081234567890',
    ]);
    $response4->assertStatus(200);

    $order = Order::where('product_id', $product->id)->first();
    expect($order->target_phone)->toBe('081234567890');
});
