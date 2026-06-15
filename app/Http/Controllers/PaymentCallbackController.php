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

        // Find the oldest active pending order matching this total amount
        $order = Order::where('status', 'pending')
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

        // Complete the order
        $order->status = 'success';
        $order->save();

        // Dispatch background job to trigger supplier API integration
        \App\Jobs\SendOrderToOrderkuota::dispatch($order->id);

        // Decrement product stock if not unlimited
        if ($order->product && $order->product->stock > 0) {
            $order->product->decrement('stock');
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
