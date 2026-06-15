<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Setting;
use Illuminate\Support\Facades\Log;

class OrderkuotaService
{
    /**
     * Send order to Orderkuota API (mocked logging implementation).
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

        // Ambil data nomor HP tujuan pembeli (disimpan di email_or_whatsapp)
        $targetPhone = $order->email_or_whatsapp;

        // Ambil konfigurasi API Orderkuota dari database
        $memberId = Setting::get('orderkuota_member_id', '');
        $apiKey = Setting::get('orderkuota_api_key', '');
        $mode = Setting::get('orderkuota_mode', 'sandbox');

        // Lakukan logging data sesuai spesifikasi tugas
        Log::info("Pesanan ID {$orderId} siap ditembak ke Orderkuota dengan kode produk {$code}");
        
        // Log tambahan informasi untuk pembuktian data terambil dengan benar
        Log::info("Detail API Orderkuota - Member ID: {$memberId}, Token/Key: " . ($apiKey ? 'TERSEDIA' : 'KOSONG') . ", Mode: {$mode}, Nomor HP Tujuan: {$targetPhone}");
    }
}
