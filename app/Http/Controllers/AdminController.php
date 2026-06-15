<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\Order;
use App\Models\PaymentLog;
use App\Models\Setting;
use App\Models\User;
use App\Models\AccountStock;
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
        $totalRevenue = Order::whereIn('status', ['success', 'paid'])->sum('total_amount');
        
        // Sum of pending orders' amount (simulating "held balance" or "saldo tertahan")
        $pendingRevenue = Order::whereIn('status', ['pending', 'pending_manual'])
            ->where('expired_at', '>', Carbon::now())
            ->sum('total_amount');

        $totalSalesCount = Order::whereIn('status', ['success', 'paid'])->count();
        $totalOrdersCount = Order::count();
        $readyStockCount = Product::sum('stock');

        // 2. Trend Pendapatan Harian (Last 7 Days)
        $chartLabels = [];
        $chartData = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            $chartLabels[] = $date->isoFormat('D MMM');
            
            $revenue = Order::whereIn('status', ['success', 'paid'])
                ->whereDate('created_at', $date->format('Y-m-d'))
                ->sum('total_amount');
            $chartData[] = $revenue;
        }

        // 3. Rasio Status Order (Donut Chart)
        $statusSuccess = Order::whereIn('status', ['success', 'paid'])->count();
        $statusPending = Order::whereIn('status', ['pending', 'pending_manual'])->count();
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
            'description' => 'nullable|string',
            'price' => 'required|integer|min:0',
            'duration_days' => 'required|integer|min:1',
            'config_template' => 'nullable|string',
            'stock' => 'required|integer|min:0',
            'orderkuota_product_code' => 'nullable|string|max:50',
            'success_instruction' => 'nullable|string',
        ]);

        DB::transaction(function () use ($request) {
            Product::create($request->all());
        });

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
            'description' => 'nullable|string',
            'price' => 'required|integer|min:0',
            'duration_days' => 'required|integer|min:1',
            'config_template' => 'nullable|string',
            'stock' => 'required|integer|min:0',
            'orderkuota_product_code' => 'nullable|string|max:50',
            'success_instruction' => 'nullable|string',
        ]);

        DB::transaction(function () use ($product, $request) {
            $product->update($request->all());
        });

        return redirect()->route('admin.products')->with('success', 'Produk berhasil diperbarui.');
    }

    /**
     * Delete product.
     */
    public function deleteProduct($id)
    {
        $product = Product::findOrFail($id);
        
        DB::transaction(function () use ($product) {
            $product->delete();
        });

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
            if ($status === 'pending') {
                $query->whereIn('status', ['pending', 'pending_manual']);
            } else {
                $query->where('status', $status);
            }
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

    /**
     * Display supplier settings page.
     */
    public function supplierSettings()
    {
        $memberId = Setting::get('orderkuota_member_id', '');
        $apiKey = Setting::get('orderkuota_api_key', '');
        $pin = Setting::get('orderkuota_pin', '');
        $mode = Setting::get('orderkuota_mode', 'sandbox');

        return view('admin.supplier_settings', compact('memberId', 'apiKey', 'pin', 'mode'));
    }

    /**
     * Update supplier settings.
     */
    public function updateSupplierSettings(Request $request)
    {
        $request->validate([
            'orderkuota_member_id' => 'nullable|string|max:255',
            'orderkuota_api_key' => 'nullable|string|max:255',
            'orderkuota_pin' => 'nullable|string|max:10',
            'orderkuota_mode' => 'required|in:sandbox,production',
        ]);

        Setting::set('orderkuota_member_id', $request->orderkuota_member_id);
        Setting::set('orderkuota_api_key', $request->orderkuota_api_key);
        Setting::set('orderkuota_pin', $request->orderkuota_pin);
        Setting::set('orderkuota_mode', $request->orderkuota_mode);

        return redirect()->route('admin.supplier_settings')->with('success', 'Pengaturan API Supplier berhasil diperbarui.');
    }

    /**
     * Display User Management dashboard with 2 tabs.
     */
    public function userManagement()
    {
        // Tab 1: Pendaftar Baru yang belum disetujui (is_verified = 0)
        $newAccounts = User::where('is_verified', false)->orderBy('created_at', 'desc')->get();

        // Tab 2: Buyer yang mengajukan upgrade ke Seller (role = buyer & seller_request = pending)
        $sellerRequests = User::where('role', 'buyer')
                              ->where('seller_request', 'pending')
                              ->orderBy('updated_at', 'asc')
                              ->get();

        // Tab 3: Semua Pengguna (selain admin yang sedang login)
        $allUsers = User::where('id', '!=', auth()->id())->orderBy('name', 'asc')->get();

        return view('admin.users', compact('newAccounts', 'sellerRequests', 'allUsers'));
    }

    /**
     * Approve new registration account.
     */
    public function approveAccount($id)
    {
        $user = User::findOrFail($id);
        $user->update([
            'is_verified' => true,
        ]);

        return redirect()->route('admin.users')->with('success', "Akun {$user->name} berhasil disetujui dan diaktifkan.");
    }

    /**
     * Reject and delete new registration account.
     */
    public function rejectAccount($id)
    {
        $user = User::findOrFail($id);
        $name = $user->name;
        $user->delete();

        return redirect()->route('admin.users')->with('success', "Pendaftaran akun {$name} berhasil ditolak.");
    }

    /**
     * Approve seller upgrade request.
     */
    public function approveSeller($id)
    {
        $user = User::findOrFail($id);
        $user->update([
            'role' => 'seller',
            'seller_request' => 'approved',
        ]);

        return redirect()->route('admin.users')->with('success', "Akun {$user->name} berhasil disetujui sebagai Seller.");
    }

    /**
     * Reject seller upgrade request.
     */
    public function rejectSeller($id)
    {
        $user = User::findOrFail($id);
        $user->update([
            'seller_request' => 'rejected',
        ]);

        return redirect()->route('admin.users')->with('success', "Pengajuan Seller dari {$user->name} telah ditolak.");
    }

    /**
     * Update user role.
     */
    public function updateRole(Request $request, $id)
    {
        $user = User::findOrFail($id);
        
        $request->validate([
            'role' => 'required|in:admin,seller,buyer',
        ]);

        // Cegah admin mengubah rolenya sendiri jika kebetulan masuk ke sini
        if ($user->id === auth()->id()) {
            return redirect()->route('admin.users')->with('error', 'Anda tidak dapat mengubah peran Anda sendiri.');
        }

        $user->update([
            'role' => $request->role,
        ]);

        return redirect()->route('admin.users')->with('success', "Peran untuk user {$user->name} berhasil diubah menjadi " . ucfirst($request->role) . ".");
    }

    /**
     * Toggle status (Suspend / Aktifkan).
     */
    public function toggleStatus($id)
    {
        $user = User::findOrFail($id);

        // Cegah mensuspend diri sendiri
        if ($user->id === auth()->id()) {
            return redirect()->route('admin.users')->with('error', 'Anda tidak dapat mensuspend akun Anda sendiri.');
        }

        $newStatus = !$user->is_verified;
        $user->update([
            'is_verified' => $newStatus,
        ]);

        $statusText = $newStatus ? 'diaktifkan kembali' : 'ditangguhkan (suspend)';
        return redirect()->route('admin.users')->with('success', "Akun {$user->name} berhasil {$statusText}.");
    }

    /**
     * Delete user permanently.
     */
    public function deleteUser($id)
    {
        $user = User::findOrFail($id);

        // Cegah menghapus diri sendiri
        if ($user->id === auth()->id()) {
            return redirect()->route('admin.users')->with('error', 'Anda tidak dapat menghapus akun Anda sendiri.');
        }

        $name = $user->name;
        $user->delete();

        return redirect()->route('admin.users')->with('success', "Akun {$name} berhasil dihapus secara permanen.");
    }

    /**
     * Display Account Stocks management page.
     */
    public function accountStocks(Request $request)
    {
        // Get all products
        $products = Product::orderBy('name', 'asc')->get();

        $productId = $request->query('product_id');
        $stocks = collect();

        if ($productId) {
            $product = Product::findOrFail($productId);
            $stocks = AccountStock::where('product_id', $productId)
                ->orderBy('status', 'asc') // ready first
                ->orderBy('created_at', 'desc')
                ->paginate(15);
        }

        return view('admin.account_stocks', compact('products', 'productId', 'stocks'));
    }

    /**
     * Store new account configurations in bulk.
     */
    public function storeAccountStocks(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'accounts_input' => 'required|string',
        ]);

        $product = Product::findOrFail($request->product_id);
        $rawInput = $request->accounts_input;

        // Split inputs by blank lines (regex matches two or more newlines with optional spaces)
        $accounts = preg_split('/\r?\n\s*\r?\n/', $rawInput);

        $insertedCount = 0;
        foreach ($accounts as $account) {
            $trimmed = trim($account);
            if (!empty($trimmed)) {
                AccountStock::create([
                    'product_id' => $product->id,
                    'account_data' => $trimmed,
                    'status' => 'ready',
                ]);
                $insertedCount++;
            }
        }

        return redirect()->route('admin.account_stocks', ['product_id' => $product->id])
            ->with('success', "Berhasil menambahkan {$insertedCount} stok akun.");
    }

    /**
     * Delete an account stock.
     */
    public function deleteAccountStock($id)
    {
        $stock = AccountStock::findOrFail($id);

        if ($stock->status === 'sold') {
            return back()->with('error', 'Tidak dapat menghapus akun yang sudah terjual.');
        }

        $productId = $stock->product_id;
        $stock->delete();

        return redirect()->route('admin.account_stocks', ['product_id' => $productId])
            ->with('success', 'Stok akun berhasil dihapus.');
    }
}
