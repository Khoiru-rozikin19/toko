<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    protected $token;
    protected $adminId;
    protected $apiBase;

    public function __construct()
    {
        $this->token = env('TELEGRAM_BOT_TOKEN');
        $this->adminId = env('TELEGRAM_ADMIN_ID');
        $this->apiBase = rtrim(env('TELEGRAM_API_BASE', 'https://api.telegram.org'), '/');
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
     * Send raw markdown message to Telegram.
     *
     * @param string $chatId
     * @param string $text
     * @param array|null $keyboard
     * @return array|bool
     */
    public function sendMessage($chatId, $text, $keyboard = null)
    {
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
                ->post("{$this->apiBase}/bot{$this->token}/sendMessage", $payload);
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
     * @return bool
     */
    public function answerCallbackQuery($callbackQueryId, $text = null)
    {
        $payload = [
            'callback_query_id' => $callbackQueryId,
        ];

        if ($text) {
            $payload['text'] = $text;
        }

        try {
            $response = Http::timeout(8)
                ->withoutVerifying()
                ->post("{$this->apiBase}/bot{$this->token}/answerCallbackQuery", $payload);
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
     * @return bool
     */
    public function editMessageText($chatId, $messageId, $text)
    {
        $payload = [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => 'Markdown',
        ];

        try {
            $response = Http::timeout(8)
                ->withoutVerifying()
                ->post("{$this->apiBase}/bot{$this->token}/editMessageText", $payload);
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
