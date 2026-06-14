<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\PaymentCallbackController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AdminController;

// User Frontend Routes
Route::get('/', [CatalogController::class, 'index'])->name('catalog');
Route::get('/catalog', [CatalogController::class, 'index']);
Route::post('/buy', [CatalogController::class, 'buy'])->name('buy');
Route::get('/orders/{id}/status', [CatalogController::class, 'checkStatus'])->name('order.status');
Route::get('/orders/{id}/download', [CatalogController::class, 'download'])->name('order.download');

// API Callback (for Android Notification Listener)
Route::post('/api/v1/payment/callback-notification', [PaymentCallbackController::class, 'handle'])->name('api.payment.callback');

// Admin Auth Routes
Route::get('/admin/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/admin/login', [AuthController::class, 'login']);
Route::post('/admin/logout', [AuthController::class, 'logout'])->name('logout');

// Admin Portal Routes (Protected by Auth middleware)
Route::middleware('auth')->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [AdminController::class, 'dashboard'])->name('dashboard');
    
    // Products CRUD
    Route::get('/products', [AdminController::class, 'products'])->name('products');
    Route::post('/products', [AdminController::class, 'storeProduct'])->name('products.store');
    Route::post('/products/{id}/update', [AdminController::class, 'updateProduct'])->name('products.update');
    Route::post('/products/{id}/delete', [AdminController::class, 'deleteProduct'])->name('products.delete');
    
    // Transactions
    Route::get('/transactions', [AdminController::class, 'transactions'])->name('transactions');
    
    // Settings
    Route::get('/settings', [AdminController::class, 'settings'])->name('settings');
    Route::post('/settings', [AdminController::class, 'updateSettings'])->name('settings.update');
});
