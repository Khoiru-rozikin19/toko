<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsappService
{
    protected $apiUrl;
    protected $enabled;
    protected $otpEnabled;
    protected $groupId;

    public function __construct()
    {
        $this->apiUrl = rtrim(Setting::get('whatsapp_bot_api_url', 'http://127.0.0.1:3000'), '/');
        $this->enabled = Setting::get('whatsapp_bot_enabled', '0') === '1';
        $this->otpEnabled = Setting::get('whatsapp_bot_otp_enabled', '0') === '1';
        $this->groupId = Setting::get('whatsapp_bot_group_id', '');
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function isOtpEnabled(): bool
    {
        return $this->enabled && $this->otpEnabled;
    }

    public function getGroupId(): ?string
    {
        return $this->groupId;
    }

    public function getApiUrl(): string
    {
        return $this->apiUrl;
    }

    /**
     * Send message to personal JID
     */
    public function sendGenericMessage($phone, $message): bool
    {
        if (!$this->enabled) {
            Log::info("WhatsappService: Bot is disabled. Skipping message to {$phone}.");
            return false;
        }

        try {
            $response = Http::timeout(8)
                ->withoutVerifying()
                ->post("{$this->apiUrl}/send-message", [
                    'phone' => $phone,
                    'message' => $message
                ]);

            if ($response->failed()) {
                Log::error("WhatsappService sendGenericMessage Failed: " . $response->body());
                if ($response->status() === 400 && str_contains($response->body(), 'tidak terdaftar')) {
                    throw new \Exception("Nomor WhatsApp tidak terdaftar atau tidak aktif.");
                }
                return false;
            }

            return $response->json('success', false);
        } catch (\Exception $e) {
            Log::error("WhatsappService sendGenericMessage Exception: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send message to WhatsApp Group
     */
    public function sendGroupMessage($message): bool
    {
        if (!$this->enabled || empty($this->groupId)) {
            Log::info("WhatsappService: Bot or Group ID is not configured. Skipping group message.");
            return false;
        }

        try {
            $response = Http::timeout(8)
                ->withoutVerifying()
                ->post("{$this->apiUrl}/send-group-message", [
                    'groupId' => $this->groupId,
                    'message' => $message
                ]);

            if ($response->failed()) {
                Log::error("WhatsappService sendGroupMessage Failed: " . $response->body());
                return false;
            }

            return $response->json('success', false);
        } catch (\Exception $e) {
            Log::error("WhatsappService sendGroupMessage Exception: " . $e->getMessage());
            return false;
        }
    }

    public function sendOtp($phone, $otpCode): bool
    {
        $templates = [
            "🔐 *KODE OTP RZK STORE*\n\nKode OTP verifikasi akun Anda adalah: *{$otpCode}*\nBerlaku selama 5 menit. Jangan bagikan kode ini kepada siapa pun demi keamanan akun Anda.",
            "🛡️ *VERIFIKASI RZK STORE*\n\nMasukkan kode keamanan *{$otpCode}* pada halaman verifikasi website untuk mengaktifkan akun Anda. Kode ini kedaluwarsa dalam 5 menit.",
            "🔑 *KODE KEAMANAN RZK STORE*\n\nBerikut adalah kode OTP Anda: *{$otpCode}*.\nSegera masukkan kode ini untuk melanjutkan proses registrasi. Tetap jaga kerahasiaan kode Anda.",
            "🌟 *AKTIVASI AKUN RZK STORE*\n\nTerima kasih telah mendaftar! Kode verifikasi Anda adalah: *{$otpCode}*.\nJangan berikan kode ini kepada petugas atau pihak lain."
        ];

        // Pilih template secara acak
        $template = $templates[array_rand($templates)];

        // Tambahkan pengenal unik (reference code & timestamp) di bagian bawah agar setiap pesan unik bagi server WhatsApp
        $refCode = strtoupper(substr(md5(uniqid()), 0, 4));
        $timestamp = date('H:i:s');
        $message = "{$template}\n\n_Ref: [{$refCode}] • Waktu: {$timestamp}_";
                 
        return $this->sendGenericMessage($phone, $message);
    }

    /**
     * Get Baileys connection status and QR code from gateway
     */
    public function getConnectionStatus(): array
    {
        try {
            $response = Http::timeout(5)
                ->withoutVerifying()
                ->get("{$this->apiUrl}/status");

            if ($response->successful()) {
                return [
                    'status' => $response->json('status', 'connecting'),
                    'qr' => $response->json('qr', null),
                    'online' => true
                ];
            }
        } catch (\Exception $e) {
            Log::warning("WhatsappService getConnectionStatus: Gateway is offline/unreachable.");
        }

        return [
            'status' => 'offline',
            'qr' => null,
            'online' => false
        ];
    }

    /**
     * Disconnect/logout the session on the gateway
     */
    public function disconnect(): bool
    {
        try {
            $response = Http::timeout(5)
                ->withoutVerifying()
                ->post("{$this->apiUrl}/disconnect");

            return $response->successful() && $response->json('success', false);
        } catch (\Exception $e) {
            Log::error("WhatsappService disconnect Exception: " . $e->getMessage());
            return false;
        }
    }
}
