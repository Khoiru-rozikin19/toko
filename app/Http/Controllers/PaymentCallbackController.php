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

        // Log the incoming payment notification
        $paymentLog = PaymentLog::create([
            'raw_text' => $rawText,
            'amount' => $amount,
        ]);

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

        // Find the oldest active pending order matching this total amount
        $order = Order::whereIn('status', ['pending', 'pending_manual'])
            ->where('total_amount', $amount)
            ->where('expired_at', '>', Carbon::now())
            ->orderBy('created_at', 'asc')
            ->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Pembayaran diterima, tetapi tidak ada pesanan pending yang cocok untuk nominal Rp ' . number_format($amount, 0, ',', '.'),
            ], 200); // Return 200 so the Android app doesn't retry unnecessarily, but specify false
        }

        // === TOPUP BALANCE HANDLING ===
        if ($order->payment_method === 'topup_balance') {
            \Illuminate\Support\Facades\DB::transaction(function () use ($order) {
                $order->status = 'success';
                $order->save();

                // Add balance to user
                if ($order->user_id) {
                    $user = \App\Models\User::find($order->user_id);
                    if ($user) {
                        $balanceRecord = $user->getOrCreateBalance();
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
            });

            // Link log to the matched order
            $paymentLog->update([
                'matched_order_id' => $order->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Top up saldo berhasil! Saldo ' . $order->id . ' senilai Rp ' . number_format($order->base_amount, 0, ',', '.') . ' telah ditambahkan.',
                'order_id' => $order->id,
            ]);
        }

        // === NORMAL PRODUCT ORDER HANDLING ===
        // Assign local account stock if product uses dynamic stock
        if ($order->product && $order->product->stocks()->where('status', 'ready')->exists()) {
            $stock = \App\Models\AccountStock::where('product_id', $order->product_id)
                ->where('status', 'ready')
                ->first();

            if ($stock) {
                $stock->update([
                    'status' => 'sold',
                    'order_id' => $order->id,
                ]);
                $order->vpn_config = $stock->account_data;
            }
        }

        // Run VPS account creation if product is linked to a VPS server
        if ($order->product && $order->product->vps_server_id) {
            app(\App\Services\VpsSshService::class)->createVpnAccount($order);
        }

        // Complete the order
        $order->status = 'success';
        $order->save();

        // Kirim pesanan ke Orderkuota secara langsung
        $this->orderkuotaService->kirimPesananKeOrderkuota($order->id);

        // Decrement product stock if not unlimited
        if ($order->product && $order->product->stock > 0) {
            if (!$order->product->stocks()->where('status', 'ready')->exists()) {
                $order->product->decrement('stock');
            }
        }

        // Link log to the matched order
        $paymentLog->update([
            'matched_order_id' => $order->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Pesanan ' . $order->id . ' berhasil diverifikasi dan diaktifkan.',
            'order_id' => $order->id,
        ]);
    }
}
