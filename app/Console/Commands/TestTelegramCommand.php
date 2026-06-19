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
        $adminId = env('TELEGRAM_ADMIN_ID');
        $apiBase = env('TELEGRAM_API_BASE', 'https://api.telegram.org');

        $this->info("=== TELEGRAM CONFIGURATION TEST ===");
        $this->line("TELEGRAM_BOT_TOKEN: " . ($token ? substr($token, 0, 5) . '...' . substr($token, -5) : '[NOT SET]'));
        $this->line("TELEGRAM_ADMIN_ID: " . ($adminId ?: '[NOT SET]'));
        $this->line("TELEGRAM_API_BASE: " . $apiBase);
        $this->line("");

        $chatId = $this->argument('chat_id') ?: $adminId;

        if (empty($token)) {
            $this->error("Error: TELEGRAM_BOT_TOKEN is empty in .env.");
            return 1;
        }

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

        $this->info("Sending test message to Chat ID: {$chatId}...");

        $telegramService = app(TelegramService::class);
        $text = "🔔 *Test Notifikasi Telegram*\n\n"
              . "Koneksi bot Anda berhasil dikonfigurasi dengan benar! 🎉";

        $response = $telegramService->sendMessage($chatId, $text);

        if ($response && isset($response['ok']) && $response['ok']) {
            $this->info("SUCCESS! Test message sent successfully.");
            $this->line("Message ID: " . ($response['result']['message_id'] ?? 'N/A'));
        } else {
            $this->error("FAILED! Telegram API returned an error.");
            if ($response === false) {
                $this->line("Check storage/logs/laravel.log for detailed error logs.");
            } else {
                $this->line(json_encode($response));
            }
        }

        return 0;
    }
}
