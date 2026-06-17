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
    public function index(Request $request)
    {
        $query = Product::query();

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        $products = $query->get();
        $categories = \App\Models\Category::orderBy('name', 'asc')->get();
        $qris_configured = !empty(Setting::get('qris_static_string'));

        return view('catalog', compact('products', 'categories', 'qris_configured'));
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
            'payment_method' => 'nullable|in:qris,balance',
        ];

        // Validasi Nomor HP Tujuan / ID Pelanggan secara dinamis untuk produk supplier (pulsa/kuota)
        if (!empty($product->orderkuota_product_code)) {
            $rules['target_phone'] = 'required|regex:/^[0-9]+$/|min:10|max:13';
        } else {
            $rules['target_phone'] = 'nullable|string|max:255';
        }

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()->first(),
                ], 422);
            }
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $paymentMethod = $request->payment_method ?? 'qris';

        if ($product->cekStok() <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Stok produk ini sedang habis.',
            ], 422);
        }

        // === BALANCE PAYMENT ===
        if ($paymentMethod === 'balance') {
            $user = auth()->user();
            $balanceRecord = $user->getOrCreateBalance();
            $price = $product->price;

            if ($balanceRecord->balance < $price) {
                return response()->json([
                    'success' => false,
                    'message' => 'Saldo tidak cukup. Saldo Anda: Rp ' . number_format($balanceRecord->balance, 0, ',', '.') . ', Harga: Rp ' . number_format($price, 0, ',', '.'),
                ], 422);
            }

            $orderId = 'ORD-' . strtoupper(Str::random(8));

            $order = \Illuminate\Support\Facades\DB::transaction(function () use ($orderId, $user, $product, $request, $price, $balanceRecord) {
                // Deduct balance
                $balanceBefore = $balanceRecord->balance;
                $balanceRecord->decrement('balance', $price);
                $balanceRecord->refresh();

                // Create balance transaction
                \App\Models\BalanceTransaction::create([
                    'user_id' => $user->id,
                    'type' => 'purchase',
                    'amount' => $price,
                    'balance_before' => $balanceBefore,
                    'balance_after' => $balanceRecord->balance,
                    'description' => 'Pembelian: ' . $product->name,
                    'reference_id' => $orderId,
                    'status' => 'success',
                ]);

                // Create the order (paid immediately)
                $order = Order::create([
                    'id' => $orderId,
                    'user_id' => $user->id,
                    'product_id' => $product->id,
                    'email_or_whatsapp' => $request->email_or_whatsapp,
                    'target_phone' => $request->target_phone,
                    'base_amount' => $price,
                    'unique_code' => 0,
                    'total_amount' => $price,
                    'status' => 'success',
                    'payment_method' => 'balance',
                    'qris_payload' => null,
                    'vpn_config' => $product->config_template,
                    'expired_at' => null,
                ]);

                // Assign local account stock if product uses dynamic stock
                if ($product->stocks()->where('status', 'ready')->exists()) {
                    $stock = \App\Models\AccountStock::where('product_id', $product->id)
                        ->where('status', 'ready')
                        ->first();

                    if ($stock) {
                        $stock->update([
                            'status' => 'sold',
                            'order_id' => $order->id,
                        ]);
                        $order->vpn_config = $stock->account_data;
                        $order->save();
                    }
                }

                // Run VPS account creation if product is linked to a VPS server
                if ($product->vps_server_id) {
                    app(\App\Services\VpsSshService::class)->createVpnAccount($order);
                    $order->save();
                }

                // Kirim pesanan ke Orderkuota jika applicable
                app(\App\Services\OrderkuotaService::class)->kirimPesananKeOrderkuota($order->id);

                // Decrement product stock if not unlimited
                if ($product->stock > 0) {
                    if (!$product->stocks()->where('status', 'ready')->exists()) {
                        $product->decrement('stock');
                    }
                }

                return $order;
            });

            return response()->json([
                'success' => true,
                'payment_method' => 'balance',
                'order' => [
                    'id' => $order->id,
                    'product_name' => $product->name,
                    'email_or_whatsapp' => $order->email_or_whatsapp,
                    'total_amount' => $order->total_amount,
                    'status' => 'success',
                    'vpn_config' => $order->vpn_config,
                    'success_instruction' => $product->success_instruction,
                ],
            ]);
        }

        // === QRIS PAYMENT (existing flow) ===
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
            'user_id' => auth()->id(),
            'product_id' => $product->id,
            'email_or_whatsapp' => $request->email_or_whatsapp,
            'target_phone' => $request->target_phone,
            'base_amount' => $product->price,
            'unique_code' => $uniqueCode,
            'total_amount' => $totalAmount,
            'status' => 'pending_manual',
            'payment_method' => 'qris',
            'qris_payload' => $qrisPayload,
            'vpn_config' => $product->config_template,
            'expired_at' => Carbon::now()->addMinutes(15),
        ]);

        // Kirim notifikasi ke Telegram Admin secara asynchronous via background queue
        \App\Jobs\SendTelegramNotificationJob::dispatch($order->id, $order->total_amount, $order->email_or_whatsapp);

        return response()->json([
            'success' => true,
            'payment_method' => 'qris',
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

        $response = [
            'status' => $order->status,
            'has_config' => !empty($order->vpn_config) && in_array($order->status, ['success', 'paid']),
        ];

        if (in_array($order->status, ['success', 'paid'])) {
            $response['vpn_config'] = $order->vpn_config;
            $response['success_instruction'] = $order->product ? $order->product->success_instruction : null;
        }

        return response()->json($response);
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

    /**
     * Show buyer's order history.
     */
    public function history()
    {
        $user = auth()->user();

        // Generate variations of email and phone to catch all matching orders
        $variations = [
            $user->email,
            strtolower($user->email),
        ];

        if (!empty($user->phone)) {
            $rawPhone = preg_replace('/[^0-9]/', '', $user->phone);
            if (!empty($rawPhone)) {
                $basePhone = $rawPhone;
                if (str_starts_with($rawPhone, '62')) {
                    $basePhone = substr($rawPhone, 2);
                } elseif (str_starts_with($rawPhone, '0')) {
                    $basePhone = substr($rawPhone, 1);
                }

                $variations[] = $user->phone;
                $variations[] = $rawPhone;
                $variations[] = '0' . $basePhone;
                $variations[] = '62' . $basePhone;
                $variations[] = '+' . '62' . $basePhone;
                $variations[] = $basePhone;
            }
        }

        // Clean unique variations
        $variations = array_unique(array_filter($variations));

        $orders = Order::with('product')
            ->whereIn('email_or_whatsapp', $variations)
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return view('orders.history', compact('orders'));
    }
}
