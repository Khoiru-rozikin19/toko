<?php

use App\Models\Product;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use App\Models\AccountStock;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    Setting::truncate();
    Product::truncate();
    Order::truncate();
    AccountStock::truncate();

    Setting::set('api_secret_key', 'rahasiahappy123');
    config(['services.telegram.bot_token' => '123456:ABC-DEF']);
    putenv('TELEGRAM_BOT_TOKEN=123456:ABC-DEF');
    putenv('TELEGRAM_ADMIN_ID=987654321');
});

test('product stock accessor is dynamic for local products and static for supplier products', function () {
    // 1. Local product with no configurations (should default to database stock column or 0 if exists)
    $localProduct = Product::create([
        'name' => 'Local VPN',
        'price' => 10000,
        'stock' => 5, // static stock column
    ]);

    // Since no AccountStock records exist, it falls back to the database stock column (5)
    expect($localProduct->stock)->toBe(5);

    // Create an AccountStock record for the local product. This transitions it to use dynamic stock tracking.
    $stock1 = AccountStock::create([
        'product_id' => $localProduct->id,
        'account_data' => 'vmess://config1',
        'status' => 'ready',
    ]);

    $localProduct->refresh();
    expect($localProduct->stock)->toBe(1);

    // Create a second AccountStock record
    $stock2 = AccountStock::create([
        'product_id' => $localProduct->id,
        'account_data' => 'vmess://config2',
        'status' => 'ready',
    ]);

    $localProduct->refresh();
    expect($localProduct->stock)->toBe(2);

    // Mark one as sold
    $stock1->update(['status' => 'sold']);

    $localProduct->refresh();
    expect($localProduct->stock)->toBe(1);

    // 2. Supplier product (always uses static stock column)
    $supplierProduct = Product::create([
        'name' => 'Supplier Pulsa',
        'price' => 12000,
        'stock' => 10,
        'orderkuota_product_code' => 'TSEL10',
    ]);

    // Create AccountStock records for supplier product (just to make sure they are ignored)
    AccountStock::create([
        'product_id' => $supplierProduct->id,
        'account_data' => 'vmess://supplier_config',
        'status' => 'ready',
    ]);

    $supplierProduct->refresh();
    expect($supplierProduct->stock)->toBe(10);
});

test('admin can upload accounts in bulk separated by double newlines', function () {
    $admin = User::create([
        'name' => 'Admin User',
        'email' => 'admin@example.com',
        'password' => Hash::make('password'),
        'role' => 'admin',
        'is_verified' => true,
    ]);

    $product = Product::create([
        'name' => 'Bulk VPN Pack',
        'price' => 5000,
        'stock' => 0,
    ]);

    // Insert first AccountStock to enable dynamic stock tracking
    AccountStock::create([
        'product_id' => $product->id,
        'account_data' => 'vmess://initial',
        'status' => 'ready',
    ]);

    $response = $this->actingAs($admin)->post(route('admin.account_stocks.store'), [
        'product_id' => $product->id,
        'accounts_input' => "vmess://first_config\n\nvmess://second_config\r\n\r\nvmess://third_config",
    ]);

    $response->assertRedirect();
    
    // We expect 1 (initial) + 3 (newly uploaded) = 4 configs in total
    $product->refresh();
    expect($product->stock)->toBe(4);

    $readyConfigs = AccountStock::where('product_id', $product->id)
        ->where('status', 'ready')
        ->pluck('account_data')
        ->toArray();

    expect($readyConfigs)->toContain('vmess://first_config');
    expect($readyConfigs)->toContain('vmess://second_config');
    expect($readyConfigs)->toContain('vmess://third_config');
});

test('telegram webhook approval consumes a ready configuration and maps it to the order', function () {
    Http::fake([
        'api.telegram.org/*' => Http::response(['ok' => true], 200),
    ]);

    $product = Product::create([
        'name' => 'Telegram VPN',
        'price' => 15000,
        'stock' => 0,
    ]);

    // Add stock configs (oldest first)
    $config1 = AccountStock::create([
        'product_id' => $product->id,
        'account_data' => 'vmess://oldest_ready',
        'status' => 'ready',
    ]);

    $config2 = AccountStock::create([
        'product_id' => $product->id,
        'account_data' => 'vmess://newest_ready',
        'status' => 'ready',
    ]);

    $order = Order::create([
        'id' => 'ORD-TGSTOCK',
        'product_id' => $product->id,
        'email_or_whatsapp' => 'tgbuyer@test.com',
        'base_amount' => 15000,
        'unique_code' => 5,
        'total_amount' => 15005,
        'status' => 'pending_manual',
        'expired_at' => Carbon::now()->addMinutes(15),
    ]);

    // Send webhook approval request from Admin ID
    $response = $this->postJson(route('webhook.telegram'), [
        'callback_query' => [
            'id' => 'cb-tg-approve',
            'from' => [
                'id' => 987654321,
                'first_name' => 'Admin User',
            ],
            'data' => 'approve:ORD-TGSTOCK',
            'message' => [
                'message_id' => 12,
                'chat' => [
                    'id' => 987654321,
                ],
            ],
        ],
    ]);

    $response->assertStatus(200);

    // Verify order transitioned to 'paid' and vpn_config contains the oldest config
    $order->refresh();
    expect($order->status)->toBe('paid');
    expect($order->vpn_config)->toBe('vmess://oldest_ready');

    // Verify oldest config is sold and linked to the order
    $config1->refresh();
    expect($config1->status)->toBe('sold');
    expect($config1->order_id)->toBe($order->id);

    // Verify newest config is still ready
    $config2->refresh();
    expect($config2->status)->toBe('ready');

    // Verify product stock count is now 1
    $product->refresh();
    expect($product->stock)->toBe(1);
});

test('automated payment callback consumes a ready configuration and maps it to the order', function () {
    $product = Product::create([
        'name' => 'Auto VPN',
        'price' => 10000,
        'stock' => 0,
    ]);

    // Add stock config
    $config = AccountStock::create([
        'product_id' => $product->id,
        'account_data' => 'vmess://auto_ready',
        'status' => 'ready',
    ]);

    $order = Order::create([
        'id' => 'ORD-AUTOSTOCK',
        'product_id' => $product->id,
        'email_or_whatsapp' => 'autobuyer@test.com',
        'base_amount' => 10000,
        'unique_code' => 12,
        'total_amount' => 10012,
        'status' => 'pending',
        'expired_at' => Carbon::now()->addMinutes(15),
    ]);

    // Send successful payment callback
    $response = $this->postJson(route('api.payment.callback'), [
        'raw_text' => 'GOPAY TRANSFER RECEIVED Rp 10.012 FROM CUSTOMER',
        'amount' => 10012,
        'secret_key' => 'rahasiahappy123',
    ]);

    $response->assertStatus(200);

    // Verify order status updated to success and config is assigned
    $order->refresh();
    expect($order->status)->toBe('success');
    expect($order->vpn_config)->toBe('vmess://auto_ready');

    // Verify configuration marked as sold
    $config->refresh();
    expect($config->status)->toBe('sold');
    expect($config->order_id)->toBe($order->id);

    // Verify product stock count is now 0
    $product->refresh();
    expect($product->stock)->toBe(0);
});
