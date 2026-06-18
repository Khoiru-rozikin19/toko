<?php

define('LARAVEL_START', microtime(true));

// Register the Composer autoloader
require __DIR__.'/vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Order;
use App\Models\Product;
use App\Models\SellerCommission;
use App\Models\BalanceTransaction;
use Illuminate\Support\Facades\DB;

// Let's run inside a transaction and rollback at the end so we don't mess up the database.
DB::beginTransaction();

try {
    echo "=== RUNNING SELLER COMMISSION INTEGRATION TEST ===\n";

    // 1. Get or Create a Seller
    $seller = User::where('role', 'seller')->first();
    if (!$seller) {
        $seller = User::create([
            'name' => 'Mock Seller',
            'email' => 'mock_seller@test.com',
            'password' => bcrypt('password'),
            'role' => 'seller',
            'is_verified' => true,
        ]);
        echo "Created mock seller: ID {$seller->id}\n";
    } else {
        echo "Found existing seller: ID {$seller->id}\n";
    }

    // 2. Get or Create a Product (belonging to Admin/System)
    $product = Product::whereNull('user_id')->orWhere('user_id', 1)->first();
    if (!$product) {
        $product = Product::create([
            'name' => 'Mock Gmail Account',
            'price' => 20000,
            'harga_modal' => 15000,
            'duration_days' => 30,
            'stock' => 10,
            'user_id' => 1 // System Admin ID
        ]);
        echo "Created mock product: ID {$product->id}\n";
    } else {
        echo "Found product: ID {$product->id} (Price: {$product->price}, Owner ID: " . ($product->user_id ?? 'System') . ")\n";
    }

    // 3. Create active Seller Commission Rule for this product and seller
    SellerCommission::where('seller_id', $seller->id)->where('product_id', $product->id)->delete();
    $rule = SellerCommission::create([
        'seller_id' => $seller->id,
        'product_id' => $product->id,
        'commission_amount' => 4000,
        'is_active' => true,
    ]);
    echo "Created seller commission rule: {$rule->commission_amount} for Seller {$seller->id} on Product {$product->id}\n";

    // 4. Mock Order where Seller buys the Product using QRIS or Balance
    $order = Order::create([
        'id' => 'ORD-TESTMOCK' . rand(1000, 9999),
        'user_id' => $seller->id,
        'product_id' => $product->id,
        'email_or_whatsapp' => 'seller@test.com',
        'base_amount' => $product->price,
        'unique_code' => 0,
        'total_amount' => $product->price,
        'status' => 'success',
        'payment_method' => 'balance',
    ]);
    echo "Created mock order: ID {$order->id} for Seller {$seller->id}\n";

    // 5. Run processForOrder
    SellerCommission::processForOrder($order);
    $order->refresh();
    echo "Processed commission. Order commission_earned field value: {$order->commission_earned}\n";

    if ((int)$order->commission_earned === 4000) {
        echo "SUCCESS: Commission recorded on order successfully.\n";
    } else {
        echo "FAIL: Commission NOT recorded on order! Value: {$order->commission_earned}\n";
    }

    // 6. Calculate Wallet Balance for Seller
    // Profit dari penjualan produk sendiri (0 for mock seller who doesn't own product)
    $orderQuery = Order::whereHas('product', function ($pq) use ($seller) {
        $pq->where('user_id', $seller->id);
    });
    
    $walletBalance = 0;
    $successfulOrders = $orderQuery->whereIn('status', ['success', 'paid'])->with('product')->get();
    foreach ($successfulOrders as $so) {
        $prod = $so->product;
        $modal = $prod ? ($prod->harga_modal ?? 0) : 0;
        $walletBalance += ($so->total_amount - $modal);
    }
    echo "Initial sales profit wallet balance: {$walletBalance}\n";

    // Tambah komisi/cashback yang didapatkan dari pembelian produk
    $totalCommissions = Order::where('user_id', $seller->id)
        ->whereIn('status', ['success', 'paid'])
        ->sum('commission_earned');
    $walletBalance += $totalCommissions;
    echo "Wallet balance after adding commissions: {$walletBalance}\n";

    if ((int)$walletBalance === 4000) {
        echo "SUCCESS: Wallet balance shows Rp 4.000 successfully!\n";
    } else {
        echo "FAIL: Wallet balance shows {$walletBalance}\n";
    }

} catch (\Exception $e) {
    echo "ERROR IN TEST: " . $e->getMessage() . "\n";
} finally {
    // Rollback so we don't pollute local database with mock user, mock product, mock order.
    DB::rollBack();
    echo "DB Transaction rolled back. Database remains clean!\n";
}
