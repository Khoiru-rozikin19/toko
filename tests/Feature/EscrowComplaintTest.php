<?php

use App\Models\Product;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use App\Models\Complaint;
use App\Models\UserBalance;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    Setting::truncate();
    Product::truncate();
    Order::truncate();
    User::truncate();
    Complaint::truncate();
    UserBalance::truncate();

    Setting::set('qris_static_string', '00020101021126610014COM.GO-JEK...');
    Setting::set('api_secret_key', 'rahasiahappy123');
    
    config(['services.telegram.bot_token' => '123456:ABC-DEF']);
    putenv('TELEGRAM_BOT_TOKEN=123456:ABC-DEF');
    putenv('TELEGRAM_ADMIN_ID=987654321');
});

test('buyer buys seller product via balance and funds enter held_balance, seller receives telegram notification', function () {
    Http::fake([
        'api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => '998877']], 200),
    ]);

    // Create seller
    $seller = User::create([
        'name' => 'Seller Account',
        'email' => 'seller@test.com',
        'password' => Hash::make('password'),
        'role' => 'seller',
        'telegram_chat_id' => '777666555',
        'is_verified' => true,
    ]);

    // Create buyer
    $buyer = User::create([
        'name' => 'Buyer Account',
        'email' => 'buyer@test.com',
        'password' => Hash::make('password'),
        'role' => 'buyer',
        'is_verified' => true,
    ]);
    
    // Add balance to buyer
    $buyerBalance = $buyer->getOrCreateBalance();
    $buyerBalance->update(['balance' => 20000]);

    // Create product owned by seller
    $product = Product::create([
        'user_id' => $seller->id,
        'name' => 'Seller Product',
        'price' => 15000,
        'harga_modal' => 5000, // profit should be 10000
        'duration_days' => 30,
        'stock' => 5,
    ]);

    $response = $this->actingAs($buyer)->postJson(route('buy'), [
        'product_id' => $product->id,
        'email_or_whatsapp' => 'buyer@test.com',
        'payment_method' => 'balance',
    ]);

    $response->assertStatus(200);

    // Verify order was created with correct escrow details
    $order = Order::first();
    expect($order)->not->toBeNull();
    expect($order->status)->toBe('success');
    expect($order->escrow_status)->toBe('held');
    expect((float)$order->escrow_amount)->toBe(10000.0); // 15000 - 5000
    expect($order->telegram_message_id)->toBe('998877');

    // Verify seller's held balance is incremented
    $sellerBalance = $seller->balance;
    expect((float)$sellerBalance->held_balance)->toBe(10000.0);

    // Verify Telegram notification was sent to seller
    Http::assertSent(function ($request) use ($order) {
        $data = $request->data();
        return str_contains($request->url(), 'api.telegram.org/bot123456:ABC-DEF/sendMessage') &&
               $data['chat_id'] === '777666555' &&
               str_contains($data['text'], $order->id) &&
               isset($data['reply_markup']['inline_keyboard']);
    });
});

test('telegram webhook processes seller accept callback and changes status to proses', function () {
    Http::fake([
        'api.telegram.org/*' => Http::response(['ok' => true], 200),
    ]);

    $seller = User::create([
        'name' => 'Seller Account',
        'email' => 'seller@test.com',
        'password' => Hash::make('password'),
        'role' => 'seller',
        'telegram_chat_id' => '777666555',
        'is_verified' => true,
    ]);

    $product = Product::create([
        'user_id' => $seller->id,
        'name' => 'Seller Product',
        'price' => 15000,
        'stock' => 5,
    ]);

    $order = Order::create([
        'id' => 'ORD-SELLER-ACC',
        'product_id' => $product->id,
        'email_or_whatsapp' => 'buyer@test.com',
        'base_amount' => 15000,
        'unique_code' => 0,
        'total_amount' => 15000,
        'status' => 'success',
        'escrow_status' => 'held',
        'escrow_amount' => 15000,
        'payment_method' => 'balance',
    ]);

    // Send webhook seller accept from seller's chat ID
    $response = $this->postJson(route('webhook.telegram'), [
        'callback_query' => [
            'id' => 'cb-seller-acc',
            'from' => [
                'id' => 777666555,
                'first_name' => 'Seller User',
            ],
            'data' => 'seller_accept:ORD-SELLER-ACC',
            'message' => [
                'message_id' => 1122,
                'chat' => [
                    'id' => 777666555,
                ],
            ],
        ],
    ]);

    $response->assertStatus(200);

    // Verify order transitioned to 'proses'
    $order->refresh();
    expect($order->status)->toBe('proses');
    expect($order->escrow_status)->toBe('held'); // escrow remains held until 90 minutes
});

test('telegram webhook processes seller reject callback, decrements held balance and refunds buyer', function () {
    Http::fake([
        'api.telegram.org/*' => Http::response(['ok' => true], 200),
    ]);

    $seller = User::create([
        'name' => 'Seller Account',
        'email' => 'seller@test.com',
        'password' => Hash::make('password'),
        'role' => 'seller',
        'telegram_chat_id' => '777666555',
        'is_verified' => true,
    ]);
    
    $sellerBalance = $seller->getOrCreateBalance();
    $sellerBalance->update(['held_balance' => 15000]);

    $buyer = User::create([
        'name' => 'Buyer Account',
        'email' => 'buyer@test.com',
        'password' => Hash::make('password'),
        'role' => 'buyer',
        'is_verified' => true,
    ]);
    $buyerBalance = $buyer->getOrCreateBalance();
    $buyerBalance->update(['balance' => 5000]); // balance before refund

    $product = Product::create([
        'user_id' => $seller->id,
        'name' => 'Seller Product',
        'price' => 15000,
        'stock' => 5,
    ]);

    $order = Order::create([
        'id' => 'ORD-SELLER-REJ',
        'user_id' => $buyer->id,
        'product_id' => $product->id,
        'email_or_whatsapp' => 'buyer@test.com',
        'base_amount' => 15000,
        'unique_code' => 0,
        'total_amount' => 15000,
        'status' => 'success',
        'escrow_status' => 'held',
        'escrow_amount' => 15000,
        'payment_method' => 'balance',
    ]);

    // Send webhook seller reject from seller's chat ID
    $response = $this->postJson(route('webhook.telegram'), [
        'callback_query' => [
            'id' => 'cb-seller-rej',
            'from' => [
                'id' => 777666555,
                'first_name' => 'Seller User',
            ],
            'data' => 'seller_reject:ORD-SELLER-REJ',
            'message' => [
                'message_id' => 1123,
                'chat' => [
                    'id' => 777666555,
                ],
            ],
        ],
    ]);

    $response->assertStatus(200);

    // Verify order transitioned to 'gagal'
    $order->refresh();
    expect($order->status)->toBe('gagal');
    expect($order->escrow_status)->toBe('none');

    // Verify seller's held balance decremented
    $sellerBalance->refresh();
    expect((float)$sellerBalance->held_balance)->toBe(0.0);

    // Verify buyer got refunded
    $buyerBalance->refresh();
    expect((float)$buyerBalance->balance)->toBe(20000.0); // 5000 + 15000
});

test('auto-release command moves funds to seller active balance after 90 minutes', function () {
    $seller = User::create([
        'name' => 'Seller Account',
        'email' => 'seller@test.com',
        'password' => Hash::make('password'),
        'role' => 'seller',
        'is_verified' => true,
    ]);
    
    $sellerBalance = $seller->getOrCreateBalance();
    $sellerBalance->update(['held_balance' => 10000]);

    $product = Product::create([
        'user_id' => $seller->id,
        'name' => 'Seller Product',
        'price' => 15000,
        'harga_modal' => 5000,
        'stock' => 5,
    ]);

    // Create an order paid 95 minutes ago
    $order = Order::create([
        'id' => 'ORD-ESCROW-RELEASE',
        'product_id' => $product->id,
        'email_or_whatsapp' => 'buyer@test.com',
        'base_amount' => 15000,
        'unique_code' => 0,
        'total_amount' => 15000,
        'status' => 'success',
        'escrow_status' => 'held',
        'escrow_amount' => 10000,
        'paid_at' => Carbon::now()->subMinutes(95),
    ]);

    // Run the artisan command
    $this->artisan('escrow:release')
        ->assertExitCode(0);

    // Verify escrow status changed to released
    $order->refresh();
    expect($order->escrow_status)->toBe('released');
    expect($order->status)->toBe('proses');

    // Verify seller held balance decremented
    $sellerBalance->refresh();
    expect((float)$sellerBalance->held_balance)->toBe(0.0);
});

test('buyer can file a complaint which prevents automatic escrow release', function () {
    $buyer = User::create([
        'name' => 'Buyer Account',
        'email' => 'buyer@test.com',
        'password' => Hash::make('password'),
        'role' => 'buyer',
        'is_verified' => true,
    ]);

    $seller = User::create([
        'name' => 'Seller Account',
        'email' => 'seller@test.com',
        'password' => Hash::make('password'),
        'role' => 'seller',
        'is_verified' => true,
    ]);
    
    $sellerBalance = $seller->getOrCreateBalance();
    $sellerBalance->update(['held_balance' => 10000]);

    $product = Product::create([
        'user_id' => $seller->id,
        'name' => 'Seller Product',
        'price' => 15000,
        'harga_modal' => 5000,
        'stock' => 5,
    ]);

    $order = Order::create([
        'id' => 'ORD-COMPLAINT-TEST',
        'user_id' => $buyer->id,
        'product_id' => $product->id,
        'email_or_whatsapp' => 'buyer@test.com',
        'base_amount' => 15000,
        'unique_code' => 0,
        'total_amount' => 15000,
        'status' => 'success',
        'escrow_status' => 'held',
        'escrow_amount' => 10000,
        'paid_at' => Carbon::now()->subMinutes(95),
    ]);

    // File a complaint
    $response = $this->actingAs($buyer)->post(route('order.complain', $order->id), [
        'reason' => 'Produk tidak bekerja dengan baik.',
    ]);

    $response->assertSessionHasNoErrors();
    $response->assertStatus(302); // redirects back

    // Verify complaint record created and escrow status is disputed
    $complaint = Complaint::first();
    expect($complaint)->not->toBeNull();
    expect($complaint->status)->toBe('pending');
    expect($complaint->reason)->toBe('Produk tidak bekerja dengan baik.');
    
    $order->refresh();
    expect($order->escrow_status)->toBe('disputed');

    // Run release command and verify it DOES NOT release disputed order
    $this->artisan('escrow:release')
        ->assertExitCode(0);

    $order->refresh();
    expect($order->escrow_status)->toBe('disputed'); // still disputed!
    expect((float)$sellerBalance->fresh()->held_balance)->toBe(10000.0); // still held!
});

test('seller resolves a complaint: refunds buyer and sets status to gagal', function () {
    $buyer = User::create([
        'name' => 'Buyer Account',
        'email' => 'buyer@test.com',
        'password' => Hash::make('password'),
        'role' => 'buyer',
        'is_verified' => true,
    ]);
    $buyerBalance = $buyer->getOrCreateBalance();
    $buyerBalance->update(['balance' => 0]);

    $seller = User::create([
        'name' => 'Seller Account',
        'email' => 'seller@test.com',
        'password' => Hash::make('password'),
        'role' => 'seller',
        'is_verified' => true,
    ]);
    
    $sellerBalance = $seller->getOrCreateBalance();
    $sellerBalance->update(['held_balance' => 10000]);

    $product = Product::create([
        'user_id' => $seller->id,
        'name' => 'Seller Product',
        'price' => 15000,
        'harga_modal' => 5000,
        'stock' => 5,
    ]);

    $order = Order::create([
        'id' => 'ORD-RESOLVE-TEST',
        'user_id' => $buyer->id,
        'product_id' => $product->id,
        'email_or_whatsapp' => 'buyer@test.com',
        'base_amount' => 15000,
        'unique_code' => 0,
        'total_amount' => 15000,
        'status' => 'success',
        'escrow_status' => 'disputed',
        'escrow_amount' => 10000,
        'payment_method' => 'balance',
    ]);

    $complaint = Complaint::create([
        'order_id' => $order->id,
        'user_id' => $buyer->id,
        'reason' => 'Masalah teknis',
        'status' => 'pending',
    ]);

    // Seller resolves complaint
    $response = $this->actingAs($seller)->post(route('admin.complaints.resolve', $complaint->id));
    $response->assertStatus(302);

    // Verify complaint resolved and order failed
    $complaint->refresh();
    expect($complaint->status)->toBe('resolved');

    $order->refresh();
    expect($order->status)->toBe('gagal');
    expect($order->escrow_status)->toBe('none');

    // Seller held balance is decremented
    $sellerBalance->refresh();
    expect((float)$sellerBalance->held_balance)->toBe(0.0);

    // Buyer is refunded
    $buyerBalance->refresh();
    expect((float)$buyerBalance->balance)->toBe(15000.0);
});

test('seller rejects a complaint: releases escrow and sets status to proses', function () {
    $buyer = User::create([
        'name' => 'Buyer Account',
        'email' => 'buyer@test.com',
        'password' => Hash::make('password'),
        'role' => 'buyer',
        'is_verified' => true,
    ]);

    $seller = User::create([
        'name' => 'Seller Account',
        'email' => 'seller@test.com',
        'password' => Hash::make('password'),
        'role' => 'seller',
        'is_verified' => true,
    ]);
    
    $sellerBalance = $seller->getOrCreateBalance();
    $sellerBalance->update(['held_balance' => 10000]);

    $product = Product::create([
        'user_id' => $seller->id,
        'name' => 'Seller Product',
        'price' => 15000,
        'harga_modal' => 5000,
        'stock' => 5,
    ]);

    $order = Order::create([
        'id' => 'ORD-REJECT-TEST',
        'user_id' => $buyer->id,
        'product_id' => $product->id,
        'email_or_whatsapp' => 'buyer@test.com',
        'base_amount' => 15000,
        'unique_code' => 0,
        'total_amount' => 15000,
        'status' => 'success',
        'escrow_status' => 'disputed',
        'escrow_amount' => 10000,
        'payment_method' => 'balance',
    ]);

    $complaint = Complaint::create([
        'order_id' => $order->id,
        'user_id' => $buyer->id,
        'reason' => 'Komplain palsu',
        'status' => 'pending',
    ]);

    // Seller rejects complaint
    $response = $this->actingAs($seller)->post(route('admin.complaints.reject', $complaint->id));
    $response->assertStatus(302);

    // Verify complaint rejected and order released
    $complaint->refresh();
    expect($complaint->status)->toBe('rejected');

    $order->refresh();
    expect($order->status)->toBe('proses');
    expect($order->escrow_status)->toBe('released');

    // Seller held balance is decremented (and moved to dynamic walletBalance)
    $sellerBalance->refresh();
    expect((float)$sellerBalance->held_balance)->toBe(0.0);
});
