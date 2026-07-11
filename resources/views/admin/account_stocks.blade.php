@extends('layouts.app', ['title' => 'Stok Akun'])

@section('content')
<div class="space-y-6 sm:space-y-8">
    
    <!-- Header -->
    <div class="border-b border-slate-200 dark:border-slate-800 pb-5">
        <p class="text-sm text-slate-500 dark:text-slate-400">Kelola data akun/konfigurasi unik untuk produk digital non-supplier</p>
    </div>

    <!-- Product Selection Dropdown -->
    <div class="bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 p-5 rounded-2xl shadow-sm">
        <label for="productSelect" class="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase mb-2">Pilih Produk:</label>
        <select id="productSelect" onchange="changeProduct(this.value)" class="w-full max-w-md px-4 py-3 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 focus:border-blue-500 focus:bg-white focus:outline-none rounded-2xl text-sm font-semibold text-slate-800 dark:text-slate-100 transition-all duration-200">
            <option value="">-- Silakan Pilih Produk --</option>
            @foreach($products as $prod)
                <option value="{{ $prod->id }}" {{ $productId == $prod->id ? 'selected' : '' }}>
                    {{ $prod->name }} ({{ $prod->stocks()->where('status', 'ready')->count() }} ready)
                </option>
            @endforeach
        </select>
    </div>

    @if($productId)
        @php
            $selectedProduct = $products->firstWhere('id', $productId);
            $totalCount = $selectedProduct->stocks()->count();
            $readyCount = $selectedProduct->stocks()->where('status', 'ready')->count();
            $soldCount = $selectedProduct->stocks()->where('status', 'sold')->count();
        @endphp

        <!-- Stats Summary -->
        <div class="grid grid-cols-3 gap-4 sm:gap-6">
            <div class="bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 p-5 rounded-2xl shadow-sm">
                <span class="text-[10px] sm:text-xs text-slate-400 dark:text-slate-500 font-bold uppercase block">Total Diinput</span>
                <span class="text-xl sm:text-2xl font-black text-slate-800 dark:text-slate-100 mt-1 block">{{ $totalCount }}</span>
            </div>
            <div class="bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 p-5 rounded-2xl shadow-sm">
                <span class="text-[10px] sm:text-xs text-slate-400 dark:text-slate-500 font-bold uppercase block">Stok Ready</span>
                <span class="text-xl sm:text-2xl font-black text-emerald-600 dark:text-emerald-400 mt-1 block">{{ $readyCount }}</span>
            </div>
            <div class="bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 p-5 rounded-2xl shadow-sm">
                <span class="text-[10px] sm:text-xs text-slate-400 dark:text-slate-500 font-bold uppercase block">Terjual</span>
                <span class="text-xl sm:text-2xl font-black text-blue-600 dark:text-blue-400 mt-1 block">{{ $soldCount }}</span>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 sm:gap-8 items-start">
            
            <!-- BULK UPLOAD FORM (Left/Top) -->
            <div class="bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 p-5 sm:p-6 rounded-3xl shadow-sm space-y-4">
                <h3 class="text-lg font-bold text-slate-800 dark:text-slate-200">Tambah Stok Akun</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    Masukkan satu atau beberapa akun di bawah ini. Untuk menambahkan banyak akun sekaligus, **pisahkan setiap akun dengan 1 baris kosong (jarak 1 baris)**.
                </p>

                <form action="{{ route('admin.account_stocks.store') }}" method="POST" class="space-y-4">
                    @csrf
                    <input type="hidden" name="product_id" value="{{ $productId }}">

                    <div>
                        <label for="accounts_input" class="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase mb-2">Teks Akun / Konfigurasi:</label>
                        <textarea id="accounts_input" name="accounts_input" rows="10" required placeholder="vmess://eyJhZGQiOiJzdXBwb3J5MSJ9...&#10;&#10;vmess://eyJhZGQiOiJzdXBwb3J5MiJ9..." class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 focus:border-blue-500 focus:bg-white focus:outline-none rounded-2xl text-xs font-mono text-slate-800 dark:text-slate-100 transition-all duration-200"></textarea>
                    </div>

                    <button type="submit" class="w-full flex items-center justify-center space-x-2 px-5 py-3.5 bg-blue-600 hover:bg-blue-700 text-white rounded-2xl text-sm font-bold shadow-lg shadow-blue-500/20 active:scale-95 transition-all duration-200">
                        <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"></path></svg>
                        <span>Simpan Stok Akun</span>
                    </button>
                </form>
            </div>

            <!-- STOCK LIST TABLE (Right/Bottom) -->
            <div class="bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 rounded-3xl shadow-sm overflow-hidden lg:col-span-2">
                <div class="px-6 py-4.5 border-b border-slate-100 dark:border-slate-800 flex justify-between items-center">
                    <h3 class="font-bold text-slate-800 dark:text-slate-250">Daftar Stok: {{ $selectedProduct->name }}</h3>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50 dark:bg-slate-900/60 border-b border-slate-100 dark:border-slate-800 text-xs font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">
                                <th class="py-4 px-6">No</th>
                                <th class="py-4 px-6">Data Akun/Config</th>
                                <th class="py-4 px-6">Status</th>
                                <th class="py-4 px-6">Terkait Order</th>
                                <th class="py-4 px-6 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-sm font-medium text-slate-700 dark:text-slate-350">
                            @forelse($stocks as $index => $stock)
                                <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/20 transition-all duration-150">
                                    <td class="py-4.5 px-6 font-mono text-xs text-slate-400">
                                        {{ $stocks->firstItem() + $index }}
                                    </td>
                                    <td class="py-4.5 px-6 max-w-xs truncate">
                                        <code class="text-xs text-slate-500 font-mono block select-all cursor-pointer" title="Klik untuk memblokir, salin: {{ $stock->account_data }}">
                                            {{ Str::limit($stock->account_data, 35) }}
                                        </code>
                                    </td>
                                    <td class="py-4.5 px-6">
                                        @if($stock->status === 'ready')
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-50 dark:bg-emerald-950/30 text-emerald-700 dark:text-emerald-400">
                                                Ready
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-blue-50 dark:bg-blue-950/30 text-blue-700 dark:text-blue-400">
                                                Sold
                                            </span>
                                        @endif
                                    </td>
                                    <td class="py-4.5 px-6 font-mono text-xs">
                                        @if($stock->order_id)
                                            <span class="text-slate-800 dark:text-slate-300 font-bold">{{ $stock->order_id }}</span>
                                        @else
                                            <span class="text-slate-400">-</span>
                                        @endif
                                    </td>
                                    <td class="py-4.5 px-6 text-center">
                                        @if($stock->status === 'ready')
                                            <form action="{{ route('admin.account_stocks.delete', $stock->id) }}" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin menghapus stok akun ini?')" class="inline">
                                                @csrf
                                                <button type="submit" class="p-2 bg-slate-100 dark:bg-slate-800 hover:bg-rose-50 dark:hover:bg-rose-950/20 text-slate-600 hover:text-rose-600 dark:text-slate-400 dark:hover:text-rose-400 rounded-xl transition-all duration-200" title="Hapus Stok">
                                                    <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                                </button>
                                            </form>
                                        @else
                                            <button disabled class="p-2 bg-slate-50 dark:bg-slate-900 text-slate-300 dark:text-slate-700 rounded-xl cursor-not-allowed" title="Sudah Terjual">
                                                <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="py-10 text-center text-slate-400">
                                        Stok kosong. Gunakan form di sebelah kiri untuk menambah stok akun/config untuk produk ini.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($stocks->count() > 0)
                    <div class="px-6 py-4.5 bg-slate-50 dark:bg-slate-900/60 border-t border-slate-100 dark:border-slate-800">
                        {{ $stocks->appends(['product_id' => $productId])->links() }}
                    </div>
                @endif
            </div>

        </div>
    @else
        <div class="bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 p-10 rounded-3xl text-center text-slate-400">
            <svg class="w-16 h-16 mx-auto text-slate-300 dark:text-slate-700 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"></path></svg>
            <h3 class="text-lg font-bold text-slate-600 dark:text-slate-400">Silakan Pilih Produk</h3>
            <p class="text-sm text-slate-400 dark:text-slate-500 mt-1">Pilih salah satu produk di atas untuk mulai mengelola stok akun/config.</p>
        </div>
    @endif

</div>

<script>
    function changeProduct(productId) {
        if (productId) {
            window.location.href = `{{ route('admin.account_stocks') }}?product_id=${productId}`;
        } else {
            window.location.href = `{{ route('admin.account_stocks') }}`;
        }
    }
</script>
@endsection
