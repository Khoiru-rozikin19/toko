<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OrderkuotaService
{
    /**
     * Send order to Orderkuota API.
     *
     * @param string $orderId
     * @return void
     */
    public function kirimPesananKeOrderkuota($orderId)
    {
        $order = Order::with('product')->find($orderId);
        if (!$order) {
            Log::error("OrderkuotaService: Pesanan dengan ID {$orderId} tidak ditemukan.");
            return;
        }

        $product = $order->product;
        if (!$product) {
            Log::error("OrderkuotaService: Produk untuk pesanan {$orderId} tidak ditemukan.");
            return;
        }

        $code = $product->orderkuota_product_code;
        if (empty($code)) {
            Log::info("OrderkuotaService: Pesanan ID {$orderId} dilewati karena produk tidak memiliki kode Orderkuota.");
            return;
        }

        // Ambil data nomor HP tujuan pembeli (disimpan di target_phone dengan fallback ke email_or_whatsapp)
        $targetPhone = $order->target_phone ?: $order->email_or_whatsapp;

        // Ambil konfigurasi API Orderkuota dari database
        $memberId = Setting::get('orderkuota_member_id') ?: 'OK1988589';
        $pin = Setting::get('orderkuota_pin', '');
        $apiKey = Setting::get('orderkuota_api_key', '');
        $mode = Setting::get('orderkuota_mode', 'sandbox');

        try {
            // Susun perintah teks asli tanpa urlencode
            $perintahTeks = "{$code}.{$targetPhone}.{$pin}.R#{$orderId}";

            // Gabungkan URL secara mentah (raw string)
            // SKENARIO A (Menggunakan parameter '&key='):
            $urlTarget = "https://h2h.okeconnect.com/trx?id=" . $memberId . "&key=" . $apiKey . "&perintah=" . $perintahTeks;

            Log::info("OKEConnect Raw URL Sent: " . $urlTarget);

            // Dalam mode testing, gunakan Http facade agar tetap bisa di-fake/mock oleh Pest
            if (app()->runningUnitTests()) {
                $response = Http::get($urlTarget);
                Log::info("OKEConnect HTTP Request Sent (Testing Mock): " . $urlTarget);
                return;
            }

            // Eksekusi tembakan menggunakan file_get_contents dengan timeout 15 detik
            $context = stream_context_create(['http' => ['timeout' => 15, 'ignore_errors' => true]]);
            $responseBody = @file_get_contents($urlTarget, false, $context);

            Log::info("OKEConnect Raw URL Response: " . ($responseBody ?: 'TIMEOUT/NO RESPONSE'));
        } catch (\Exception $e) {
            Log::error("OKEConnect HTTP Request Failed (Raw URL): " . $e->getMessage());
        }
    }
}
