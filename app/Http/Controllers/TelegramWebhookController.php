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

        // Parse callback data format (action:order_id)
        if (strpos($data, ':') === false) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid callback data format.',
            ], 400);
        }

        list($action, $orderId) = explode(':', $data, 2);

        // Switch to the correct bot token dynamically depending on the action type
        if (in_array($action, ['seller_accept', 'seller_reject'])) {
            $this->telegramService->useSellerToken();
        } else {
            $this->telegramService->useAdminToken();
        }

        $order = Order::with('product.seller')->find($orderId);
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

        $adminId = env('TELEGRAM_ADMIN_ID');

        // Security check
        if (in_array($action, ['approve', 'reject'])) {
            // Admin action
            if ((string)$senderId !== (string)$adminId) {
                Log::warning("Telegram Webhook Unauthorized Admin Action: Sender ID {$senderId} does not match Admin ID {$adminId}");
                if ($callbackQueryId) {
                    $this->telegramService->answerCallbackQuery($callbackQueryId, "Akses Ditolak: Anda bukan Admin.");
                }
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized admin action.',
                ], 403);
            }
        } elseif (in_array($action, ['seller_accept', 'seller_reject'])) {
            // Seller action
            $sellerChatId = $order->product ? ($order->product->seller->telegram_chat_id ?? null) : null;
            if (empty($sellerChatId) || (string)$senderId !== (string)$sellerChatId) {
                Log::warning("Telegram Webhook Unauthorized Seller Action: Sender ID {$senderId} does not match Seller Chat ID {$sellerChatId}");
                if ($callbackQueryId) {
                    $this->telegramService->answerCallbackQuery($callbackQueryId, "Akses Ditolak: Anda bukan Seller untuk order ini.");
                }
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized seller action.',
                ], 403);
            }
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Invalid action.',
            ], 400);
        }

        $result = \Illuminate\Support\Facades\DB::transaction(function () use ($orderId, $action, $callbackQueryId, $chatId, $messageId) {
            // Find order with write lock
            $order = Order::with('product.seller')->where('id', $orderId)->lockForUpdate()->first();
            if (!$order) {
                Log::warning("Telegram Webhook Warning: Order ID {$orderId} not found inside transaction.");
                if ($callbackQueryId) {
                    $this->telegramService->answerCallbackQuery($callbackQueryId, "Order tidak ditemukan.");
                }
                return [
                    'success' => false,
                    'message' => 'Order not found.',
                    'status_code' => 404
                ];
            }

            // Verify order status depending on the action to prevent duplicate processing
            if (in_array($action, ['approve', 'reject'])) {
                if ($order->status !== 'pending' && $order->status !== 'pending_manual') {
                    if ($callbackQueryId) {
                        $this->telegramService->answerCallbackQuery($callbackQueryId, "Order sudah diproses sebelumnya (Status: {$order->status}).");
                    }
                    return [
                        'success' => false,
                        'message' => 'Order already processed.',
                        'status_code' => 200
                    ];
                }
            } elseif (in_array($action, ['seller_accept', 'seller_reject'])) {
                if ($order->status !== 'success' && $order->status !== 'paid') {
                    if ($callbackQueryId) {
                        $this->telegramService->answerCallbackQuery($callbackQueryId, "Order tidak dalam status untuk diproses seller (Status: {$order->status}).");
                    }
                    return [
                        'success' => false,
                        'message' => 'Order already processed by seller.',
                        'status_code' => 200
                    ];
                }
            }

            $formattedAmount = number_format($order->total_amount, 0, ',', '.');
            $customerName = $order->email_or_whatsapp;
            
            if ($action === 'approve') {
                if ($order->payment_method === 'topup_balance') {
                    $order->status = 'success';
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

                    // Acknowledge Telegram callback query
                    if ($callbackQueryId) {
                        $this->telegramService->answerCallbackQuery($callbackQueryId, "Top up {$orderId} disetujui!");
                    }

                    // Edit message to reflect approval
                    if ($chatId && $messageId) {
                        $updatedText = "✅ *Top Up Disetujui*\n\n"
                                     . "📦 *ID Order:* `{$orderId}`\n"
                                     . "💰 *Nominal:* Rp {$formattedAmount}\n"
                                     . "👤 *Pelanggan:* {$customerName}\n\n"
                                     . "Status top up telah diubah menjadi *SUCCESS* dan saldo telah ditambahkan ke akun user.";
                        $this->telegramService->editMessageText($chatId, $messageId, $updatedText);
                    }

                    return [
                        'success' => true,
                        'message' => "Top up {$orderId} approved and balance added.",
                        'status_code' => 200
                    ];
                }

                // Assign local account stock if product uses dynamic stock
                if ($order->product && $order->product->stocks()->where('status', 'ready')->exists()) {
                    $stock = \App\Models\AccountStock::where('product_id', $order->product_id)
                        ->where('status', 'ready')
                        ->lockForUpdate()
                        ->first();

                    if (!$stock) {
                        Log::warning("Telegram Webhook Warning: Stock exhausted for product {$order->product_id}");
                        if ($callbackQueryId) {
                            $this->telegramService->answerCallbackQuery($callbackQueryId, "Gagal: Stok akun habis!");
                        }
                        return [
                            'success' => false,
                            'message' => 'Stock exhausted.',
                            'status_code' => 400
                        ];
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
                $order->processEscrowAndNotification();

                // Decrement product stock if not unlimited
                if ($order->product && $order->product->stock > 0) {
                    if (!$order->product->stocks()->where('status', 'ready')->exists()) {
                        $prod = \App\Models\Product::where('id', $order->product_id)->lockForUpdate()->first();
                        if ($prod && $prod->stock > 0) {
                            $prod->decrement('stock');
                        }
                    }
                }

                // Process seller commissions
                \App\Models\SellerCommission::processForOrder($order);

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

                return [
                    'success' => true,
                    'message' => "Order {$orderId} approved and processed.",
                    'status_code' => 200
                ];
            }

            if ($action === 'reject') {
                // Update order status to rejected
                $order->status = 'rejected';
                $order->save();

                if ($order->payment_method === 'topup_balance') {
                    \App\Models\BalanceTransaction::where('reference_id', $order->id)
                        ->where('status', 'pending')
                        ->update(['status' => 'failed']);
                }

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

                return [
                    'success' => true,
                    'message' => "Order {$orderId} rejected.",
                    'status_code' => 200
                ];
            }

            if ($action === 'seller_accept') {
                $order->status = 'success';
                $order->save();

                if ($callbackQueryId) {
                    $this->telegramService->answerCallbackQuery($callbackQueryId, "Pesanan diterima dan sukses!");
                }

                if ($chatId && $messageId) {
                    $updatedText = "✅ *Pesanan Diterima Seller*\n\n"
                                 . "📦 *ID Order:* `{$orderId}`\n"
                                 . "💰 *Nominal:* Rp {$formattedAmount}\n"
                                 . "👤 *Pelanggan:* {$customerName}\n\n"
                                 . "Status pesanan telah diubah menjadi *SUCCESS* oleh Seller.";
                    $this->telegramService->editMessageText($chatId, $messageId, $updatedText);
                }

                return [
                    'success' => true,
                    'message' => "Order {$orderId} accepted by seller.",
                    'status_code' => 200
                ];
            }

            if ($action === 'seller_reject') {
                // Update order status to gagal
                $order->status = 'gagal';
                $order->save();

                // Deduct from seller's held_balance
                $sellerId = $order->product ? $order->product->user_id : null;
                if ($sellerId) {
                    $sellerBalance = \App\Models\UserBalance::where('user_id', $sellerId)->lockForUpdate()->first();
                    if ($sellerBalance) {
                        $newHeld = max(0, $sellerBalance->held_balance - $order->escrow_amount);
                        $sellerBalance->update(['held_balance' => $newHeld]);
                    }
                }
                $order->escrow_status = 'none';
                $order->save();

                // Refund buyer if payment method was balance
                if ($order->payment_method === 'balance' && $order->user_id) {
                    $buyer = \App\Models\User::find($order->user_id);
                    if ($buyer) {
                        $buyerBalance = \App\Models\UserBalance::where('user_id', $buyer->id)->lockForUpdate()->first();
                        if (!$buyerBalance) {
                            $buyerBalance = $buyer->getOrCreateBalance();
                            $buyerBalance = \App\Models\UserBalance::where('user_id', $buyer->id)->lockForUpdate()->first();
                        }
                        $balanceBefore = $buyerBalance->balance;
                        $buyerBalance->increment('balance', $order->total_amount);
                        $buyerBalance->refresh();

                        // Record balance transaction
                        \App\Models\BalanceTransaction::create([
                            'user_id' => $buyer->id,
                            'type' => 'topup',
                            'amount' => $order->total_amount,
                            'balance_before' => $balanceBefore,
                            'balance_after' => $buyerBalance->balance,
                            'description' => 'Refund penolakan seller: ' . ($order->product->name ?? 'Produk'),
                            'reference_id' => $order->id,
                            'status' => 'success',
                        ]);
                    }
                }

                if ($callbackQueryId) {
                    $this->telegramService->answerCallbackQuery($callbackQueryId, "Pesanan ditolak oleh seller!");
                }

                if ($chatId && $messageId) {
                    $updatedText = "❌ *Pesanan Ditolak Seller*\n\n"
                                 . "📦 *ID Order:* `{$orderId}`\n"
                                 . "💰 *Nominal:* Rp {$formattedAmount}\n"
                                 . "👤 *Pelanggan:* {$customerName}\n\n"
                                 . "Status pesanan telah diubah menjadi *GAGAL* oleh Seller.";
                    $this->telegramService->editMessageText($chatId, $messageId, $updatedText);
                }

                return [
                    'success' => true,
                    'message' => "Order {$orderId} rejected by seller.",
                    'status_code' => 200
                ];
            }

            return [
                'success' => false,
                'message' => 'Unknown action.',
                'status_code' => 400
            ];
        });

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'],
        ], $result['status_code']);
    }
}
