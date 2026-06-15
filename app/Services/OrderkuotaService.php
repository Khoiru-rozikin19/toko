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
            Log::info("Pesanan ID {$orderId} siap ditembak ke API modern Orderkuota dengan kode produk {$code}");
            Log::info("Detail API - Member ID: {$memberId}, Token: " . ($apiKey ? 'TERSEDIA' : 'KOSONG') . ", Mode: {$mode}, Nomor HP Tujuan: {$targetPhone}");

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Accept'        => 'application/json',
            ])->post('https://api.orderkuota.com/v1/transaction', [
                'member_id'    => $memberId,
                'product_code' => $code,
                'target'       => $targetPhone,
                'ref_id'       => $orderId,
                'pin'          => $pin,
            ]);

            $responseBody = $response->body();
            Log::info("OKEConnect Modern API Sent. Status: " . $response->status());
            Log::info("OKEConnect Modern API Response: " . $responseBody);
        } catch (\Exception $e) {
            Log::error("OKEConnect Modern API Request Failed: " . $e->getMessage());
        }
    }
}
