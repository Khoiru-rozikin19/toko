<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\PaymentLog;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class PaymentCallbackController extends Controller
{
    protected $orderkuotaService;

    public function __construct(\App\Services\OrderkuotaService $orderkuotaService)
    {
        $this->orderkuotaService = $orderkuotaService;
    }

    /**
     * Handle payment callback notifications from Android app.
     */
    public function handle(Request $request)
    {
        // Validate request structure (loosen amount check to support dot/comma formatted values)
        $request->validate([
            'raw_text' => 'required|string',
            'amount' => 'required',
            'secret_key' => 'required|string',
        ]);

        $secretKey = Setting::get('api_secret_key');

        // Verify secret key
        if (empty($secretKey) || $request->secret_key !== $secretKey) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Secret key tidak valid atau belum dikonfigurasi.',
            ], 401);
        }

        // Clean amount to ensure it is a valid integer
        $amount = (int) preg_replace('/[^0-9]/', '', $request->amount);
        $rawText = $request->raw_text;

        // Filter out Telegram order notifications to prevent self-trigger loop
        if (str_contains($rawText, 'Notifikasi Transaksi Baru') || 
            str_contains($rawText, 'ID Order') || 
            str_contains($rawText, 'Nominal:') ||
            str_contains($rawText, 'Pelanggan:')) {
            Log::warning("PaymentCallback: Ignored callback because raw_text matches Telegram order notification pattern.");
            return response()->json([
                'success' => false,
                'message' => 'Ignored. Raw text matches Telegram notification pattern.',
            ], 200);
        }

        // Check if this payment has already been matched to an order
        $existingLog = PaymentLog::where('raw_text', $rawText)
            ->whereNotNull('matched_order_id')
            ->first();

        if ($existingLog) {
            Log::info("PaymentCallback: Already matched raw_text to order: " . $existingLog->matched_order_id);
            return response()->json([
                'success' => true,
                'message' => 'Pembayaran ini sudah diproses sebelumnya.',
                'order_id' => $existingLog->matched_order_id,
            ], 200);
        }

        // Use atomic lock to prevent concurrent processing of the exact same callback notification
        $lockKey = 'payment_callback_lock_' . md5($rawText);
        $lock = \Illuminate\Support\Facades\Cache::lock($lockKey, 10);

        if (!$lock->get()) {
            return response()->json([
                'success' => false,
                'message' => 'Permintaan sedang diproses, silakan coba lagi.',
            ], 429);
        }

        try {
            $result = \Illuminate\Support\Facades\DB::transaction(function () use ($amount, $rawText) {
                // Find the oldest active pending order matching this total amount with lock
                $order = Order::whereIn('status', ['pending', 'pending_manual'])
                    ->where('total_amount', $amount)
                    ->where('expired_at', '>', Carbon::now())
                    ->orderBy('created_at', 'asc')
                    ->lockForUpdate()
                    ->first();

                if (!$order) {
                    return [
                        'success' => false,
                        'message' => 'Pembayaran diterima, tetapi tidak ada pesanan pending yang cocok untuk nominal Rp ' . number_format($amount, 0, ',', '.'),
                        'status_code' => 200
                    ];
                }

                // Log the incoming payment notification and link to the matched order
                $paymentLog = PaymentLog::create([
                    'raw_text' => $rawText,
                    'amount' => $amount,
                    'matched_order_id' => $order->id,
                ]);

                // === TOPUP BALANCE HANDLING ===
                if ($order->payment_method === 'topup_balance') {
                    $order->status = 'sukses';
                    $order->save();

                    // Add balance to user
                    if ($order->user_id) {
                        $user = \App\Models\User::find($order->user_id);
                        if ($user) {
                            $balanceRecord = \App\Models\UserBalance::where('user_id', $user->id)->lockForUpdate()->first();
                            if (!$balanceRecord) {
                                $balanceRecord = $user->getOrCreateBalance();
                                $balanceRecord = \App\Models\UserBalance::where('user_id', $user->id)->lockForUpdate()->first();
                            }
                            $balanceBefore = $balanceRecord->balance;
                            $balanceRecord->increment('balance', $order->base_amount);
                            $balanceRecord->refresh();

                            // Update balance transaction to success
                            \App\Models\BalanceTransaction::where('reference_id', $order->id)
                                ->where('status', 'pending')
                                ->update([
                                    'status' => 'success',
                                    'balance_before' => $balanceBefore,
                                    'balance_after' => $balanceRecord->balance,
                                ]);
                        }
                    }

                    return [
                        'success' => true,
                        'message' => 'Top up saldo berhasil! Saldo ' . $order->id . ' senilai Rp ' . number_format($order->base_amount, 0, ',', '.') . ' telah ditambahkan.',
                        'order_id' => $order->id,
                        'status_code' => 200
                    ];
                }

                // === NORMAL PRODUCT ORDER HANDLING ===
                // Assign local account stock if product uses dynamic stock
                if ($order->product && $order->product->stocks()->where('status', 'ready')->exists()) {
                    $stock = \App\Models\AccountStock::where('product_id', $order->product_id)
                        ->where('status', 'ready')
                        ->lockForUpdate()
                        ->first();

                    if ($stock) {
                        $stock->update([
                            'status' => 'sold',
                            'order_id' => $order->id,
                        ]);
                        $order->vpn_config = $stock->account_data;
                    }
                }

                // Complete the order
                $order->status = 'sukses';
                $order->save();

                // Process seller commissions
                \App\Models\SellerCommission::processForOrder($order);

                // Decrement product stock if not unlimited
                if ($order->product && $order->product->stock > 0) {
                    if (!$order->product->stocks()->where('status', 'ready')->exists()) {
                        $product = \App\Models\Product::where('id', $order->product_id)->lockForUpdate()->first();
                        if ($product && $product->stock > 0) {
                            $product->decrement('stock');
                        }
                    }
                }

                return [
                    'success' => true,
                    'message' => 'Pesanan ' . $order->id . ' berhasil diverifikasi dan diaktifkan.',
                    'order_id' => $order->id,
                    'status_code' => 200
                ];
            });

            if ($result['success'] && isset($result['order_id'])) {
                $orderId = $result['order_id'];

                // Defer slow operations (SSH connection, Telegram requests, Okeconnect APIs)
                // to run asynchronously after the HTTP response has been sent to the Android app.
                app()->terminating(function () use ($orderId) {
                    $order = Order::with('product')->find($orderId);
                    if (!$order) {
                        return;
                    }

                    // 1. VPS account creation via SSH
                    if ($order->product && $order->product->vps_server_id) {
                        try {
                            app(\App\Services\VpsSshService::class)->createVpnAccount($order);
                            $order->save();
                        } catch (\Exception $e) {
                            Log::error("PaymentCallback: Failed to create VPS account for order {$order->id}: " . $e->getMessage());
                        }
                    }

                    // 2. Escrow processing and seller notifications
                    try {
                        $order->processEscrowAndNotification();
                    } catch (\Exception $e) {
                        Log::error("PaymentCallback: Failed to process escrow/notification for order {$order->id}: " . $e->getMessage());
                    }

                    // 3. Send order to Orderkuota API
                    try {
                        app(\App\Services\OrderkuotaService::class)->kirimPesananKeOrderkuota($order->id);
                    } catch (\Exception $e) {
                        Log::error("PaymentCallback: Failed to send order {$order->id} to Orderkuota: " . $e->getMessage());
                    }

                    // 4. Update Telegram message to Admin
                    if ($order->telegram_message_id) {
                        try {
                            $telegramService = app(\App\Services\TelegramService::class);
                            $adminId = env('TELEGRAM_ADMIN_ID');
                            $formattedAmount = number_format($order->total_amount, 0, ',', '.');
                            $customerName = $order->email_or_whatsapp;

                            if ($order->payment_method === 'topup_balance') {
                                $updatedText = "✅ *Top Up Sukses (Otomatis)*\n\n"
                                             . "📦 *ID Order:* `{$order->id}`\n"
                                             . "💰 *Nominal:* Rp {$formattedAmount}\n"
                                             . "👤 *Pelanggan:* {$customerName}\n\n"
                                             . "Status top up telah diubah menjadi *SUKSES* (diverifikasi otomatis oleh pembaca notifikasi) dan saldo telah ditambahkan ke akun user.";
                            } else {
                                $updatedText = "✅ *Transaksi Sukses (Otomatis)*\n\n"
                                             . "📦 *ID Order:* `{$order->id}`\n"
                                             . "💰 *Nominal:* Rp {$formattedAmount}\n"
                                             . "👤 *Pelanggan:* {$customerName}\n\n"
                                             . "Status transaksi telah diubah menjadi *SUKSES* (diverifikasi otomatis oleh pembaca notifikasi) dan pesanan diteruskan ke supplier.";
                            }

                            $telegramService->editMessageText($adminId, $order->telegram_message_id, $updatedText);
                        } catch (\Exception $e) {
                            Log::error("PaymentCallback: Failed to update Telegram message for order {$order->id}: " . $e->getMessage());
                        }
                    }
                });
            }

            return response()->json([
                'success' => $result['success'],
                'message' => $result['message'],
                'order_id' => $result['order_id'] ?? null,
            ], $result['status_code']);

        } finally {
            $lock->release();
        }
    }
}
