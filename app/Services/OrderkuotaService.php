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

            // METODE SAPU JAGAT: Masukkan semua tebakan nama parameter sekaligus!
            $queryParams = http_build_query([
                // Variasi penamaan ID
                'id'       => $memberId,
                'uid'      => $memberId,
                'memberid' => $memberId,

                // Variasi penamaan Password
                'pass'     => $passwordH2H,
                'password' => $passwordH2H,
                'pin_ip'   => $passwordH2H, 

                // Variasi penamaan String Transaksi
                'trx'      => $perintahTeks,
                'msg'      => $perintahTeks,
                'sms'      => $perintahTeks,
                'pesan'    => $perintahTeks,
                'text'     => $perintahTeks,
                'format'   => $perintahTeks,
                'q'        => $perintahTeks
            ]);

            $urlTarget = "https://h2h.okeconnect.com/trx?" . $queryParams;

            Log::info("OKEConnect Shotgun Request: " . $urlTarget);

            // Eksekusi request menggunakan HTTP Client Laravel
            $response = Http::timeout(20)->get($urlTarget);

            Log::info("OKEConnect Shotgun Response: " . $response->body());
        } catch (\Exception $e) {
            Log::error("OKEConnect HTTP Request Failed (Shotgun URL): " . $e->getMessage());
        }
    }
}
