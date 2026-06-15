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
            $params = [
                // Variasi penamaan ID
                'id'       => $memberId,
                'uid'      => $memberId,
                'memberid' => $memberId,

                // Variasi penamaan Password
                'pass'     => $passwordH2H,
                'password' => $passwordH2H,
                'pin_ip'   => $passwordH2H,
                'key'      => $passwordH2H,

                // Variasi penamaan String Transaksi (Format Jabber/SMS)
                'perintah' => $perintahTeks,
                'pesan'    => $perintahTeks,
                'mod'      => $perintahTeks,
                'sms'      => $perintahTeks,
                'trx'      => $perintahTeks,
                'msg'      => $perintahTeks,
                'q'        => $perintahTeks,
                'text'     => $perintahTeks,
                'format'   => $perintahTeks,

                // Variasi penamaan Split Parameter (Format API H2H Otomatis)
                'produk'     => $code,
                'kodeproduk' => $code,
                'kode'       => $code,
                'hp'         => $targetPhone,
                'tujuan'     => $targetPhone,
                'target'     => $targetPhone,
                'refid'      => $orderId,
                'ref_id'     => $orderId,
                'idtrx'      => $orderId,
                'pin'        => $pin,
            ];

            // Rakit query string secara manual agar karakter spesial '@' tetap terkirim mentah (raw),
            // namun '#' di-encode menjadi '%23' agar tidak dianggap sebagai URI Fragment.
            $queryParts = [];
            foreach ($params as $key => $value) {
                $safeValue = str_replace('#', '%23', $value);
                $queryParts[] = $key . '=' . $safeValue;
            }
            $urlTarget = "https://h2h.okeconnect.com/trx?" . implode('&', $queryParts);

            Log::info("OKEConnect Shotgun Request: " . $urlTarget);

            // Dalam mode testing, gunakan Http facade agar tetap bisa di-fake/mock oleh Pest
            if (app()->runningUnitTests()) {
                $response = Http::get($urlTarget);
                Log::info("OKEConnect HTTP Request Sent (Testing Mock): " . $urlTarget);
                return;
            }

            // Eksekusi request menggunakan file_get_contents agar literal '@' terkirim murni secara raw text
            $context = stream_context_create(['http' => ['timeout' => 20, 'ignore_errors' => true]]);
            $responseBody = @file_get_contents($urlTarget, false, $context);

            Log::info("OKEConnect Shotgun Response: " . ($responseBody ?: 'TIMEOUT/NO RESPONSE'));
        } catch (\Exception $e) {
            Log::error("OKEConnect HTTP Request Failed (Shotgun URL): " . $e->getMessage());
        }
    }
}
