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
use Illuminate\Support\Facades\Storage;

class AdminController extends Controller
{
    /**
     * Display admin dashboard with stats and charts.
     */
    public function dashboard()
    {
        $user = auth()->user();
        $isAdmin = $user->role === 'admin';

        // Helper to query orders belonging to the user
        $orderQuery = function () use ($user) {
            return Order::whereHas('product', function ($pq) use ($user) {
                $pq->where('user_id', $user->id);
            });
        };

        // Helper to query products belonging to the user
        $productQuery = function () use ($user) {
            return Product::where('user_id', $user->id);
        };

        // 1. Wallets & Earnings (Stats matching screenshot 2)
        $totalRevenue = $orderQuery()->whereIn('status', ['success', 'paid', 'proses', 'sukses'])->sum('total_amount');
        
        // Saldo Dompet Saya (Profit/Wallet Balance) - Released or Non-escrow only
        $walletBalance = 0;
        
        // Profit dari penjualan produk sendiri yang sudah dirilis atau non-escrow
        $successfulOrders = $orderQuery()
            ->whereIn('status', ['success', 'paid', 'proses', 'sukses'])
            ->whereIn('escrow_status', ['released', 'none'])
            ->with('product')
            ->get();
        foreach ($successfulOrders as $order) {
            $product = $order->product;
            $modal = $product ? ($product->harga_modal ?? 0) : 0;
            $walletBalance += ($order->total_amount - $modal);
        }
 
        // Tambah komisi/cashback yang didapatkan dari pembelian produk
        $totalCommissions = Order::where('user_id', $user->id)
            ->whereIn('status', ['success', 'paid', 'proses', 'sukses'])
            ->sum('commission_earned');
        $walletBalance += $totalCommissions;

        // Deduct seller transfers to their user balance
        $totalTransferred = \App\Models\BalanceTransaction::where('user_id', $user->id)
            ->where('type', 'transfer_in')
            ->where('status', 'success')
            ->where('description', 'like', '%Transfer dari Dompet Seller%')
            ->sum('amount');
        $walletBalance -= $totalTransferred;

        // Saldo Tertahan (Held or Disputed)
        $heldBalance = $orderQuery()
            ->whereIn('escrow_status', ['held', 'disputed'])
            ->sum('escrow_amount');

        // Saldo Orderkuota (Admin only)
        $orderkuotaBalance = 0;
        if ($isAdmin) {
            $orderkuotaBalance = app(\App\Services\OrderkuotaService::class)->getSaldoOrderkuota();
        }

        $totalSalesCount = $orderQuery()->whereIn('status', ['success', 'paid', 'proses', 'sukses'])->count();
        $totalOrdersCount = $orderQuery()->count();

        $readyStockCount = 0;
        foreach ($productQuery()->get() as $p) {
            $readyStockCount += $p->stock;
        }

        // 2. Trend Pendapatan Harian (Last 7 Days)
        $chartLabels = [];
        $chartData = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            $chartLabels[] = $date->isoFormat('D MMM');
            
            $revenue = $orderQuery()->whereIn('status', ['success', 'paid', 'proses', 'sukses'])
                ->whereDate('created_at', $date->format('Y-m-d'))
                ->sum('total_amount');
            $chartData[] = $revenue;
        }

        // 3. Rasio Status Order (Donut Chart)
        $statusSuccess = $orderQuery()->whereIn('status', ['success', 'paid', 'proses', 'sukses'])->count();
        $statusPending = $orderQuery()->whereIn('status', ['pending', 'pending_manual'])->count();
        $statusExpired = $orderQuery()->where('status', 'expired')->count();

        $donutLabels = ['Sukses', 'Pending', 'Expired'];
        $donutData = [$statusSuccess, $statusPending, $statusExpired];

        return view('admin.dashboard', compact(
            'totalRevenue',
            'walletBalance',
            'heldBalance',
            'orderkuotaBalance',
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
     * Get real-time Orderkuota balance.
     */
    public function orderkuotaBalance()
    {
        if (auth()->user()->role !== 'admin') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $balance = app(\App\Services\OrderkuotaService::class)->getSaldoOrderkuota(true);

        return response()->json([
            'success' => is_numeric($balance),
            'balance' => $balance,
            'formatted_balance' => is_numeric($balance) ? 'Rp ' . number_format($balance, 0, ',', '.') : $balance
        ]);
    }

    /**
     * Transfer seller wallet balance to user account balance.
     */
    public function transferToBalance(Request $request)
    {
        $request->validate([
            'amount' => 'required|integer|min:1000',
        ]);

        $user = auth()->user();
        $amount = (int) $request->amount;

        try {
            DB::transaction(function () use ($user, $amount) {
                // Lock the user balance row
                $balanceRecord = \App\Models\UserBalance::where('user_id', $user->id)->lockForUpdate()->first();
                if (!$balanceRecord) {
                    $balanceRecord = $user->getOrCreateBalance();
                    $balanceRecord = \App\Models\UserBalance::where('user_id', $user->id)->lockForUpdate()->first();
                }

                // Calculate current seller wallet balance
                $orderQuery = Order::whereHas('product', function ($pq) use ($user) {
                    $pq->where('user_id', $user->id);
                });

                $walletBalance = 0;
                
                // Profit dari penjualan produk sendiri yang sudah dirilis atau non-escrow
                $successfulOrders = $orderQuery
                    ->whereIn('status', ['success', 'paid', 'proses', 'sukses'])
                    ->whereIn('escrow_status', ['released', 'none'])
                    ->with('product')
                    ->get();
                foreach ($successfulOrders as $order) {
                    $product = $order->product;
                    $modal = $product ? ($product->harga_modal ?? 0) : 0;
                    $walletBalance += ($order->total_amount - $modal);
                }

                // Tambah komisi/cashback yang didapatkan dari pembelian produk
                $totalCommissions = Order::where('user_id', $user->id)
                    ->whereIn('status', ['success', 'paid', 'proses', 'sukses'])
                    ->sum('commission_earned');
                $walletBalance += $totalCommissions;

                // Deduct already transferred amounts
                $totalTransferred = \App\Models\BalanceTransaction::where('user_id', $user->id)
                    ->where('type', 'transfer_in')
                    ->where('status', 'success')
                    ->where('description', 'like', '%Transfer dari Dompet Seller%')
                    ->sum('amount');

                $availableBalance = $walletBalance - $totalTransferred;

                if ($amount > $availableBalance) {
                    throw new \Exception('Saldo dompet seller tidak mencukupi untuk transfer nominal tersebut. Maksimal transfer: Rp ' . number_format($availableBalance, 0, ',', '.'));
                }

                $balanceBefore = $balanceRecord->balance;
                $balanceRecord->increment('balance', $amount);
                $balanceRecord->refresh();

                \App\Models\BalanceTransaction::create([
                    'user_id' => $user->id,
                    'type' => 'transfer_in',
                    'amount' => $amount,
                    'balance_before' => $balanceBefore,
                    'balance_after' => $balanceRecord->balance,
                    'description' => 'Transfer dari Dompet Seller',
                    'status' => 'success',
                ]);
            });
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Berhasil memindahkan saldo Rp ' . number_format($amount, 0, ',', '.') . ' ke saldo akun Anda.');
    }

    /**
     * Display products page (CRUD).
     */
    public function products()
    {
        $user = auth()->user();
        $categories = \App\Models\Category::orderBy('sort_order', 'asc')->orderBy('name', 'asc')->get();
        $vpsServers = \App\Models\VpsServer::orderBy('name', 'asc')->get();
        if ($user->role === 'admin') {
            $products = Product::with(['seller', 'category', 'vpsServer', 'stocks'])->orderBy('created_at', 'desc')->get();
            $sellers = User::whereIn('role', ['seller', 'admin'])->where('is_verified', true)->orderBy('name', 'asc')->get();
        } else {
            $products = Product::with(['category', 'vpsServer', 'stocks'])->where('user_id', $user->id)->orderBy('created_at', 'desc')->get();
            $sellers = collect();
        }
        return view('admin.products', compact('products', 'sellers', 'categories', 'vpsServers'));
    }

    /**
     * Store new product.
     */
    public function storeProduct(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'category_id' => 'nullable|exists:categories,id',
            'vps_server_id' => 'nullable|exists:vps_servers,id',
            'vps_command_template' => 'nullable|string|max:1000',
            'description' => 'nullable|string',
            'price' => 'required|integer|min:0',
            'harga_modal' => 'nullable|integer|min:0',
            'duration_days' => auth()->user()->role === 'admin' ? 'required|integer|min:1' : 'nullable|integer|min:1',
            'config_template' => 'nullable|string',
            'stock' => 'nullable|integer|min:0',
            'accounts_input' => 'nullable|string',
            'orderkuota_product_code' => 'nullable|string|max:50',
            'success_instruction' => 'nullable|string',
            'user_id' => 'nullable|exists:users,id',
            'visibility' => 'nullable|in:all,admin_seller,admin_only',
        ]);

        DB::transaction(function () use ($request) {
            $data = $request->all();
            if (auth()->user()->role !== 'admin' || empty($data['user_id'])) {
                $data['user_id'] = auth()->id();
            }

            // Restrict VPS automation settings to admin users only
            if (auth()->user()->role !== 'admin') {
                $data['vps_server_id'] = null;
                $data['vps_command_template'] = null;
                $data['duration_days'] = $data['duration_days'] ?? 30; // Default for seller if hidden
            }

            // Handle image upload
            if ($request->hasFile('image')) {
                $data['image_path'] = $request->file('image')->store('products', 'public');
            }

            // Parse accounts input if present
            $accounts = [];
            if ($request->filled('accounts_input')) {
                $accounts = preg_split('/\r?\n\s*\r?\n/', $request->accounts_input);
                $accounts = array_filter(array_map('trim', $accounts));
                $data['stock'] = count($accounts);
            } else {
                $data['stock'] = $data['stock'] ?? 0;
            }

            $product = Product::create($data);

            // Create account stocks
            foreach ($accounts as $accountData) {
                AccountStock::create([
                    'product_id' => $product->id,
                    'account_data' => $accountData,
                    'status' => 'ready',
                ]);
            }
        });

        return redirect()->route('admin.products')->with('success', 'Produk berhasil ditambahkan.');
    }

    /**
     * Update existing product.
     */
    public function updateProduct(Request $request, $id)
    {
        $product = Product::findOrFail($id);

        // Authorization check
        if (auth()->user()->role !== 'admin' && $product->user_id !== auth()->id()) {
            return redirect()->route('admin.products')->with('error', 'Anda tidak memiliki akses untuk memperbarui produk ini.');
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'category_id' => 'nullable|exists:categories,id',
            'vps_server_id' => 'nullable|exists:vps_servers,id',
            'vps_command_template' => 'nullable|string|max:1000',
            'description' => 'nullable|string',
            'price' => 'required|integer|min:0',
            'harga_modal' => 'nullable|integer|min:0',
            'duration_days' => 'required|integer|min:1',
            'config_template' => 'nullable|string',
            'stock' => 'nullable|integer|min:0',
            'accounts_input' => 'nullable|string',
            'orderkuota_product_code' => 'nullable|string|max:50',
            'success_instruction' => 'nullable|string',
            'user_id' => 'nullable|exists:users,id',
            'visibility' => 'nullable|in:all,admin_seller,admin_only',
        ]);

        DB::transaction(function () use ($product, $request) {
            $data = $request->all();
            if (auth()->user()->role !== 'admin') {
                unset($data['user_id']); // Seller cannot change the owner
                // Restrict VPS automation settings to admin users only (preserve existing config in DB by unsetting)
                unset($data['vps_server_id']);
                unset($data['vps_command_template']);
            }

            // Handle image upload
            if ($request->hasFile('image')) {
                if ($product->image_path) {
                    Storage::disk('public')->delete($product->image_path);
                }
                $data['image_path'] = $request->file('image')->store('products', 'public');
            }

            if ($request->has('accounts_input')) {
                $accounts = preg_split('/\r?\n\s*\r?\n/', $request->accounts_input);
                $accounts = array_filter(array_map('trim', $accounts));
                
                // Delete existing ready stocks
                $product->stocks()->where('status', 'ready')->delete();

                // Create new ready stocks
                foreach ($accounts as $accountData) {
                    AccountStock::create([
                        'product_id' => $product->id,
                        'account_data' => $accountData,
                        'status' => 'ready',
                    ]);
                }

                if (!empty($accounts)) {
                    $data['stock'] = count($accounts);
                } else {
                    $data['stock'] = $data['stock'] ?? 0;
                }
            } else {
                $data['stock'] = $data['stock'] ?? 0;
            }

            $product->update($data);
        });

        return redirect()->route('admin.products')->with('success', 'Produk berhasil diperbarui.');
    }

    /**
     * Delete product.
     */
    public function deleteProduct($id)
    {
        $product = Product::findOrFail($id);
        
        // Authorization check
        if (auth()->user()->role !== 'admin' && $product->user_id !== auth()->id()) {
            return redirect()->route('admin.products')->with('error', 'Anda tidak memiliki akses untuk menghapus produk ini.');
        }

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

        $query = Order::with('product.seller')->orderBy('created_at', 'desc');

        // Filter by seller if not admin
        if (auth()->user()->role !== 'admin') {
            $query->whereHas('product', function ($q) {
                $q->where('user_id', auth()->id());
            });
        }

        if ($status) {
            if ($status === 'pending') {
                $query->whereIn('status', ['pending', 'pending_manual']);
            } elseif ($status === 'success') {
                $query->whereIn('status', ['success', 'paid', 'sukses']);
            } elseif ($status === 'rejected') {
                $query->whereIn('status', ['rejected', 'ditolak']);
            } else {
                $query->where('status', $status);
            }
        }

        $orders = $query->paginate(15);

        // Scoped payment logs matching the products/orders
        $paymentLogsQuery = PaymentLog::with('order.product')->orderBy('created_at', 'desc');
        if (auth()->user()->role !== 'admin') {
            $paymentLogsQuery->whereHas('order.product', function ($q) {
                $q->where('user_id', auth()->id());
            });
        }
        $paymentLogs = $paymentLogsQuery->limit(20)->get();

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
        $priceListId = Setting::get('orderkuota_price_list_id', '905ccd028329b0a');

        return view('admin.supplier_settings', compact('memberId', 'apiKey', 'pin', 'mode', 'priceListId'));
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
            'orderkuota_price_list_id' => 'nullable|string|max:255',
        ]);

        Setting::set('orderkuota_member_id', $request->orderkuota_member_id);
        Setting::set('orderkuota_api_key', $request->orderkuota_api_key);
        Setting::set('orderkuota_pin', $request->orderkuota_pin);
        Setting::set('orderkuota_mode', $request->orderkuota_mode);
        Setting::set('orderkuota_price_list_id', $request->orderkuota_price_list_id);

        return redirect()->route('admin.supplier_settings')->with('success', 'Pengaturan API Supplier berhasil diperbarui.');
    }

    /**
     * Trigger manual Okeconnect product status synchronization.
     */
    public function syncOkeconnectProducts()
    {
        if (auth()->user()->role !== 'admin') {
            return redirect()->back()->with('error', 'Akses ditolak.');
        }

        $result = app(\App\Services\OrderkuotaService::class)->syncProductStatuses();

        if ($result['success']) {
            return redirect()->route('admin.products')->with('success', $result['message']);
        }

        return redirect()->route('admin.products')->with('error', $result['message']);
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
        // Get all products that belong to the logged-in seller, or all products if admin
        if (auth()->user()->role === 'admin') {
            $products = Product::orderBy('name', 'asc')->get();
        } else {
            $products = Product::where('user_id', auth()->id())->orderBy('name', 'asc')->get();
        }

        $productId = $request->query('product_id');
        $stocks = collect();

        if ($productId) {
            $product = Product::findOrFail($productId);

            // Authorization check
            if (auth()->user()->role !== 'admin' && $product->user_id !== auth()->id()) {
                return redirect()->route('admin.account_stocks')->with('error', 'Anda tidak memiliki akses untuk mengelola stok produk ini.');
            }

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

        // Authorization check
        if (auth()->user()->role !== 'admin' && $product->user_id !== auth()->id()) {
            return redirect()->route('admin.account_stocks')->with('error', 'Anda tidak memiliki akses untuk mengelola stok produk ini.');
        }

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

        $product = $stock->product;
        // Authorization check
        if (auth()->user()->role !== 'admin' && $product->user_id !== auth()->id()) {
            return redirect()->route('admin.account_stocks')->with('error', 'Anda tidak memiliki akses untuk mengelola stok produk ini.');
        }

        $productId = $stock->product_id;
        $stock->delete();

        return redirect()->route('admin.account_stocks', ['product_id' => $productId])
            ->with('success', 'Stok akun berhasil dihapus.');
    }

    /**
     * Store a new category via AJAX.
     */
    public function storeCategory(Request $request)
    {
        if (auth()->user()->role !== 'admin') {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'name' => 'required|string|max:50|unique:categories,name',
        ]);

        \App\Models\Category::create([
            'name' => $request->name,
        ]);

        $categories = \App\Models\Category::orderBy('sort_order', 'asc')->orderBy('name', 'asc')->get();

        return response()->json([
            'success' => true,
            'categories' => $categories,
        ]);
    }

    /**
     * Delete a category via AJAX.
     */
    public function deleteCategory($id)
    {
        if (auth()->user()->role !== 'admin') {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $category = \App\Models\Category::findOrFail($id);
        $category->delete();

        $categories = \App\Models\Category::orderBy('sort_order', 'asc')->orderBy('name', 'asc')->get();

        return response()->json([
            'success' => true,
            'categories' => $categories,
        ]);
    }

    /**
     * Reorder categories via AJAX.
     */
    public function reorderCategories(Request $request)
    {
        if (auth()->user()->role !== 'admin') {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:categories,id',
        ]);

        foreach ($request->ids as $index => $id) {
            \App\Models\Category::where('id', $id)->update(['sort_order' => $index]);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Display VPN Panel management page.
     */
    public function vpnPanel()
    {
        if (auth()->user()->role !== 'admin') {
            return abort(403);
        }
        $servers = \App\Models\VpsServer::orderBy('created_at', 'desc')->get();
        return view('admin.vpn_panel', compact('servers'));
    }

    /**
     * Store new VPS server settings.
     */
    public function storeVpnServer(Request $request)
    {
        if (auth()->user()->role !== 'admin') {
            return abort(403);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'ip_address' => 'required|string|max:255',
            'ssh_port' => 'required|integer|min:1|max:65535',
            'ssh_username' => 'required|string|max:255',
            'ssh_password' => 'nullable|string',
            'ssh_private_key' => 'nullable|string',
        ]);

        \App\Models\VpsServer::create($request->all());

        return redirect()->route('admin.vpn_panel')->with('success', 'Server VPS berhasil ditambahkan.');
    }

    /**
     * Update VPS server settings.
     */
    public function updateVpnServer(Request $request, $id)
    {
        if (auth()->user()->role !== 'admin') {
            return abort(403);
        }

        $server = \App\Models\VpsServer::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'ip_address' => 'required|string|max:255',
            'ssh_port' => 'required|integer|min:1|max:65535',
            'ssh_username' => 'required|string|max:255',
            'ssh_password' => 'nullable|string',
            'ssh_private_key' => 'nullable|string',
        ]);

        $server->update($request->all());

        return redirect()->route('admin.vpn_panel')->with('success', 'Server VPS berhasil diperbarui.');
    }

    /**
     * Delete VPS server settings.
     */
    public function deleteVpnServer($id)
    {
        if (auth()->user()->role !== 'admin') {
            return abort(403);
        }

        $server = \App\Models\VpsServer::findOrFail($id);
        $server->delete();

        return redirect()->route('admin.vpn_panel')->with('success', 'Server VPS berhasil dihapus.');
    }

    /**
     * Test VPS SSH connection via AJAX.
     */
    public function testVpnServerConnection(Request $request)
    {
        if (auth()->user()->role !== 'admin') {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'ip_address' => 'required|string',
            'ssh_port' => 'required|integer',
            'ssh_username' => 'required|string',
            'ssh_password' => 'nullable|string',
            'ssh_private_key' => 'nullable|string',
        ]);

        $service = app(\App\Services\VpsSshService::class);
        $result = $service->testConnection($request->only([
            'ip_address', 'ssh_port', 'ssh_username', 'ssh_password', 'ssh_private_key'
        ]));

        if ($result === true) {
            return response()->json(['success' => true, 'message' => 'Koneksi SSH berhasil terhubung ke server VPS!']);
        }

        return response()->json(['success' => false, 'message' => $result]);
    }

    // =====================================================
    //  KOMISI SELLER
    // =====================================================

    /**
     * Display commission management page.
     */
    public function commissions()
    {
        if (auth()->user()->role !== 'admin') {
            return abort(403);
        }

        $commissions = \App\Models\SellerCommission::with(['seller', 'product'])
            ->orderBy('created_at', 'desc')
            ->get();

        $sellers = User::whereIn('role', ['seller', 'admin'])
            ->where('is_verified', true)
            ->orderBy('name', 'asc')
            ->get();

        $products = Product::orderBy('name', 'asc')->get();

        return view('admin.commissions', compact('commissions', 'sellers', 'products'));
    }

    /**
     * Store a new commission rule.
     */
    public function storeCommission(Request $request)
    {
        if (auth()->user()->role !== 'admin') {
            return abort(403);
        }

        $request->validate([
            'seller_id' => 'required|exists:users,id',
            'product_id' => 'required|exists:products,id',
            'commission_amount' => 'required|integer|min:100',
        ]);

        // Cek apakah rule sudah ada
        $existing = \App\Models\SellerCommission::where('seller_id', $request->seller_id)
            ->where('product_id', $request->product_id)
            ->first();

        if ($existing) {
            return redirect()->route('admin.commissions')
                ->with('error', 'Aturan komisi untuk seller dan produk ini sudah ada. Silakan edit yang sudah ada.');
        }

        \App\Models\SellerCommission::create([
            'seller_id' => $request->seller_id,
            'product_id' => $request->product_id,
            'commission_amount' => $request->commission_amount,
            'is_active' => true,
        ]);

        return redirect()->route('admin.commissions')
            ->with('success', 'Aturan komisi berhasil ditambahkan.');
    }

    /**
     * Update commission amount.
     */
    public function updateCommission(Request $request, $id)
    {
        if (auth()->user()->role !== 'admin') {
            return abort(403);
        }

        $commission = \App\Models\SellerCommission::findOrFail($id);

        $request->validate([
            'commission_amount' => 'required|integer|min:100',
        ]);

        $commission->update([
            'commission_amount' => $request->commission_amount,
        ]);

        return redirect()->route('admin.commissions')
            ->with('success', 'Jumlah komisi berhasil diperbarui.');
    }

    /**
     * Delete a commission rule.
     */
    public function deleteCommission($id)
    {
        if (auth()->user()->role !== 'admin') {
            return abort(403);
        }

        $commission = \App\Models\SellerCommission::findOrFail($id);
        $commission->delete();

        return redirect()->route('admin.commissions')
            ->with('success', 'Aturan komisi berhasil dihapus.');
    }

    /**
     * Toggle commission active/inactive.
     */
    public function toggleCommission($id)
    {
        if (auth()->user()->role !== 'admin') {
            return abort(403);
        }

        $commission = \App\Models\SellerCommission::findOrFail($id);
        $commission->update([
            'is_active' => !$commission->is_active,
        ]);

        $statusText = $commission->is_active ? 'diaktifkan' : 'dinonaktifkan';
        return redirect()->route('admin.commissions')
            ->with('success', "Aturan komisi berhasil {$statusText}.");
    }

    /**
     * View complaints for seller or admin.
     */
    public function complaints()
    {
        $user = auth()->user();
        $isAdmin = $user->role === 'admin';

        $query = \App\Models\Complaint::with(['order.product', 'user']);

        if (!$isAdmin) {
            // Seller: only view complaints on their own products
            $query->whereHas('order.product', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            });
        }

        $complaints = $query->orderBy('created_at', 'desc')->paginate(15);
        $title = 'Kelola Komplain';

        return view('admin.complaints', compact('complaints', 'title'));
    }

    /**
     * Resolve (Approve) buyer complaint and refund money.
     */
    public function resolveComplaint(Request $request, $id)
    {
        $complaint = \App\Models\Complaint::findOrFail($id);
        $order = $complaint->order;
        $user = auth()->user();

        // Authorization check
        $isSeller = $order->product && $order->product->user_id === $user->id;
        if ($user->role !== 'admin' && !$isSeller) {
            return abort(403, 'Akses Ditolak.');
        }

        if ($complaint->status !== 'pending') {
            return redirect()->back()->with('error', 'Komplain sudah diproses sebelumnya.');
        }

        DB::transaction(function () use ($complaint, $order) {
            $complaint->update(['status' => 'resolved']);
            
            // Set order status to gagal
            $order->status = 'gagal';
            $order->save();

            // Deduct escrow amount from seller's held_balance if it was disputed or held
            if (in_array($order->escrow_status, ['held', 'disputed'])) {
                $sellerId = $order->product ? $order->product->user_id : null;
                if ($sellerId) {
                    $sellerBalance = \App\Models\UserBalance::where('user_id', $sellerId)->lockForUpdate()->first();
                    if ($sellerBalance) {
                        $newHeld = max(0, $sellerBalance->held_balance - $order->escrow_amount);
                        $sellerBalance->update(['held_balance' => $newHeld]);
                    }
                }
            }
            $order->escrow_status = 'none';
            $order->save();

            // Refund the buyer if paid via balance
            if ($order->payment_method === 'balance' && $order->user_id) {
                $buyer = \App\Models\User::find($order->user_id);
                if ($buyer) {
                    $buyerBalance = \App\Models\UserBalance::where('user_id', $buyer->id)->lockForUpdate()->first();
                    if (!$buyerBalance) {
                        $buyerBalance = $buyer->getOrCreateBalance();
                        $buyerBalance = \App\Models\UserBalance::where('user_id', $buyer->id)->lockForUpdate()->first();
                    }
                    $balanceBefore = $buyerBalance->balance;
                    $buyerBalance->increment('balance', $order->total_amount);
                    $buyerBalance->refresh();

                    // Record transaction log
                    \App\Models\BalanceTransaction::create([
                        'user_id' => $buyer->id,
                        'type' => 'topup',
                        'amount' => $order->total_amount,
                        'balance_before' => $balanceBefore,
                        'balance_after' => $buyerBalance->balance,
                        'description' => 'Refund komplain disetujui: ' . ($order->product->name ?? 'Produk'),
                        'reference_id' => $order->id,
                        'status' => 'success',
                    ]);
                }
            }
        });

        return redirect()->back()->with('success', 'Komplain berhasil disetujui. Dana telah dikembalikan ke pembeli.');
    }

    /**
     * Reject buyer complaint.
     */
    public function rejectComplaint(Request $request, $id)
    {
        $complaint = \App\Models\Complaint::findOrFail($id);
        $order = $complaint->order;
        $user = auth()->user();

        // Authorization check
        $isSeller = $order->product && $order->product->user_id === $user->id;
        if ($user->role !== 'admin' && !$isSeller) {
            return abort(403, 'Akses Ditolak.');
        }

        if ($complaint->status !== 'pending') {
            return redirect()->back()->with('error', 'Komplain sudah diproses sebelumnya.');
        }

        DB::transaction(function () use ($complaint, $order) {
            $complaint->update(['status' => 'rejected']);

            // Release escrow balance to seller active balance
            if (in_array($order->escrow_status, ['held', 'disputed'])) {
                $sellerId = $order->product ? $order->product->user_id : null;
                if ($sellerId) {
                    $sellerBalance = \App\Models\UserBalance::where('user_id', $sellerId)->lockForUpdate()->first();
                    if ($sellerBalance) {
                        $newHeld = max(0, $sellerBalance->held_balance - $order->escrow_amount);
                        $sellerBalance->update(['held_balance' => $newHeld]);
                    }
                }
            }
            $order->escrow_status = 'released';
            $order->escrow_released_at = now();

            // Set order status to proses if still success/paid
            if ($order->status === 'success' || $order->status === 'paid') {
                $order->status = 'proses';
            }

            $order->save();
        });

        return redirect()->back()->with('success', 'Komplain ditolak. Saldo penjualan telah dilepaskan ke dompet Anda.');
    }
}
