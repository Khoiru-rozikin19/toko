<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\PaymentCallbackController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\BuyerController;

// API Callback (public - no auth required)
Route::post('/api/v1/payment/callback-notification', [PaymentCallbackController::class, 'handle'])->name('api.payment.callback');

// Guest Auth Routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
});

Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

// Rute Terkunci (Wajib Login & Terverifikasi - Semua Peran)
Route::middleware(['auth', 'role:buyer,seller,admin'])->group(function () {
    
    // User Frontend Routes
    Route::get('/', [CatalogController::class, 'index'])->name('catalog');
    Route::get('/catalog', [CatalogController::class, 'index']);
    Route::post('/buy', [CatalogController::class, 'buy'])->name('buy');
    Route::get('/orders/{id}/status', [CatalogController::class, 'checkStatus'])->name('order.status');
    Route::get('/orders/{id}/download', [CatalogController::class, 'download'])->name('order.download');
    
    // Area Buyer (Khusus Pengajuan Upgrade Peran)
    Route::middleware('role:buyer')->prefix('buyer')->name('buyer.')->group(function () {
        Route::post('/request-seller', [BuyerController::class, 'requestUpgradeToSeller'])->name('request-seller');
    });
});

// Area Portal Seller / Admin (Protected by Auth & RoleMiddleware)
Route::middleware(['auth', 'role:seller,admin'])->prefix('admin')->name('admin.')->group(function () {
    
    // Dashboard & CRUD Produk & Transaksi
    Route::get('/dashboard', [AdminController::class, 'dashboard'])->name('dashboard');
    Route::get('/products', [AdminController::class, 'products'])->name('products');
    Route::post('/products', [AdminController::class, 'storeProduct'])->name('products.store');
    Route::post('/products/{id}/update', [AdminController::class, 'updateProduct'])->name('products.update');
    Route::post('/products/{id}/delete', [AdminController::class, 'deleteProduct'])->name('products.delete');
    Route::get('/transactions', [AdminController::class, 'transactions'])->name('transactions');

    // Khusus Admin Utama (Konfigurasi QRIS & Manajemen Pengguna)
    Route::middleware('role:admin')->group(function () {
        Route::get('/settings', [AdminController::class, 'settings'])->name('settings');
        Route::post('/settings', [AdminController::class, 'updateSettings'])->name('settings.update');
        
        // Manajemen User (Persetujuan Pendaftaran & Upgrade Seller)
        Route::get('/users', [AdminController::class, 'userManagement'])->name('users');
        Route::post('/users/{id}/approve-account', [AdminController::class, 'approveAccount']);
        Route::post('/users/{id}/reject-account', [AdminController::class, 'rejectAccount']);
        Route::post('/users/{id}/approve-seller', [AdminController::class, 'approveSeller']);
        Route::post('/users/{id}/reject-seller', [AdminController::class, 'rejectSeller']);
    });
});
