<?php

use App\Models\Product;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use App\Models\UserBalance;
use App\Models\BalanceTransaction;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use App\Services\OrderkuotaService;

uses(RefreshDatabase::class);

beforeEach(function () {
    Setting::truncate();
    Product::truncate();
    Order::truncate();
    User::truncate();
    UserBalance::truncate();
    BalanceTransaction::truncate();

    Setting::set('qris_static_string', '00020101021126610014COM.GO-JEK...');
    Setting::set('api_secret_key', 'rahasiahappy123');
    Setting::set('orderkuota_api_key', 'rahasiahappy123');
    
    config(['services.telegram.bot_token' => '123456:ABC-DEF']);
    putenv('TELEGRAM_BOT_TOKEN=123456:ABC-DEF');
    putenv('TELEGRAM_ADMIN_ID=987654321');
});

test('buyer can pre-order closed Okeconnect product using balance and it is marked as proses', function () {
    Http::fake([
        'h2h.okeconnect.com/*' => Http::response('PROSES', 200),
        'api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 123456]], 200),
    ]);

    $buyer = User::create([
        'name' => 'Buyer Account',
        'email' => 'buyer@test.com',
        'password' => Hash::make('password'),
        'role' => 'buyer',
        'is_verified' => true,
    ]);

    $buyerBalance = $buyer->getOrCreateBalance();
    $buyerBalance->update(['balance' => 20000]);

    $product = Product::create([
        'name' => 'Okeconnect Closed Product',
        'price' => 15000,
        'stock' => 5,
        'status' => 'close',
        'orderkuota_product_code' => 'OKCLOSE',
    ]);

    $response = $this->actingAs($buyer)->postJson(route('buy'), [
        'product_id' => $product->id,
        'email_or_whatsapp' => 'buyer@test.com',
        'target_phone' => '081234567890',
        'preorder_name' => 'Budi Santoso',
        'payment_method' => 'balance',
    ]);

    $response->assertStatus(200)
             ->assertJsonPath('order.status', 'proses');

    $order = Order::first();
    expect($order)->not->toBeNull();
    expect($order->status)->toBe('proses');
    expect($order->is_preorder)->toBeTrue();
    expect($order->customer_name)->toBe('Budi Santoso');
    expect($order->target_phone)->toBe('081234567890');

    // Balance should be deducted
    expect((int) $buyer->fresh()->getOrCreateBalance()->balance)->toBe(5000);

    // No Okeconnect HTTP requests should have been recorded because it was a pre-order
    Http::assertNotSent(function ($request) {
        return str_contains($request->url(), 'h2h.okeconnect.com/trx');
    });

    // Verify Telegram notification was dispatched
    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'sendMessage') &&
               str_contains($request['text'], 'Pre-Order Baru Diterima') &&
               str_contains($request['text'], 'Budi Santoso');
    });
});

test('buyer must input name when pre-ordering closed Okeconnect product', function () {
    $buyer = User::create([
        'name' => 'Buyer Account',
        'email' => 'buyer@test.com',
        'password' => Hash::make('password'),
        'role' => 'buyer',
        'is_verified' => true,
    ]);

    $buyerBalance = $buyer->getOrCreateBalance();
    $buyerBalance->update(['balance' => 20000]);

    $product = Product::create([
        'name' => 'Okeconnect Closed Product',
        'price' => 15000,
        'stock' => 5,
        'status' => 'close',
        'orderkuota_product_code' => 'OKCLOSE',
    ]);

    // Request without preorder_name should fail validation (422)
    $response = $this->actingAs($buyer)->postJson(route('buy'), [
        'product_id' => $product->id,
        'email_or_whatsapp' => 'buyer@test.com',
        'target_phone' => '081234567890',
        'payment_method' => 'balance',
    ]);

    $response->assertStatus(422);
    expect($response->json('message'))->toContain('preorder name');
});

test('buyer can pre-order closed Okeconnect product using QRIS and it is marked as pending', function () {
    Http::fake([
        'api.telegram.org/*' => Http::response(['ok' => true], 200),
    ]);

    $buyer = User::create([
        'name' => 'Buyer Account',
        'email' => 'buyer@test.com',
        'password' => Hash::make('password'),
        'role' => 'buyer',
        'is_verified' => true,
    ]);

    $product = Product::create([
        'name' => 'Okeconnect Closed Product QRIS',
        'price' => 10000,
        'stock' => 5,
        'status' => 'close',
        'orderkuota_product_code' => 'OKCLOSEQRIS',
    ]);

    $response = $this->actingAs($buyer)->postJson(route('buy'), [
        'product_id' => $product->id,
        'email_or_whatsapp' => 'buyer@test.com',
        'target_phone' => '081234567890',
        'preorder_name' => 'Budi Santoso',
        'payment_method' => 'qris',
    ]);

    $response->assertStatus(200);

    $order = Order::first();
    expect($order)->not->toBeNull();
    expect($order->status)->toBe('pending');
    expect($order->is_preorder)->toBeTrue();
    expect($order->customer_name)->toBe('Budi Santoso');
});

test('auto callback payment transitions pre-order from pending to proses and fires Telegram notification', function () {
    Http::fake([
        'api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 9999]], 200),
    ]);

    $product = Product::create([
        'name' => 'Okeconnect Closed Product',
        'price' => 10000,
        'stock' => 5,
        'status' => 'close',
        'orderkuota_product_code' => 'OKCLOSE',
    ]);

    $order = Order::create([
        'id' => 'ORD-PRE-AUTO',
        'product_id' => $product->id,
        'email_or_whatsapp' => 'buyer@test.com',
        'customer_name' => 'Budi Santoso',
        'target_phone' => '081234567890',
        'base_amount' => 10000,
        'unique_code' => 5,
        'total_amount' => 10005,
        'status' => 'pending',
        'is_preorder' => true,
        'expired_at' => Carbon::now()->addMinutes(30),
    ]);

    $response = $this->postJson(route('api.payment.callback'), [
        'raw_text' => 'GOPAY RECEIVED Rp 10.005',
        'amount' => 10005,
        'secret_key' => 'rahasiahappy123',
    ]);

    $response->assertStatus(200);

    $order->refresh();
    expect($order->status)->toBe('proses');
    expect($order->telegram_message_id)->toBe('9999');

    // Pre-order notification sent with Cancel Pre-Order button
    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'sendMessage') &&
               str_contains($request['text'], 'Pre-Order Baru Diterima') &&
               isset($request['reply_markup']['inline_keyboard'][0][0]['callback_data']) &&
               $request['reply_markup']['inline_keyboard'][0][0]['callback_data'] === 'cancel_preorder:ORD-PRE-AUTO';
    });
});

test('admin can cancel pre-order from Telegram webhook which refunds balance and updates status to ditolak', function () {
    Http::fake([
        'api.telegram.org/*' => Http::response(['ok' => true], 200),
    ]);

    $buyer = User::create([
        'name' => 'Buyer Account',
        'email' => 'buyer@test.com',
        'password' => Hash::make('password'),
        'role' => 'buyer',
        'is_verified' => true,
    ]);
    $buyerBalance = $buyer->getOrCreateBalance();
    $buyerBalance->update(['balance' => 2000]); // initial balance

    $product = Product::create([
        'name' => 'Okeconnect Closed Product',
        'price' => 10000,
        'stock' => 5,
        'status' => 'close',
        'orderkuota_product_code' => 'OKCLOSE',
    ]);

    $order = Order::create([
        'id' => 'ORD-PRE-CANCEL',
        'user_id' => $buyer->id,
        'product_id' => $product->id,
        'email_or_whatsapp' => 'buyer@test.com',
        'customer_name' => 'Budi Santoso',
        'target_phone' => '081234567890',
        'base_amount' => 10000,
        'unique_code' => 5,
        'total_amount' => 10005,
        'status' => 'proses', // paid preorder
        'is_preorder' => true,
        'telegram_message_id' => '123456',
        'expired_at' => Carbon::now()->addMinutes(30),
    ]);

    $response = $this->postJson(route('webhook.telegram'), [
        'callback_query' => [
            'id' => 'cb_987',
            'from' => ['id' => 987654321], // Admin ID
            'data' => 'cancel_preorder:ORD-PRE-CANCEL',
            'message' => [
                'message_id' => 123456,
                'chat' => ['id' => 987654321],
            ],
        ],
    ]);

    $response->assertStatus(200);

    $order->refresh();
    expect($order->status)->toBe('ditolak');
    expect($order->is_preorder)->toBeFalse();

    // User should have their balance refunded
    expect((int) $buyer->fresh()->getOrCreateBalance()->balance)->toBe(12005); // 2000 + 10005

    // Balance transaction log should exist
    $tx = BalanceTransaction::where('reference_id', 'ORD-PRE-CANCEL')->first();
    expect($tx)->not->toBeNull();
    expect($tx->type)->toBe('topup');
    expect((int) $tx->amount)->toBe(10005);

    // Verify Telegram message was edited to reflect cancellation
    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'editMessageText') &&
               str_contains($request['text'], 'Pre-Order Dibatalkan oleh Admin') &&
               str_contains($request['text'], 'DITOLAK');
    });
});

test('syncing Okeconnect product status to open dispatches pending pre-orders, updates status to sukses, and removes Telegram buttons', function () {
    Http::fake([
        'h2h.okeconnect.com/*' => Http::response('PROSES', 200),
        'api.telegram.org/*' => Http::response(['ok' => true], 200),
    ]);

    $product = Product::create([
        'name' => 'Okeconnect Pre-order Product',
        'price' => 10000,
        'stock' => 5,
        'status' => 'close',
        'orderkuota_product_code' => 'OKP123',
    ]);

    $order = Order::create([
        'id' => 'ORD-PRE-SYNC',
        'product_id' => $product->id,
        'email_or_whatsapp' => 'buyer@test.com',
        'customer_name' => 'Budi Santoso',
        'target_phone' => '081234567890',
        'base_amount' => 10000,
        'unique_code' => 0,
        'total_amount' => 10000,
        'status' => 'proses',
        'is_preorder' => true,
        'telegram_message_id' => '123456',
    ]);

    // Manually set product status to open
    $product->update(['status' => 'open']);

    // Trigger processing
    app(OrderkuotaService::class)->processAllOpenPreorders();

    $order->refresh();
    expect($order->status)->toBe('sukses');
    expect($order->is_preorder)->toBeFalse();

    // Verify Telegram message was edited to "Pre-Order Berhasil"
    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'editMessageText') &&
               str_contains($request['text'], 'Pre-Order Berhasil') &&
               !isset($request['reply_markup']);
    });
});
