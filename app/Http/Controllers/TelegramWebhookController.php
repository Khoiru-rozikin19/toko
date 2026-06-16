<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Order;
use App\Services\TelegramService;
use App\Services\OrderkuotaService;
use Illuminate\Support\Facades\Log;

class TelegramWebhookController extends Controller
{
    protected $telegramService;
    protected $orderkuotaService;

    public function __construct(TelegramService $telegramService, OrderkuotaService $orderkuotaService)
    {
        $this->telegramService = $telegramService;
        $this->orderkuotaService = $orderkuotaService;
    }

    /**
     * Handle Telegram webhook request.
     */
    public function handle(Request $request)
    {
        Log::info("Telegram Webhook Received: " . json_encode($request->all()));

        $callbackQuery = $request->input('callback_query');

        if (!$callbackQuery) {
            return response()->json([
                'success' => true,
                'message' => 'Not a callback query, ignoring.',
            ]);
        }

        $callbackQueryId = $callbackQuery['id'] ?? null;
        $senderId = $callbackQuery['from']['id'] ?? null;
        $data = $callbackQuery['data'] ?? '';
        $message = $callbackQuery['message'] ?? null;
        $messageId = $message['message_id'] ?? null;
        $chatId = $message['chat']['id'] ?? null;

        $adminId = env('TELEGRAM_ADMIN_ID');

        // Security check: must originate from the admin
        if ((string)$senderId !== (string)$adminId) {
            Log::warning("Telegram Webhook Unauthorized: Sender ID {$senderId} does not match Admin ID {$adminId}");
            
            if ($callbackQueryId) {
                $this->telegramService->answerCallbackQuery($callbackQueryId, "Akses Ditolak: Anda bukan Admin.");
            }

            return response()->json([
                'success' => false,
                'message' => 'Unauthorized sender.',
            ], 403);
        }

        // Parse callback data format (action:order_id)
        if (strpos($data, ':') === false) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid callback data format.',
            ], 400);
        }

        list($action, $orderId) = explode(':', $data, 2);

        $order = Order::with('product')->find($orderId);
        if (!$order) {
            Log::warning("Telegram Webhook Warning: Order ID {$orderId} not found.");
            
            if ($callbackQueryId) {
                $this->telegramService->answerCallbackQuery($callbackQueryId, "Order tidak ditemukan.");
            }

            return response()->json([
                'success' => false,
                'message' => 'Order not found.',
            ], 404);
        }

        $formattedAmount = number_format($order->total_amount, 0, ',', '.');
        $customerName = $order->email_or_whatsapp;
        if ($action === 'approve') {
            // Assign local account stock if product uses dynamic stock
            if ($order->product && $order->product->stocks()->where('status', 'ready')->exists()) {
                $stock = \App\Models\AccountStock::where('product_id', $order->product_id)
                    ->where('status', 'ready')
                    ->first();

                if (!$stock) {
                    Log::warning("Telegram Webhook Warning: Stock exhausted for product {$order->product_id}");
                    if ($callbackQueryId) {
                        $this->telegramService->answerCallbackQuery($callbackQueryId, "Gagal: Stok akun habis!");
                    }
                    return response()->json([
                        'success' => false,
                        'message' => 'Stock exhausted.',
                    ], 400);
                }

                $stock->update([
                    'status' => 'sold',
                    'order_id' => $order->id,
                ]);

                $order->vpn_config = $stock->account_data;
            }

            // Run VPS account creation if product is linked to a VPS server
            if ($order->product && $order->product->vps_server_id) {
                app(\App\Services\VpsSshService::class)->createVpnAccount($order);
            }

            // Update order status to paid
            $order->status = 'paid';
            $order->save();

            // Decrement product stock if not unlimited
            if ($order->product && $order->product->stock > 0) {
                if (!$order->product->stocks()->where('status', 'ready')->exists()) {
                    $order->product->decrement('stock');
                }
            }

            // Run the pulsa transaction
            $this->orderkuotaService->prosesTransaksiPulsa($order->id);

            // Acknowledge Telegram callback query
            if ($callbackQueryId) {
                $this->telegramService->answerCallbackQuery($callbackQueryId, "Transaksi {$orderId} disetujui!");
            }

            // Edit message to reflect approval
            if ($chatId && $messageId) {
                $updatedText = "✅ *Transaksi Disetujui*\n\n"
                             . "📦 *ID Order:* `{$orderId}`\n"
                             . "💰 *Nominal:* Rp {$formattedAmount}\n"
                             . "👤 *Pelanggan:* {$customerName}\n\n"
                             . "Status transaksi telah diubah menjadi *PAID* dan pesanan diteruskan ke supplier.";
                $this->telegramService->editMessageText($chatId, $messageId, $updatedText);
            }

            return response()->json([
                'success' => true,
                'message' => "Order {$orderId} approved and processed.",
            ]);
        }

        if ($action === 'reject') {
            // Update order status to rejected
            $order->status = 'rejected';
            $order->save();

            // Optional notification to the user
            Log::info("Notification sent to user {$customerName}: Transaction {$orderId} has been rejected by Admin.");

            // Acknowledge Telegram callback query
            if ($callbackQueryId) {
                $this->telegramService->answerCallbackQuery($callbackQueryId, "Transaksi {$orderId} ditolak!");
            }

            // Edit message to reflect rejection
            if ($chatId && $messageId) {
                $updatedText = "❌ *Transaksi Ditolak*\n\n"
                             . "📦 *ID Order:* `{$orderId}`\n"
                             . "💰 *Nominal:* Rp {$formattedAmount}\n"
                             . "👤 *Pelanggan:* {$customerName}\n\n"
                             . "Status transaksi telah diubah menjadi *REJECTED* oleh Admin.";
                $this->telegramService->editMessageText($chatId, $messageId, $updatedText);
            }

            return response()->json([
                'success' => true,
                'message' => "Order {$orderId} rejected.",
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Unknown action.',
        ], 400);
    }
}
