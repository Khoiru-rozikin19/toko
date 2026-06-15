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
        $passwordH2H = Setting::get('orderkuota_api_key') ?: '@jkn1234';
        $mode = Setting::get('orderkuota_mode', 'sandbox');

        try {
            // Susun teks perintah transaksinya (tanpa urlencode terlebih dahulu)
            $perintahTeks = "{$code}.{$targetPhone}.{$pin}.R#{$orderId}";

            // Rakit parameter menggunakan http_build_query agar URL aman
            $queryParams = http_build_query([
                'id'    => $memberId,
                'pass'  => $passwordH2H,
                'pesan' => $perintahTeks  // Parameter standar IRS untuk format Jabber
            ]);

            $urlTarget = "https://h2h.okeconnect.com/trx?" . $queryParams;

            Log::info("OKEConnect IRS Encoded Request: " . $urlTarget);

            // Gunakan Http::get Laravel biasa karena URL sudah di-encode dengan aman
            $response = Http::timeout(20)->get($urlTarget);
            $responseBody = $response->body();

            Log::info("OKEConnect IRS Encoded Response: " . $responseBody);
        } catch (\Exception $e) {
            Log::error("OKEConnect HTTP Request Failed (Encoded URL): " . $e->getMessage());
        }
    }
}
