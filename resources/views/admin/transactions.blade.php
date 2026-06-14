@extends('layouts.app', ['title' => 'Daftar Transaksi'])

@section('content')
<div class="space-y-10">
    
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-5">
        <div>
            <h2 class="text-3xl font-extrabold text-slate-855 dark:text-slate-100 tracking-tight">Daftar Transaksi</h2>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Riwayat pesanan pelanggan dan log mutasi masuk</p>
        </div>
        
        <!-- Filters -->
        <div class="mt-4 md:mt-0 flex flex-wrap gap-2">
            <a href="{{ route('admin.transactions') }}" class="px-4 py-2 rounded-xl text-xs font-bold transition-all duration-200 {{ !$status ? 'bg-blue-600 text-white shadow-md shadow-blue-500/10' : 'bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-400 hover:bg-slate-50' }}">
                Semua
            </a>
            <a href="{{ route('admin.transactions', ['status' => 'pending']) }}" class="px-4 py-2 rounded-xl text-xs font-bold transition-all duration-200 {{ $status === 'pending' ? 'bg-blue-600 text-white shadow-md shadow-blue-500/10' : 'bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-400 hover:bg-slate-50' }}">
                Pending
            </a>
            <a href="{{ route('admin.transactions', ['status' => 'success']) }}" class="px-4 py-2 rounded-xl text-xs font-bold transition-all duration-200 {{ $status === 'success' ? 'bg-blue-600 text-white shadow-md shadow-blue-500/10' : 'bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-400 hover:bg-slate-50' }}">
                Sukses
            </a>
            <a href="{{ route('admin.transactions', ['status' => 'expired']) }}" class="px-4 py-2 rounded-xl text-xs font-bold transition-all duration-200 {{ $status === 'expired' ? 'bg-blue-600 text-white shadow-md shadow-blue-500/10' : 'bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-400 hover:bg-slate-50' }}">
                Expired
            </a>
        </div>
    </div>

    <!-- Orders/Transactions Table -->
    <div class="bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 rounded-3xl shadow-sm overflow-hidden">
        <div class="p-6 border-b border-slate-100 dark:border-slate-800">
            <h3 class="font-extrabold text-slate-800 dark:text-slate-200">Riwayat Pesanan</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 dark:bg-slate-900/60 border-b border-slate-100 dark:border-slate-800 text-xs font-bold text-slate-450 dark:text-slate-500 uppercase tracking-wider">
                        <th class="py-4.5 px-6">Order ID</th>
                        <th class="py-4.5 px-6">Produk</th>
                        <th class="py-4.5 px-6">Email / WhatsApp</th>
                        <th class="py-4.5 px-6">Harga Dasar</th>
                        <th class="py-4.5 px-6 text-center">Kode Unik</th>
                        <th class="py-4.5 px-6">Total Tagihan</th>
                        <th class="py-4.5 px-6">Status</th>
                        <th class="py-4.5 px-6">Dibuat Pada</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-sm font-medium text-slate-700 dark:text-slate-350">
                    @forelse($orders as $order)
                        <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/20 transition-all duration-150">
                            <td class="py-4.5 px-6 font-mono text-xs text-blue-600 dark:text-blue-400 font-bold">
                                {{ $order->id }}
                            </td>
                            <td class="py-4.5 px-6 font-bold text-slate-800 dark:text-slate-200">
                                {{ $order->product->name ?? 'Produk Dihapus' }}
                            </td>
                            <td class="py-4.5 px-6 text-xs">{{ $order->email_or_whatsapp }}</td>
                            <td class="py-4.5 px-6 text-slate-400">Rp {{ number_format($order->base_amount, 0, ',', '.') }}</td>
                            <td class="py-4.5 px-6 text-center text-xs font-mono text-amber-600 dark:text-amber-400 font-bold">
                                +{{ $order->unique_code }}
                            </td>
                            <td class="py-4.5 px-6 font-extrabold text-slate-800 dark:text-slate-100">
                                Rp {{ number_format($order->total_amount, 0, ',', '.') }}
                            </td>
                            <td class="py-4.5 px-6">
                                @if($order->status === 'success')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-50 dark:bg-emerald-950/30 text-emerald-700 dark:text-emerald-400">
                                        Sukses
                                    </span>
                                @elseif($order->status === 'pending')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-blue-50 dark:bg-blue-950/30 text-blue-700 dark:text-blue-400 animate-pulse">
                                        Pending
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-rose-50 dark:bg-rose-950/30 text-rose-700 dark:text-rose-400">
                                        Expired
                                    </span>
                                @endif
                            </td>
                            <td class="py-4.5 px-6 text-xs text-slate-400">{{ $order->created_at->format('d/m/Y H:i') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-10 text-center text-slate-400">
                                Tidak ada transaksi yang cocok dengan filter ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        <!-- Pagination links -->
        <div class="p-6 border-t border-slate-100 dark:border-slate-800">
            {{ $orders->appends(request()->query())->links() }}
        </div>
    </div>

    <!-- Payment Logs (Callbacks from Android App) -->
    <div class="bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 rounded-3xl shadow-sm overflow-hidden">
        <div class="p-6 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
            <div>
                <h3 class="font-extrabold text-slate-800 dark:text-slate-200">Log Callback Notifikasi Android</h3>
                <p class="text-xs text-slate-400 mt-0.5">Callback POST raw JSON dari pembaca notifikasi eksternal</p>
            </div>
            <span class="text-[10px] font-bold bg-slate-100 dark:bg-slate-800 px-3 py-1.5 rounded-xl text-slate-500 uppercase tracking-wider">Maks 20 Log Terakhir</span>
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 dark:bg-slate-900/60 border-b border-slate-100 dark:border-slate-800 text-xs font-bold text-slate-450 dark:text-slate-500 uppercase tracking-wider">
                        <th class="py-4.5 px-6">ID Log</th>
                        <th class="py-4.5 px-6">Nominal Diterima</th>
                        <th class="py-4.5 px-6">Teks Raw Notifikasi</th>
                        <th class="py-4.5 px-6">Kecocokan Pesanan</th>
                        <th class="py-4.5 px-6">Diterima Pada</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-sm font-medium text-slate-700 dark:text-slate-350">
                    @forelse($paymentLogs as $log)
                        <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/20 transition-all duration-150">
                            <td class="py-4.5 px-6 font-mono text-xs text-slate-450">#{{ $log->id }}</td>
                            <td class="py-4.5 px-6 text-emerald-600 font-extrabold">Rp {{ number_format($log->amount, 0, ',', '.') }}</td>
                            <td class="py-4.5 px-6">
                                <span class="text-xs font-mono font-medium max-w-sm block break-all text-slate-500 dark:text-slate-400">
                                    {{ $log->raw_text }}
                                </span>
                            </td>
                            <td class="py-4.5 px-6">
                                @if($log->matched_order_id)
                                    <span class="inline-flex items-center space-x-1 text-xs text-emerald-600 dark:text-emerald-400 font-bold bg-emerald-50 dark:bg-emerald-950/20 px-2 py-0.5 rounded-lg border border-emerald-100 dark:border-emerald-900/30">
                                        <span>Cocok:</span>
                                        <span class="font-mono">{{ $log->matched_order_id }}</span>
                                    </span>
                                @else
                                    <span class="inline-flex items-center text-xs text-slate-400 bg-slate-50 dark:bg-slate-800/30 px-2 py-0.5 rounded-lg border border-slate-100 dark:border-slate-800">
                                        Tidak Cocok
                                    </span>
                                @endif
                            </td>
                            <td class="py-4.5 px-6 text-xs text-slate-400">{{ $log->created_at->format('d/m/Y H:i:s') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-10 text-center text-slate-400">
                                Belum ada callback notifikasi masuk dari Android.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
