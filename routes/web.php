<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\PaymentCallbackController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\BuyerController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\BalanceController;

// API Callback (public - no auth required)
Route::post('/api/v1/payment/callback-notification', [PaymentCallbackController::class, 'handle'])->name('api.payment.callback');
Route::post('/api/callback/okeconnect', [App\Http\Controllers\OkeconnectCallbackController::class, 'handle'])->name('api.callback.okeconnect');
Route::post('/webhook/telegram', [App\Http\Controllers\TelegramWebhookController::class, 'handle'])->name('webhook.telegram');

// Guest Auth Routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
});

Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

// Public Catalog Routes
Route::get('/', [CatalogController::class, 'index'])->name('catalog');
Route::get('/catalog', [CatalogController::class, 'index']);

// Rute Terkunci (Wajib Login & Terverifikasi - Semua Peran)
Route::middleware(['auth', 'role:buyer,seller,admin'])->group(function () {
    
    // User Frontend Routes
    Route::post('/buy', [CatalogController::class, 'buy'])->name('buy');
    Route::get('/orders', [CatalogController::class, 'history'])->name('orders.history');
    Route::get('/orders/{id}/status', [CatalogController::class, 'checkStatus'])->name('order.status');
    Route::get('/orders/{id}/download', [CatalogController::class, 'download'])->name('order.download');
    
    // User Profile Routes
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::post('/profile', [ProfileController::class, 'update'])->name('profile.update');

    // User Balance Routes
    Route::get('/balance', [BalanceController::class, 'index'])->name('balance.index');
    Route::post('/balance/topup', [BalanceController::class, 'topup'])->name('balance.topup');
    Route::get('/balance/topup/{id}/status', [BalanceController::class, 'checkTopupStatus'])->name('balance.topup.status');
    Route::get('/api/balance', [BalanceController::class, 'getBalance'])->name('balance.api.get');
    
    // Area Buyer (Khusus Pengajuan Upgrade Peran)
    Route::middleware('role:buyer')->prefix('buyer')->name('buyer.')->group(function () {
        Route::post('/request-seller', [BuyerController::class, 'requestUpgradeToSeller'])->name('request-seller');
    });
});

// Area Portal Seller / Admin (Protected by Auth & RoleMiddleware)
Route::middleware(['auth', 'role:seller,admin'])->prefix('admin')->name('admin.')->group(function () {
    
    // Dashboard & CRUD Produk & Transaksi
    Route::get('/dashboard', [AdminController::class, 'dashboard'])->name('dashboard');
    Route::post('/transfer-to-balance', [AdminController::class, 'transferToBalance'])->name('transfer_to_balance');
    Route::get('/products', [AdminController::class, 'products'])->name('products');
    Route::post('/products', [AdminController::class, 'storeProduct'])->name('products.store');
    Route::post('/products/{id}/update', [AdminController::class, 'updateProduct'])->name('products.update');
    Route::post('/products/{id}/delete', [AdminController::class, 'deleteProduct'])->name('products.delete');
    Route::get('/account-stocks', [AdminController::class, 'accountStocks'])->name('account_stocks');
    Route::post('/account-stocks', [AdminController::class, 'storeAccountStocks'])->name('account_stocks.store');
    Route::post('/account-stocks/{id}/delete', [AdminController::class, 'deleteAccountStock'])->name('account_stocks.delete');
    Route::get('/transactions', [AdminController::class, 'transactions'])->name('transactions');

    // Khusus Admin Utama (Konfigurasi QRIS & Manajemen Pengguna)
    Route::middleware('role:admin')->group(function () {
        Route::get('/orderkuota-balance', [AdminController::class, 'orderkuotaBalance'])->name('orderkuota_balance');
        Route::get('/settings', [AdminController::class, 'settings'])->name('settings');
        Route::post('/settings', [AdminController::class, 'updateSettings'])->name('settings.update');
        Route::get('/supplier-settings', [AdminController::class, 'supplierSettings'])->name('supplier_settings');
        Route::post('/supplier-settings', [AdminController::class, 'updateSupplierSettings'])->name('supplier_settings.update');
        
        // Manajemen User (Persetujuan Pendaftaran & Upgrade Seller & Kelola Akun)
        Route::get('/users', [AdminController::class, 'userManagement'])->name('users');
        Route::post('/users/{id}/approve-account', [AdminController::class, 'approveAccount']);
        Route::post('/users/{id}/reject-account', [AdminController::class, 'rejectAccount']);
        Route::post('/users/{id}/approve-seller', [AdminController::class, 'approveSeller']);
        Route::post('/users/{id}/reject-seller', [AdminController::class, 'rejectSeller']);
        Route::post('/users/{id}/update-role', [AdminController::class, 'updateRole']);
        Route::post('/users/{id}/toggle-status', [AdminController::class, 'toggleStatus']);
        Route::post('/users/{id}/delete', [AdminController::class, 'deleteUser']);

        // Kelola Kategori Produk
        Route::post('/categories', [AdminController::class, 'storeCategory'])->name('categories.store');
        Route::delete('/categories/{id}', [AdminController::class, 'deleteCategory'])->name('categories.delete');
        Route::post('/categories/reorder', [AdminController::class, 'reorderCategories'])->name('categories.reorder');

        // Kelola VPN Panel
        Route::get('/vpn-panel', [AdminController::class, 'vpnPanel'])->name('vpn_panel');
        Route::post('/vpn-panel', [AdminController::class, 'storeVpnServer'])->name('vpn_panel.store');
        Route::post('/vpn-panel/{id}/update', [AdminController::class, 'updateVpnServer'])->name('vpn_panel.update');
        Route::post('/vpn-panel/{id}/delete', [AdminController::class, 'deleteVpnServer'])->name('vpn_panel.delete');
        Route::post('/vpn-panel/test-connection', [AdminController::class, 'testVpnServerConnection'])->name('vpn_panel.test');

        // Kelola Komisi Seller
        Route::get('/commissions', [AdminController::class, 'commissions'])->name('commissions');
        Route::post('/commissions', [AdminController::class, 'storeCommission'])->name('commissions.store');
        Route::post('/commissions/{id}/update', [AdminController::class, 'updateCommission'])->name('commissions.update');
        Route::post('/commissions/{id}/delete', [AdminController::class, 'deleteCommission'])->name('commissions.delete');
        Route::post('/commissions/{id}/toggle', [AdminController::class, 'toggleCommission'])->name('commissions.toggle');

    });
});
