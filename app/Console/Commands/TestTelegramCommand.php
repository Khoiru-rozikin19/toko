<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\TelegramService;
use Illuminate\Support\Facades\Log;

class TestTelegramCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'telegram:test {chat_id?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test Telegram configuration and send a test message to the admin or a specific chat ID.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $token = env('TELEGRAM_BOT_TOKEN');
        $sellerToken = env('TELEGRAM_SELLER_BOT_TOKEN');
        $adminId = env('TELEGRAM_ADMIN_ID');
        $apiBase = env('TELEGRAM_API_BASE', 'https://api.telegram.org');

        $this->info("=== TELEGRAM CONFIGURATION TEST ===");
        $this->line("TELEGRAM_BOT_TOKEN (Admin Bot): " . ($token ? substr($token, 0, 5) . '...' . substr($token, -5) : '[NOT SET]'));
        $this->line("TELEGRAM_SELLER_BOT_TOKEN (Seller Bot): " . ($sellerToken ? substr($sellerToken, 0, 5) . '...' . substr($sellerToken, -5) : '[NOT SET]'));
        $this->line("TELEGRAM_ADMIN_ID: " . ($adminId ?: '[NOT SET]'));
        $this->line("TELEGRAM_API_BASE: " . $apiBase);
        $this->line("");

        $chatId = $this->argument('chat_id') ?: $adminId;

        if (empty($chatId)) {
            $this->error("Error: Target Chat ID is empty (neither argument nor TELEGRAM_ADMIN_ID is set).");
            return 1;
        }

        if (!is_numeric($chatId)) {
            if (str_starts_with($chatId, '@')) {
                $this->warn("Warning: Chat ID '{$chatId}' is a username. Telegram Bot API only supports sending private messages using numeric Chat IDs.");
            } else {
                $this->warn("Warning: Chat ID '{$chatId}' is not numeric.");
            }
        }

        $telegramService = app(TelegramService::class);

        // Test 1: Admin Bot
        if (!empty($token)) {
            $this->info("Testing Admin Bot (sending test message to Chat ID: {$chatId})...");
            $text = "🔔 *Test Bot Verifikasi Pembayaran*\n\nKoneksi Bot Admin berhasil dikonfigurasi dengan benar! 🎉";
            $response = $telegramService->sendMessage($chatId, $text, null, $token);

            if ($response && isset($response['ok']) && $response['ok']) {
                $this->info("-> ADMIN BOT: SUCCESS! Message sent.");
            } else {
                $this->error("-> ADMIN BOT: FAILED!");
                if ($response === false) {
                    $this->line("Check storage/logs/laravel.log for detailed error logs.");
                } else {
                    $this->line(json_encode($response));
                }
            }
        } else {
            $this->error("Error: TELEGRAM_BOT_TOKEN is empty in .env.");
        }

        // Test 2: Seller Bot (if configured and different from Admin Bot)
        if (!empty($sellerToken) && $sellerToken !== $token) {
            $this->line("");
            $this->info("Testing Seller Bot (sending test message to Chat ID: {$chatId})...");
            $text = "🔔 *Test Bot Notifikasi Seller*\n\nKoneksi Bot Seller berhasil dikonfigurasi dengan benar! 🎉";
            $response = $telegramService->sendMessage($chatId, $text, null, $sellerToken);

            if ($response && isset($response['ok']) && $response['ok']) {
                $this->info("-> SELLER BOT: SUCCESS! Message sent.");
            } else {
                $this->error("-> SELLER BOT: FAILED!");
                if ($response === false) {
                    $this->line("Check storage/logs/laravel.log for detailed error logs.");
                } else {
                    $this->line(json_encode($response));
                }
            }
        }

        return 0;
    }
}
