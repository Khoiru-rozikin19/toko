<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\Order;
use App\Models\PaymentLog;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    /**
     * Display admin dashboard with stats and charts.
     */
    public function dashboard()
    {
        // 1. Wallets & Earnings (Stats matching screenshot 2)
        $totalRevenue = Order::where('status', 'success')->sum('total_amount');
        
        // Sum of pending orders' amount (simulating "held balance" or "saldo tertahan")
        $pendingRevenue = Order::where('status', 'pending')
            ->where('expired_at', '>', Carbon::now())
            ->sum('total_amount');

        $totalSalesCount = Order::where('status', 'success')->count();
        $totalOrdersCount = Order::count();
        $readyStockCount = Product::sum('stock');

        // 2. Trend Pendapatan Harian (Last 7 Days)
        $chartLabels = [];
        $chartData = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            $chartLabels[] = $date->isoFormat('D MMM');
            
            $revenue = Order::where('status', 'success')
                ->whereDate('created_at', $date->format('Y-m-d'))
                ->sum('total_amount');
            $chartData[] = $revenue;
        }

        // 3. Rasio Status Order (Donut Chart)
        $statusSuccess = Order::where('status', 'success')->count();
        $statusPending = Order::where('status', 'pending')->count();
        $statusExpired = Order::where('status', 'expired')->count();

        $donutLabels = ['Sukses', 'Pending', 'Expired'];
        $donutData = [$statusSuccess, $statusPending, $statusExpired];

        return view('admin.dashboard', compact(
            'totalRevenue',
            'pendingRevenue',
            'totalSalesCount',
            'totalOrdersCount',
            'readyStockCount',
            'chartLabels',
            'chartData',
            'donutLabels',
            'donutData'
        ));
    }

    /**
     * Display products page (CRUD).
     */
    public function products()
    {
        $products = Product::orderBy('created_at', 'desc')->get();
        return view('admin.products', compact('products'));
    }

    /**
     * Store new product.
     */
    public function storeProduct(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|integer|min:0',
            'duration_days' => 'required|integer|min:1',
            'config_template' => 'nullable|string',
            'stock' => 'required|integer|min:0',
        ]);

        Product::create($request->all());

        return redirect()->route('admin.products')->with('success', 'Produk berhasil ditambahkan.');
    }

    /**
     * Update existing product.
     */
    public function updateProduct(Request $request, $id)
    {
        $product = Product::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|integer|min:0',
            'duration_days' => 'required|integer|min:1',
            'config_template' => 'nullable|string',
            'stock' => 'required|integer|min:0',
        ]);

        $product->update($request->all());

        return redirect()->route('admin.products')->with('success', 'Produk berhasil diperbarui.');
    }

    /**
     * Delete product.
     */
    public function deleteProduct($id)
    {
        $product = Product::findOrFail($id);
        $product->delete();

        return redirect()->route('admin.products')->with('success', 'Produk berhasil dihapus.');
    }

    /**
     * View and filter orders and payment logs.
     */
    public function transactions(Request $request)
    {
        $status = $request->query('status');

        $query = Order::with('product')->orderBy('created_at', 'desc');

        if ($status) {
            $query->where('status', $status);
        }

        $orders = $query->paginate(15);
        $paymentLogs = PaymentLog::with('order')->orderBy('created_at', 'desc')->limit(20)->get();

        return view('admin.transactions', compact('orders', 'paymentLogs', 'status'));
    }

    /**
     * Display configurations (QRIS & webhook secret).
     */
    public function settings()
    {
        $qrisStaticString = Setting::get('qris_static_string', '');
        $apiSecretKey = Setting::get('api_secret_key', '');
        return view('admin.settings', compact('qrisStaticString', 'apiSecretKey'));
    }

    /**
     * Update configurations.
     */
    public function updateSettings(Request $request)
    {
        $request->validate([
            'qris_static_string' => 'nullable|string',
            'api_secret_key' => 'nullable|string|min:8',
        ]);

        Setting::set('qris_static_string', $request->qris_static_string);
        Setting::set('api_secret_key', $request->api_secret_key);

        return redirect()->route('admin.settings')->with('success', 'Pengaturan berhasil diperbarui.');
    }
}
