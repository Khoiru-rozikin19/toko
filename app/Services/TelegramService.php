<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    protected $token;
    protected $adminId;
    protected $apiBase;
    protected $sellerToken;
    protected $currentToken;

    public function __construct()
    {
        $this->token = env('TELEGRAM_BOT_TOKEN');
        $this->adminId = env('TELEGRAM_ADMIN_ID');
        $this->apiBase = rtrim(env('TELEGRAM_API_BASE', 'https://api.telegram.org'), '/');
        $this->sellerToken = env('TELEGRAM_SELLER_BOT_TOKEN') ?: $this->token;
        $this->currentToken = $this->token;
    }

    public function useAdminToken()
    {
        $this->currentToken = $this->token;
        return $this;
    }

    public function useSellerToken()
    {
        $this->currentToken = $this->sellerToken;
        return $this;
    }

    public function getAdminToken()
    {
        return $this->token;
    }

    public function getSellerToken()
    {
        return $this->sellerToken;
    }

    /**
     * Send order verification notification to Telegram Admin.
     *
     * @param string $orderId
     * @param int $amount
     * @param string $customerName
     * @return bool
     */
    public function sendOrderNotification($orderId, $amount, $customerName)
    {
        if (empty($this->token) || empty($this->adminId)) {
            Log::warning('TelegramService: Token atau Admin ID belum diset di .env');
            return false;
        }

        $formattedAmount = number_format($amount, 0, ',', '.');
        $text = "🔔 *Notifikasi Transaksi Baru*\n\n"
              . "📦 *ID Order:* `{$orderId}`\n"
              . "💰 *Nominal:* Rp {$formattedAmount}\n"
              . "👤 *Pelanggan:* {$customerName}\n\n"
              . "Silakan lakukan verifikasi pembayaran di bawah ini:";

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '✅ Approve', 'callback_data' => "approve:{$orderId}"],
                    ['text' => '❌ Reject', 'callback_data' => "reject:{$orderId}"]
                ]
            ]
        ];

        $response = $this->sendMessage($this->adminId, $text, $keyboard);
        if ($response && isset($response['ok']) && $response['ok']) {
            $messageId = $response['result']['message_id'] ?? null;
            if ($messageId) {
                \App\Models\Order::where('id', $orderId)->update([
                    'telegram_message_id' => $messageId
                ]);
            }
            return true;
        }
        return false;
    }

    /**
     * Send pre-order notification with cancel button to Telegram Admin.
     *
     * @param \App\Models\Order $order
     * @return bool
     */
    public function sendAdminPreorderNotification($order)
    {
        if (empty($this->token) || empty($this->adminId)) {
            Log::warning('TelegramService: Token atau Admin ID belum diset di .env');
            return false;
        }

        $formattedAmount = number_format($order->total_amount, 0, ',', '.');
        $customerName = $order->email_or_whatsapp;
        $preorderName = $order->customer_name ?: '-';
        $targetPhone = $order->target_phone ?: '-';
        $productName = $order->product ? $order->product->name : 'Produk';

        $text = "⏳ *Pre-Order Baru Diterima*\n\n"
              . "📦 *ID Order:* `{$order->id}`\n"
              . "🛍️ *Produk:* {$productName}\n"
              . "💰 *Nominal:* Rp {$formattedAmount}\n"
              . "👤 *Pelanggan:* {$customerName}\n"
              . "📝 *Nama Pre-Order:* {$preorderName}\n"
              . "📱 *No HP Tujuan:* `{$targetPhone}`\n\n"
              . "Status pre-order saat ini adalah *PROSES*.\n"
              . "Pesanan akan diteruskan otomatis ke Okeconnect ketika status produk dibuka oleh supplier.\n\n"
              . "Anda dapat membatalkan pre-order dan mengembalikan saldo user menggunakan tombol di bawah:";

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '❌ Batalkan Pre-Order', 'callback_data' => "cancel_preorder:{$order->id}"]
                ]
            ]
        ];

        $this->useAdminToken();
        $response = $this->sendMessage($this->adminId, $text, $keyboard);
        if ($response && isset($response['ok']) && $response['ok']) {
            $messageId = $response['result']['message_id'] ?? null;
            if ($messageId) {
                $order->update([
                    'telegram_message_id' => $messageId
                ]);
            }
            return true;
        }
        return false;
    }

    /**
     * Send order verification notification to Telegram Seller.
     *
     * @param string $orderId
     * @param int $amount
     * @param string $customerName
     * @param string $sellerChatId
     * @return bool
     */
    public function sendSellerOrderNotification($orderId, $amount, $customerName, $sellerChatId)
    {
        if (empty($this->sellerToken) || empty($sellerChatId)) {
            Log::warning('TelegramService: Seller Token atau Seller Chat ID belum diset');
            return false;
        }

        $formattedAmount = number_format($amount, 0, ',', '.');
        $text = "🔔 *Pesanan Baru untuk Seller*\n\n"
              . "📦 *ID Order:* `{$orderId}`\n"
              . "💰 *Nominal:* Rp {$formattedAmount}\n"
              . "👤 *Pelanggan:* {$customerName}\n\n"
              . "Silakan terima atau tolak pesanan ini:";

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '✅ Terima', 'callback_data' => "seller_accept:{$orderId}"],
                    ['text' => '❌ Tolak', 'callback_data' => "seller_reject:{$orderId}"]
                ]
            ]
        ];

        $response = $this->sendMessage($sellerChatId, $text, $keyboard, $this->sellerToken);
        if ($response && isset($response['ok']) && $response['ok']) {
            $messageId = $response['result']['message_id'] ?? null;
            if ($messageId) {
                \App\Models\Order::where('id', $orderId)->update([
                    'telegram_message_id' => $messageId
                ]);
            }
            return true;
        }
        return false;
    }

    /**
     * Send raw markdown message to Telegram.
     *
     * @param string $chatId
     * @param string $text
     * @param array|null $keyboard
     * @return array|bool
     */
    public function sendMessage($chatId, $text, $keyboard = null, $token = null)
    {
        if (empty($chatId)) {
            Log::warning("TelegramService: Chat ID is empty.");
            return false;
        }

        if (!is_numeric($chatId)) {
            if (str_starts_with($chatId, '@')) {
                Log::warning("TelegramService: Chat ID '{$chatId}' is a Telegram username. Note that sending direct messages to user/seller usernames is NOT supported by the Telegram Bot API; it requires a numeric Chat ID.");
            } else {
                Log::warning("TelegramService: Chat ID '{$chatId}' is not numeric. Telegram requires numeric Chat IDs for private chats.");
            }
        }

        $activeToken = $token ?: $this->currentToken;
        if (empty($activeToken)) {
            Log::warning("TelegramService: Bot Token is empty.");
            return false;
        }

        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown',
        ];

        if ($keyboard) {
            $payload['reply_markup'] = $keyboard;
        }

        try {
            $response = Http::timeout(8)
                ->withoutVerifying()
                ->post("{$this->apiBase}/bot{$activeToken}/sendMessage", $payload);
            if ($response->failed()) {
                Log::error("TelegramService sendMessage Failed: " . $response->body());
                return false;
            }
            return $response->json();
        } catch (\Exception $e) {
            Log::error("TelegramService sendMessage Exception: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Answer Telegram's callback query (removes loading state).
     *
     * @param string $callbackQueryId
     * @param string|null $text
     * @param string|null $token
     * @return bool
     */
    public function answerCallbackQuery($callbackQueryId, $text = null, $token = null)
    {
        $payload = [
            'callback_query_id' => $callbackQueryId,
        ];

        if ($text) {
            $payload['text'] = $text;
        }

        $activeToken = $token ?: $this->currentToken;
        if (empty($activeToken)) {
            Log::warning("TelegramService: Bot Token is empty.");
            return false;
        }

        try {
            $response = Http::timeout(8)
                ->withoutVerifying()
                ->post("{$this->apiBase}/bot{$activeToken}/answerCallbackQuery", $payload);
            if ($response->failed()) {
                Log::error("TelegramService answerCallbackQuery Failed: " . $response->body());
                return false;
            }
            return true;
        } catch (\Exception $e) {
            Log::error("TelegramService answerCallbackQuery Exception: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Edit text of an existing message in Telegram.
     *
     * @param string $chatId
     * @param int $messageId
     * @param string $text
     * @param string|null $token
     * @return bool
     */
    public function editMessageText($chatId, $messageId, $text, $keyboard = null, $token = null)
    {
        $payload = [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => 'Markdown',
        ];

        if ($keyboard) {
            $payload['reply_markup'] = $keyboard;
        }

        $activeToken = $token ?: $this->currentToken;
        if (empty($activeToken)) {
            Log::warning("TelegramService: Bot Token is empty.");
            return false;
        }

        try {
            $response = Http::timeout(8)
                ->withoutVerifying()
                ->post("{$this->apiBase}/bot{$activeToken}/editMessageText", $payload);
            if ($response->failed()) {
                Log::error("TelegramService editMessageText Failed: " . $response->body());
                return false;
            }
            return true;
        } catch (\Exception $e) {
            Log::error("TelegramService editMessageText Exception: " . $e->getMessage());
            return false;
        }
    }
}
