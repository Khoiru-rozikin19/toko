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
        '00020101021126610014COM.GO-JEK.WWW01189360091430224274230210G0224274230303UMI51440014ID.CO.QRIS.WWW0215ID10243581829610303UMI5204573253033605802ID5919Rzk store, SNR PNNJ6003OKU61053215962070703A016304881F'
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
    expect($order->status)->toBe('pending_manual');
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
        'harga_modal' => 10000,
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
        'harga_modal' => 11000,
        'duration_days' => 30,
        'stock' => 15,
        'orderkuota_product_code' => 'TSEL10-NEW',
    ]);

    $updateResponse->assertRedirect(route('admin.products'));
    
    $product->refresh();
    expect($product->name)->toBe('Supplier Product Updated');
    expect($product->orderkuota_product_code)->toBe('TSEL10-NEW');
    expect($product->harga_modal)->toBe(11000);
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

    // Verify request was sent directly to Orderkuota API
    Http::assertSent(function ($request) use ($orderId) {
        return str_contains($request->url(), 'h2h.okeconnect.com/trx') &&
               $request->method() === 'GET' &&
               $request['id'] === 'OK1988589' &&
               $request['uid'] === 'OK1988589' &&
               $request['memberid'] === 'OK1988589' &&
               $request['memberID'] === 'OK1988589' &&
               $request['pass'] === '@jkn1234' &&
               $request['password'] === '@jkn1234' &&
               $request['pin_ip'] === '@jkn1234' &&
               $request['key'] === '@jkn1234' &&
               $request['perintah'] === "OK1988589..ML86.081234567890.R#{$orderId}" &&
               $request['pesan'] === "OK1988589..ML86.081234567890.R#{$orderId}" &&
               $request['q'] === "OK1988589..ML86.081234567890.R#{$orderId}" &&
               $request['sms'] === "OK1988589..ML86.081234567890.R#{$orderId}" &&
               $request['mod'] === "ML86.081234567890..R#{$orderId}" &&
               $request['trx'] === "ML86.081234567890..R#{$orderId}" &&
               $request['msg'] === "ML86.081234567890..R#{$orderId}" &&
               $request['text'] === "ML86.081234567890..R#{$orderId}" &&
               $request['format'] === "ML86.081234567890..R#{$orderId}" &&
               $request['product'] === "ML86" &&
               $request['produk'] === "ML86" &&
               $request['kodeproduk'] === "ML86" &&
               $request['kode'] === "ML86" &&
               $request['dest'] === "081234567890" &&
               $request['hp'] === "081234567890" &&
               $request['tujuan'] === "081234567890" &&
               $request['target'] === "081234567890" &&
               $request['refID'] === $orderId &&
               $request['refid'] === $orderId &&
               $request['ref_id'] === $orderId &&
               $request['idtrx'] === $orderId &&
               $request['pin'] === "" &&
               $request['qty'] === '1' &&
               $request['quantity'] === '1';
    });
});

test('OrderkuotaService sends H2H request using Http facade', function () {
    Http::fake([
        'h2h.okeconnect.com/*' => Http::response('SUCCESS', 200),
    ]);

    Setting::set('orderkuota_member_id', 'OK999999');
    Setting::set('orderkuota_pin', '4321');
    Setting::set('orderkuota_api_key', 'test-api-key');

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
        return str_contains($request->url(), 'h2h.okeconnect.com/trx') &&
               $request->method() === 'GET' &&
               $request['id'] === 'OK999999' &&
               $request['uid'] === 'OK999999' &&
               $request['memberid'] === 'OK999999' &&
               $request['memberID'] === 'OK999999' &&
               $request['pass'] === 'test-api-key' &&
               $request['password'] === 'test-api-key' &&
               $request['pin_ip'] === 'test-api-key' &&
               $request['key'] === 'test-api-key' &&
               $request['perintah'] === 'OK999999.4321.FF50.08777777777.R#ORD-TESTHTTP' &&
               $request['pesan'] === 'OK999999.4321.FF50.08777777777.R#ORD-TESTHTTP' &&
               $request['q'] === 'OK999999.4321.FF50.08777777777.R#ORD-TESTHTTP' &&
               $request['sms'] === 'OK999999.4321.FF50.08777777777.R#ORD-TESTHTTP' &&
               $request['mod'] === 'FF50.08777777777.4321.R#ORD-TESTHTTP' &&
               $request['trx'] === 'FF50.08777777777.4321.R#ORD-TESTHTTP' &&
               $request['msg'] === 'FF50.08777777777.4321.R#ORD-TESTHTTP' &&
               $request['text'] === 'FF50.08777777777.4321.R#ORD-TESTHTTP' &&
               $request['format'] === 'FF50.08777777777.4321.R#ORD-TESTHTTP' &&
               $request['product'] === "FF50" &&
               $request['produk'] === "FF50" &&
               $request['kodeproduk'] === "FF50" &&
               $request['kode'] === "FF50" &&
               $request['dest'] === "08777777777" &&
               $request['hp'] === "08777777777" &&
               $request['tujuan'] === "08777777777" &&
               $request['target'] === "08777777777" &&
               $request['refID'] === 'ORD-TESTHTTP' &&
               $request['refid'] === 'ORD-TESTHTTP' &&
               $request['ref_id'] === 'ORD-TESTHTTP' &&
               $request['idtrx'] === 'ORD-TESTHTTP' &&
               $request['pin'] === '4321' &&
               $request['qty'] === '1' &&
               $request['quantity'] === '1';
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
