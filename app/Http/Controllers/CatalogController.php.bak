<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\Order;
use App\Models\Setting;
use App\Services\QrisService;
use App\Services\TelegramService;
use Illuminate\Support\Str;
use Carbon\Carbon;

class CatalogController extends Controller
{
    protected $telegramService;

    public function __construct(TelegramService $telegramService)
    {
        $this->telegramService = $telegramService;
    }
    /**
     * Show the product catalog.
     */
    public function index()
    {
        $products = Product::all();
        $qris_configured = !empty(Setting::get('qris_static_string'));
        return view('catalog', compact('products', 'qris_configured'));
    }

    /**
     * Create a new order (Beli).
     */
    public function buy(Request $request)
    {
        $product = Product::findOrFail($request->product_id);

        $rules = [
            'product_id' => 'required|exists:products,id',
            'email_or_whatsapp' => 'required|string|max:255',
        ];

        // Validasi Nomor HP Tujuan / ID Pelanggan secara dinamis untuk produk supplier (pulsa/kuota)
        if (!empty($product->orderkuota_product_code)) {
            $rules['target_phone'] = 'required|regex:/^[0-9]+$/|min:10|max:13';
        } else {
            $rules['target_phone'] = 'nullable|string|max:255';
        }

        $request->validate($rules);

        if ($product->stock <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Stok produk ini sedang habis.',
            ], 422);
        }

        // Get static QRIS
        $staticQris = Setting::get('qris_static_string');
        if (empty($staticQris)) {
            return response()->json([
                'success' => false,
                'message' => 'Metode pembayaran QRIS belum dikonfigurasi oleh admin.',
            ], 500);
        }

        // Generate a unique code (1 - 99) that does not clash with active pending orders for this product
        $usedCodes = Order::where('product_id', $product->id)
            ->whereIn('status', ['pending', 'pending_manual'])
            ->where('expired_at', '>', Carbon::now())
            ->pluck('unique_code')
            ->toArray();

        $availableCodes = array_diff(range(1, 99), $usedCodes);

        if (empty($availableCodes)) {
            return response()->json([
                'success' => false,
                'message' => 'Terlalu banyak pesanan pending. Silakan coba lagi beberapa saat lagi.',
            ], 422);
        }

        // Pick a random code
        $uniqueCode = $availableCodes[array_rand($availableCodes)];
        $totalAmount = $product->price + $uniqueCode;

        // Generate dynamic QRIS string
        $qrisPayload = QrisService::generateDynamicQris($staticQris, $totalAmount);

        // Generate clean Order ID
        $orderId = 'ORD-' . strtoupper(Str::random(8));

        // Create the order
        $order = Order::create([
            'id' => $orderId,
            'product_id' => $product->id,
            'email_or_whatsapp' => $request->email_or_whatsapp,
            'target_phone' => $request->target_phone,
            'base_amount' => $product->price,
            'unique_code' => $uniqueCode,
            'total_amount' => $totalAmount,
            'status' => 'pending_manual',
            'qris_payload' => $qrisPayload,
            'vpn_config' => $product->config_template,
            'expired_at' => Carbon::now()->addMinutes(15),
        ]);

        // Kirim notifikasi ke Telegram Admin
        $this->telegramService->sendOrderNotification($order->id, $order->total_amount, $order->email_or_whatsapp);

        return response()->json([
            'success' => true,
            'order' => [
                'id' => $order->id,
                'product_name' => $product->name,
                'email_or_whatsapp' => $order->email_or_whatsapp,
                'total_amount' => $order->total_amount,
                'qris_payload' => $order->qris_payload,
                'expired_at' => $order->expired_at->toIso8601String(),
                'server_time' => Carbon::now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Polling endpoint to check order status.
     */
    public function checkStatus($id)
    {
        $order = Order::findOrFail($id);

        // Auto expire if pending/pending_manual and past expiration time
        if (in_array($order->status, ['pending', 'pending_manual']) && $order->expired_at && $order->expired_at->isPast()) {
            $order->update(['status' => 'expired']);
        }

        return response()->json([
            'status' => $order->status,
            'has_config' => !empty($order->vpn_config) && in_array($order->status, ['success', 'paid']),
        ]);
    }

    /**
     * Download the VPN configuration file.
     */
    public function download($id)
    {
        $order = Order::findOrFail($id);

        if (!in_array($order->status, ['success', 'paid'])) {
            return abort(403, 'Akses ditolak. Pembayaran belum sukses.');
        }

        if (empty($order->vpn_config)) {
            return abort(404, 'File konfigurasi VPN tidak ditemukan.');
        }

        $filename = 'VPN-' . Str::slug($order->product->name) . '-' . $order->id . '.ovpn';

        return response($order->vpn_config)
            ->header('Content-Type', 'application/octet-stream')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }
}
