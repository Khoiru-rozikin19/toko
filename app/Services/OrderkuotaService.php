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

        // Susun data format wajib H2H OKEConnect: KODE.NOHP.PIN.R#INVOICE
        $message = "{$code}.{$targetPhone}.{$pin}.R#{$orderId}";

        // Lakukan logging data sesuai spesifikasi tugas
        Log::info("Pesanan ID {$orderId} siap ditembak ke Orderkuota dengan kode produk {$code}");
        Log::info("Detail API Orderkuota - Member ID: {$memberId}, Token/Key: " . ($apiKey ? 'TERSEDIA' : 'KOSONG') . ", Mode: {$mode}, Nomor HP Tujuan: {$targetPhone}");

        try {
            // Kirim request ke URL H2H OKEConnect menggunakan HTTP GET untuk menghindari proteksi CSRF
            $response = Http::get('https://h2h.okeconnect.com/trx', [
                'memberID' => $memberId,
                'pin' => $pin,
                'dest' => $targetPhone,
                'msg' => $message,
                'q' => $message,
            ]);

            Log::info("OKEConnect HTTP Request Sent (GET). Status: " . $response->status());
            Log::info("OKEConnect HTTP Response Body: " . $response->body());
        } catch (\Exception $e) {
            Log::error("OKEConnect HTTP Request Failed (GET): " . $e->getMessage());
        }
    }
}
