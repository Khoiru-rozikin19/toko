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
        // Validate request structure
        $request->validate([
            'raw_text' => 'required|string',
            'amount' => 'required|integer',
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

        $amount = (int) $request->amount;
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

        // Assign local account stock if product is a local config product and uses dynamic stock
        if ($order->product && empty($order->product->orderkuota_product_code) && $order->product->stocks()->exists()) {
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

        // Complete the order
        $order->status = 'success';
        $order->save();

        // Kirim pesanan ke Orderkuota secara langsung
        $this->orderkuotaService->kirimPesananKeOrderkuota($order->id);

        // Decrement product stock if not unlimited
        if ($order->product && $order->product->stock > 0) {
            if (!empty($order->product->orderkuota_product_code) || !$order->product->stocks()->exists()) {
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
