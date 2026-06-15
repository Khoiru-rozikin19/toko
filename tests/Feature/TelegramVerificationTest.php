<?php

use App\Models\Product;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Clear and setup configuration
    Setting::truncate();
    Product::truncate();
    Order::truncate();

    Setting::set('qris_static_string', '00020101021126610014COM.GO-JEK...');
    
    // Set Telegram Bot credentials in environment simulation
    config(['services.telegram.bot_token' => '123456:ABC-DEF']);
    putenv('TELEGRAM_BOT_TOKEN=123456:ABC-DEF');
    putenv('TELEGRAM_ADMIN_ID=987654321');
});

test('checkout route transitions order status to pending_manual and dispatches telegram admin notification', function () {
    Http::fake([
        'api.telegram.org/*' => Http::response(['ok' => true], 200),
    ]);

    $user = User::create([
        'name' => 'Buyer Account',
        'email' => 'buyer@test.com',
        'password' => Hash::make('password'),
        'role' => 'buyer',
        'is_verified' => true,
    ]);

    $product = Product::create([
        'name' => 'Premium Server SG',
        'price' => 15000,
        'duration_days' => 30,
        'config_template' => 'vpn-config-data',
        'stock' => 5,
    ]);

    $response = $this->actingAs($user)->postJson(route('buy'), [
        'product_id' => $product->id,
        'email_or_whatsapp' => 'customer@test.com',
    ]);

    $response->assertStatus(200);

    // Verify order was created with status 'pending_manual'
    $order = Order::first();
    expect($order)->not->toBeNull();
    expect($order->status)->toBe('pending_manual');

    // Verify Telegram notification was dispatched
    Http::assertSent(function ($request) use ($order) {
        $data = $request->data();
        return str_contains($request->url(), 'api.telegram.org/bot123456:ABC-DEF/sendMessage') &&
               $data['chat_id'] === '987654321' &&
               str_contains($data['text'], $order->id) &&
               str_contains($data['text'], '15.0') &&
               isset($data['reply_markup']['inline_keyboard']);
    });
});

test('telegram webhook rejects callbacks from unauthorized chat IDs', function () {
    Http::fake([
        'api.telegram.org/*' => Http::response(['ok' => true], 200),
    ]);

    $product = Product::create([
        'name' => 'Premium Service',
        'price' => 10000,
        'duration_days' => 30,
        'stock' => 10,
    ]);

    $order = Order::create([
        'id' => 'ORD-SECURETEST',
        'product_id' => $product->id,
        'email_or_whatsapp' => 'buyer@test.com',
        'base_amount' => 10000,
        'unique_code' => 5,
        'total_amount' => 10005,
        'status' => 'pending_manual',
        'expired_at' => Carbon::now()->addMinutes(15),
    ]);

    // Send payload from an unauthorized chat ID (e.g. 111222333 instead of admin 987654321)
    $response = $this->postJson(route('webhook.telegram'), [
        'callback_query' => [
            'id' => '111',
            'from' => [
                'id' => 111222333,
                'first_name' => 'Hacker',
            ],
            'data' => 'approve:ORD-SECURETEST',
            'message' => [
                'message_id' => 45,
                'chat' => [
                    'id' => 111222333,
                ],
            ],
        ],
    ]);

    $response->assertStatus(403);
    $response->assertJsonPath('success', false);

    // Verify order remains pending_manual
    $order->refresh();
    expect($order->status)->toBe('pending_manual');
});

test('telegram webhook processes approval callback from admin and triggers H2H transaction', function () {
    Http::fake([
        'api.telegram.org/*' => Http::response(['ok' => true], 200),
        'h2h.okeconnect.com/*' => Http::response('SUCCESS', 200),
    ]);

    $product = Product::create([
        'name' => 'Premium Supplier Pulsa',
        'price' => 12000,
        'duration_days' => 30,
        'stock' => 8,
        'orderkuota_product_code' => 'TSEL10',
    ]);

    $order = Order::create([
        'id' => 'ORD-APP-TEST',
        'product_id' => $product->id,
        'email_or_whatsapp' => '081234567890',
        'target_phone' => '081234567890',
        'base_amount' => 12000,
        'unique_code' => 15,
        'total_amount' => 12015,
        'status' => 'pending_manual',
        'expired_at' => Carbon::now()->addMinutes(15),
    ]);

    // Send webhook approval request from Admin ID
    $response = $this->postJson(route('webhook.telegram'), [
        'callback_query' => [
            'id' => 'cb-approve-123',
            'from' => [
                'id' => 987654321,
                'first_name' => 'Admin User',
            ],
            'data' => 'approve:ORD-APP-TEST',
            'message' => [
                'message_id' => 45,
                'chat' => [
                    'id' => 987654321,
                ],
            ],
        ],
    ]);

    $response->assertStatus(200);
    $response->assertJsonPath('success', true);

    // Verify order transitioned to 'paid' and stock decremented
    $order->refresh();
    expect($order->status)->toBe('paid');

    $product->refresh();
    expect($product->stock)->toBe(7);

    // Verify Telegram callback was answered and message text updated
    Http::assertSent(function ($request) {
        $url = $request->url();
        $data = $request->data();

        if (str_contains($url, 'answerCallbackQuery')) {
            return $data['callback_query_id'] === 'cb-approve-123';
        }

        if (str_contains($url, 'editMessageText')) {
            return $data['chat_id'] === 987654321 &&
                   $data['message_id'] === 45 &&
                   str_contains($data['text'], 'Transaksi Disetujui') &&
                   str_contains($data['text'], 'PAID');
        }

        return true;
    });

    // Verify H2H order dispatch was triggered
    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'h2h.okeconnect.com/trx') &&
               $request['refID'] === 'ORD-APP-TEST';
    });
});

test('telegram webhook processes rejection callback from admin', function () {
    Http::fake([
        'api.telegram.org/*' => Http::response(['ok' => true], 200),
    ]);

    $product = Product::create([
        'name' => 'Premium Service',
        'price' => 10000,
        'duration_days' => 30,
        'stock' => 10,
    ]);

    $order = Order::create([
        'id' => 'ORD-REJ-TEST',
        'product_id' => $product->id,
        'email_or_whatsapp' => 'buyer@test.com',
        'base_amount' => 10000,
        'unique_code' => 5,
        'total_amount' => 10005,
        'status' => 'pending_manual',
        'expired_at' => Carbon::now()->addMinutes(15),
    ]);

    // Send webhook rejection request from Admin ID
    $response = $this->postJson(route('webhook.telegram'), [
        'callback_query' => [
            'id' => 'cb-reject-123',
            'from' => [
                'id' => 987654321,
                'first_name' => 'Admin User',
            ],
            'data' => 'reject:ORD-REJ-TEST',
            'message' => [
                'message_id' => 45,
                'chat' => [
                    'id' => 987654321,
                ],
            ],
        ],
    ]);

    $response->assertStatus(200);
    $response->assertJsonPath('success', true);

    // Verify order transitioned to 'rejected'
    $order->refresh();
    expect($order->status)->toBe('rejected');

    // Verify Telegram callback was answered and message text updated
    Http::assertSent(function ($request) {
        $url = $request->url();
        $data = $request->data();

        if (str_contains($url, 'answerCallbackQuery')) {
            return $data['callback_query_id'] === 'cb-reject-123';
        }

        if (str_contains($url, 'editMessageText')) {
            return $data['chat_id'] === 987654321 &&
                   $data['message_id'] === 45 &&
                   str_contains($data['text'], 'Transaksi Ditolak') &&
                   str_contains($data['text'], 'REJECTED');
        }

        return true;
    });
});
