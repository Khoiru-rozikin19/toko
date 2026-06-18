@extends('layouts.app', ['title' => 'Manajemen Produk'])

@section('content')
<div class="space-y-6 sm:space-y-8">
    
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-5">
        <div>
            <h2 class="text-2xl sm:text-3xl font-extrabold text-slate-800 dark:text-slate-100 tracking-tight">Manajemen Produk</h2>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Kelola stok unit produk digital VPN Anda</p>
        </div>
        <button onclick="toggleCreateModal(true)" class="mt-4 sm:mt-0 flex items-center space-x-2 px-5 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-2xl text-sm font-bold shadow-lg shadow-blue-500/20 active:scale-95 transition-all duration-200">
            <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"></path></svg>
            <span>Tambah Produk</span>
        </button>
    </div>

    <!-- Stats summary cards (dynamically updated by JS) -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 p-5 rounded-2xl shadow-sm">
            <span class="text-xs text-slate-400 dark:text-slate-500 font-bold uppercase block">Total Produk</span>
            <span id="statTotalProducts" class="text-2xl font-black text-slate-800 dark:text-slate-100 mt-1 block">{{ $products->count() }}</span>
        </div>
        <div class="bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 p-5 rounded-2xl shadow-sm">
            <span class="text-xs text-slate-400 dark:text-slate-500 font-bold uppercase block">Total Stok</span>
            <span id="statTotalStock" class="text-2xl font-black text-blue-600 dark:text-blue-400 mt-1 block">{{ $products->sum('stock') }}</span>
        </div>
    </div>

    <!-- Category Tabs (horizontal, like catalog) -->
    <div class="flex items-center space-x-2 overflow-x-auto pb-1 scrollbar-hide -mx-4 px-4 sm:mx-0 sm:px-0">
        <button onclick="filterByCategory('all')" id="catTab_all" class="cat-tab px-4 py-2 rounded-full text-xs font-bold transition-all duration-200 whitespace-nowrap bg-blue-600 text-white shadow-md shadow-blue-500/10">
            Semua
        </button>
        @foreach($categories as $category)
            <button onclick="filterByCategory('{{ $category->id }}')" id="catTab_{{ $category->id }}" class="cat-tab px-4 py-2 rounded-full text-xs font-bold transition-all duration-200 whitespace-nowrap bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-350 hover:bg-slate-200 dark:hover:bg-slate-700">
                {{ $category->name }}
            </button>
        @endforeach
        <button onclick="filterByCategory('uncategorized')" id="catTab_uncategorized" class="cat-tab px-4 py-2 rounded-full text-xs font-bold transition-all duration-200 whitespace-nowrap bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-350 hover:bg-slate-200 dark:hover:bg-slate-700">
            Tanpa Kategori
        </button>

        @if(auth()->user()->role === 'admin')
        <!-- Add Category Button -->
        <div class="relative flex-shrink-0" id="addCategoryContainer">
            <button onclick="toggleAddCategoryInline(event)" id="addCatBtn" class="flex items-center space-x-1 px-3 py-2 rounded-full text-xs font-bold transition-all duration-200 whitespace-nowrap border-2 border-dashed border-slate-300 dark:border-slate-700 text-slate-500 dark:text-slate-400 hover:border-blue-400 hover:text-blue-500 dark:hover:border-blue-600 dark:hover:text-blue-400">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"></path></svg>
                <span>+ Kategori</span>
            </button>
            <!-- Inline Add Category Form (hidden by default) -->
            <div id="addCategoryInline" class="hidden absolute top-full left-0 mt-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-xl p-4 z-30 w-64">
                <label class="block text-xs font-bold text-slate-500 uppercase mb-2">Kategori Baru</label>
                <div class="flex gap-2">
                    <input type="text" id="inlineCategoryName" placeholder="Nama Kategori..." class="flex-1 px-3 py-2 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 focus:border-blue-500 focus:outline-none rounded-xl text-xs font-medium text-slate-800 dark:text-slate-100 transition-all duration-200">
                    <button type="button" onclick="createCategoryInline()" class="px-3 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold transition-all duration-200">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"></path></svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- Delete Category Button -->
        <div class="relative flex-shrink-0" id="deleteCategoryContainer">
            <button onclick="toggleDeleteCategoryInline(event)" id="deleteCatBtn" class="flex items-center space-x-1 px-3 py-2 rounded-full text-xs font-bold transition-all duration-200 whitespace-nowrap border-2 border-dashed border-slate-300 dark:border-slate-700 text-slate-500 dark:text-slate-400 hover:border-rose-400 hover:text-rose-500 dark:hover:border-rose-600 dark:hover:text-rose-400">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M18 12H6"></path></svg>
                <span>- Kategori</span>
            </button>
            <!-- Inline Delete Category List (hidden by default) -->
            <div id="deleteCategoryInline" class="hidden absolute top-full right-0 mt-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-xl p-4 z-30 w-64 font-sans">
                <label class="block text-xs font-bold text-slate-500 uppercase mb-2">Hapus Kategori</label>
                @if($categories->count() > 0)
                <div class="space-y-1.5 max-h-48 overflow-y-auto">
                    @foreach($categories as $category)
                        <div class="flex items-center justify-between px-3 py-2 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-100 dark:border-slate-800/80">
                            <span class="text-xs font-semibold text-slate-700 dark:text-slate-300 truncate pr-2" title="{{ $category->name }}">
                                {{ $category->name }}
                            </span>
                            <button type="button" onclick="deleteCategory({{ $category->id }})" class="p-1 hover:bg-rose-50 dark:hover:bg-rose-950/30 text-slate-400 hover:text-rose-500 dark:text-slate-500 rounded-lg transition-all" title="Hapus Kategori">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                            </button>
                        </div>
                    @endforeach
                </div>
                @else
                <p class="text-xs text-slate-400 dark:text-slate-500">Belum ada kategori.</p>
                @endif
            </div>
        </div>
        @endif
    </div>

    <!-- Products Table Card -->
    <div class="bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 rounded-3xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 dark:bg-slate-900/60 border-b border-slate-100 dark:border-slate-800 text-xs font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">
                        <th class="py-4.5 px-6">ID</th>
                        <th class="py-4.5 px-6">Nama Produk</th>
                        @if(auth()->user()->role === 'admin')
                            <th class="py-4.5 px-6">Seller</th>
                        @endif
                        <th class="py-4.5 px-6">Harga</th>
                        <th class="py-4.5 px-6">Masa Aktif</th>
                        <th class="py-4.5 px-6">Stok</th>
                        <th class="py-4.5 px-6">Visibilitas</th>
                        <th class="py-4.5 px-6">Konfigurasi</th>
                        <th class="py-4.5 px-6 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody id="productsTableBody" class="divide-y divide-slate-100 dark:divide-slate-800 text-sm font-medium text-slate-700 dark:text-slate-350">
                    @forelse($products as $product)
                        <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/20 transition-all duration-150 product-row" data-category-id="{{ $product->category_id ?? 'uncategorized' }}" data-stock="{{ $product->stock }}">
                            <td class="py-4.5 px-6 font-mono text-xs text-slate-400">#{{ $product->id }}</td>
                            <td class="py-4.5 px-6">
                                <span class="font-bold text-slate-800 dark:text-slate-200">{{ $product->name }}</span>
                                @if($product->description)
                                    <span class="block text-xs text-slate-500 dark:text-slate-400 mt-0.5 max-w-xs truncate" title="{{ $product->description }}">
                                        {{ $product->description }}
                                    </span>
                                @endif
                                @if($product->orderkuota_product_code)
                                    <span class="block text-xs text-slate-400 dark:text-slate-500 mt-0.5">
                                        Supplier Code: <code class="bg-slate-100 dark:bg-slate-800/80 px-1 py-0.5 rounded text-blue-600 dark:text-blue-400 font-mono font-semibold">{{ $product->orderkuota_product_code }}</code>
                                    </span>
                                @endif
                            </td>
                            @if(auth()->user()->role === 'admin')
                                <td class="py-4.5 px-6 text-slate-600 dark:text-slate-400 text-xs font-semibold">
                                    {{ $product->seller ? $product->seller->name : 'Admin Utama' }}
                                </td>
                            @endif
                            <td class="py-4.5 px-6 text-blue-600 dark:text-blue-400 font-bold">
                                Rp {{ number_format($product->price, 0, ',', '.') }}
                            </td>
                            <td class="py-4.5 px-6">
                                {{ $product->duration_days }} Hari
                            </td>
                            <td class="py-4.5 px-6">
                                @if($product->stock > 0)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 dark:bg-emerald-950/30 text-emerald-700 dark:text-emerald-400">
                                        {{ $product->stock }} ready
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-rose-50 dark:bg-rose-950/30 text-rose-700 dark:text-rose-400">
                                        Habis
                                    </span>
                                @endif
                            </td>
                            <td class="py-4.5 px-6">
                                @php
                                    $vis = $product->visibility ?? 'all';
                                    $visLabel = ['all' => 'Semua', 'admin_seller' => 'Admin & Seller', 'admin_only' => 'Admin'];
                                    $visColor = ['all' => 'bg-emerald-50 dark:bg-emerald-950/30 text-emerald-700 dark:text-emerald-400', 'admin_seller' => 'bg-amber-50 dark:bg-amber-950/30 text-amber-700 dark:text-amber-400', 'admin_only' => 'bg-rose-50 dark:bg-rose-950/30 text-rose-700 dark:text-rose-400'];
                                @endphp
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $visColor[$vis] ?? $visColor['all'] }}">
                                    {{ $visLabel[$vis] ?? 'Semua' }}
                                </span>
                            </td>
                            <td class="py-4.5 px-6">
                                @if($product->vpsServer)
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-xl text-xs font-semibold bg-emerald-50 dark:bg-emerald-950/30 text-emerald-700 dark:text-emerald-400 font-mono" title="{{ $product->vps_command_template }}">
                                        VPS: {{ $product->vpsServer->name }}
                                    </span>
                                @else
                                    <span class="text-xs text-slate-405 font-mono block max-w-xs truncate">
                                        {{ Str::limit($product->config_template, 30) ?: 'Belum diisi' }}
                                    </span>
                                @endif
                            </td>
                            <td class="py-4.5 px-6 text-center">
                                <div class="flex items-center justify-center space-x-2">
                                    <!-- Edit Button -->
                                    <button onclick="openEditModal({{ json_encode($product) }})" class="p-2 bg-slate-100 dark:bg-slate-800 hover:bg-blue-50 dark:hover:bg-blue-950/20 text-slate-600 hover:text-blue-600 dark:text-slate-400 dark:hover:text-blue-400 rounded-xl transition-all duration-200" title="Edit Produk">
                                        <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                    </button>

                                    <!-- Delete Form -->
                                    <form action="{{ route('admin.products.delete', $product->id) }}" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin menghapus produk ini? Semua data pesanan terkait akan terhapus.')" class="inline">
                                        @csrf
                                        <button type="submit" class="p-2 bg-slate-100 dark:bg-slate-800 hover:bg-rose-50 dark:hover:bg-rose-950/20 text-slate-600 hover:text-rose-600 dark:text-slate-400 dark:hover:text-rose-400 rounded-xl transition-all duration-200" title="Hapus Produk">
                                            <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr id="emptyRow">
                            <td colspan="9" class="py-10 text-center text-slate-400">
                                Tidak ada produk terdaftar. Tekan tombol "Tambah Produk" di atas untuk mendaftarkan paket VPN baru.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- CREATE PRODUCT MODAL -->
<div id="createProductModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm hidden transition-all duration-300">
    <div class="bg-white dark:bg-slate-950 rounded-3xl w-full max-w-lg border border-slate-100 dark:border-slate-800 p-5 sm:p-8 shadow-2xl relative max-h-[calc(100vh-2rem)] overflow-y-auto">
        <button onclick="toggleCreateModal(false)" class="absolute top-5 right-5 p-2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-900 transition-all duration-200">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
        </button>

        <h3 class="text-2xl font-extrabold text-slate-800 dark:text-slate-100 tracking-tight mb-6">Tambah Produk Baru</h3>

        <form action="{{ route('admin.products.store') }}" method="POST" class="space-y-4">
            @csrf
            <!-- Hidden field: auto-set to active category -->
            <input type="hidden" id="create_category_id" name="category_id" value="">

            <div>
                <label for="create_name" class="block text-xs font-bold text-slate-500 uppercase mb-2">Nama Produk</label>
                <input type="text" id="create_name" name="name" required placeholder="Contoh: Premium SG OpenVPN" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 focus:border-blue-500 focus:bg-white focus:outline-none rounded-2xl text-sm font-medium text-slate-800 dark:text-slate-100 transition-all duration-200">
            </div>

            <!-- Show active category info -->
            <div id="createCategoryInfo" class="flex items-center space-x-2 px-4 py-3 bg-blue-50 dark:bg-blue-950/20 border border-blue-100 dark:border-blue-900/50 rounded-2xl">
                <svg class="w-4 h-4 text-blue-500 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path></svg>
                <span class="text-xs font-semibold text-blue-700 dark:text-blue-400">Kategori: <span id="createCategoryName">Semua</span></span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="create_vps_server_id" class="block text-xs font-bold text-slate-500 uppercase mb-2">Otomatisasi VPS (Opsional)</label>
                    <select id="create_vps_server_id" name="vps_server_id" onchange="toggleVpsCommandInput('create')" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 focus:border-blue-500 focus:bg-white focus:outline-none rounded-2xl text-sm font-medium text-slate-800 dark:text-slate-100 transition-all duration-200">
                        <option value="">-- Tanpa Otomatisasi --</option>
                        @foreach($vpsServers as $server)
                            <option value="{{ $server->id }}">{{ $server->name }} ({{ $server->ip_address }})</option>
                        @endforeach
                    </select>
                </div>
                <div id="create_vps_command_container" class="hidden">
                    <label for="create_vps_command_template" class="block text-xs font-bold text-slate-500 uppercase mb-2">Template Perintah CLI</label>
                    <input type="text" id="create_vps_command_template" name="vps_command_template" placeholder="Contoh: user-add-xray {username} {duration}" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 focus:border-blue-500 focus:bg-white focus:outline-none rounded-2xl text-sm font-medium text-slate-800 dark:text-slate-100 transition-all duration-200">
                </div>
            </div>

            @if(auth()->user()->role === 'admin')
            <div>
                <label for="create_user_id" class="block text-xs font-bold text-slate-500 uppercase mb-2">Pemilik Produk (Seller)</label>
                <select id="create_user_id" name="user_id" required class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 focus:border-blue-500 focus:bg-white focus:outline-none rounded-2xl text-sm font-medium text-slate-800 dark:text-slate-100 transition-all duration-200">
                    @foreach($sellers as $seller)
                        <option value="{{ $seller->id }}" {{ $seller->id == auth()->id() ? 'selected' : '' }}>
                            {{ $seller->name }} ({{ ucfirst($seller->role) }})
                        </option>
                    @endforeach
                </select>
            </div>
            @endif

            @if(auth()->user()->role === 'admin')
            <div class="grid grid-cols-3 gap-4">
                <div>
                    <label for="create_price" class="block text-xs font-bold text-slate-500 uppercase mb-2">Harga Jual (Rp)</label>
                    <input type="number" id="create_price" name="price" required placeholder="15000" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 focus:border-blue-500 focus:bg-white focus:outline-none rounded-2xl text-sm font-medium text-slate-800 dark:text-slate-100 transition-all duration-200">
                </div>
                <div>
                    <label for="create_harga_modal" class="block text-xs font-bold text-slate-500 uppercase mb-2">Harga Modal (Rp)</label>
                    <input type="number" id="create_harga_modal" name="harga_modal" placeholder="10000" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 focus:border-blue-500 focus:bg-white focus:outline-none rounded-2xl text-sm font-medium text-slate-800 dark:text-slate-100 transition-all duration-200">
                </div>
                <div>
                    <label for="create_duration" class="block text-xs font-bold text-slate-500 uppercase mb-2">Masa Aktif (Hari)</label>
                    <input type="number" id="create_duration" name="duration_days" required placeholder="30" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 focus:border-blue-500 focus:bg-white focus:outline-none rounded-2xl text-sm font-medium text-slate-800 dark:text-slate-100 transition-all duration-200">
                </div>
            </div>
            @else
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="create_price" class="block text-xs font-bold text-slate-500 uppercase mb-2">Harga (Rp)</label>
                    <input type="number" id="create_price" name="price" required placeholder="15000" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 focus:border-blue-500 focus:bg-white focus:outline-none rounded-2xl text-sm font-medium text-slate-800 dark:text-slate-100 transition-all duration-200">
                </div>
                <div>
                    <label for="create_duration" class="block text-xs font-bold text-slate-500 uppercase mb-2">Masa Aktif (Hari)</label>
                    <input type="number" id="create_duration" name="duration_days" required placeholder="30" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 focus:border-blue-500 focus:bg-white focus:outline-none rounded-2xl text-sm font-medium text-slate-800 dark:text-slate-100 transition-all duration-200">
                </div>
            </div>
            @endif

            <div>
                <label for="create_stock" class="block text-xs font-bold text-slate-500 uppercase mb-2">Jumlah Stok (Numerik Statis)</label>
                <input type="number" id="create_stock" name="stock" placeholder="10" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 focus:border-blue-500 focus:bg-white focus:outline-none rounded-2xl text-sm font-medium text-slate-800 dark:text-slate-100 transition-all duration-200">
            </div>

            @if(auth()->user()->role === 'admin')
            <div>
                <label for="create_orderkuota_product_code" class="block text-xs font-bold text-slate-500 uppercase mb-2">Kode Produk Supplier (Orderkuota)</label>
                <input type="text" id="create_orderkuota_product_code" name="orderkuota_product_code" placeholder="Contoh: TSEL10, ML86 (Kosongkan jika bukan produk supplier)" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 focus:border-blue-500 focus:bg-white focus:outline-none rounded-2xl text-sm font-medium text-slate-800 dark:text-slate-100 transition-all duration-200">
            </div>
            @endif

            <div>
                <label for="create_description" class="block text-xs font-bold text-slate-500 uppercase mb-2">Deskripsi Produk</label>
                <textarea id="create_description" name="description" rows="3" placeholder="Deskripsi produk untuk katalog..." class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 focus:border-blue-500 focus:bg-white focus:outline-none rounded-2xl text-sm font-medium text-slate-800 dark:text-slate-100 transition-all duration-200"></textarea>
            </div>

            <div>
                <label for="create_success_instruction" class="block text-xs font-bold text-slate-500 uppercase mb-2">Instruksi Pembayaran Sukses (Kustom)</label>
                <textarea id="create_success_instruction" name="success_instruction" rows="3" placeholder="Contoh: Silakan tunggu 1-5 menit untuk pengisian otomatis. Hubungi admin..." class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 focus:border-blue-500 focus:bg-white focus:outline-none rounded-2xl text-sm font-medium text-slate-800 dark:text-slate-100 transition-all duration-200"></textarea>
            </div>

            <div>
                <label for="create_visibility" class="block text-xs font-bold text-slate-500 uppercase mb-2">Visibilitas Produk</label>
                <select id="create_visibility" name="visibility" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 focus:border-blue-500 focus:bg-white focus:outline-none rounded-2xl text-sm font-medium text-slate-800 dark:text-slate-100 transition-all duration-200">
                    <option value="all" selected>👥 Semua User</option>
                    <option value="admin_seller">🏪 Admin & Seller</option>
                    <option value="admin_only">🔒 Admin Only</option>
                </select>
                <p class="text-xs text-slate-400 dark:text-slate-500 mt-1.5">Tentukan siapa saja yang bisa melihat produk ini di katalog.</p>
            </div>

            <div class="flex justify-end space-x-2 pt-4">
                <button type="button" onclick="toggleCreateModal(false)" class="px-5 py-3 bg-slate-100 hover:bg-slate-200 dark:bg-slate-850 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-300 rounded-2xl text-sm font-semibold transition-all duration-200">
                    Batal
                </button>
                <button type="submit" class="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-2xl text-sm font-bold shadow-lg shadow-blue-500/20 transition-all duration-200">
                    Simpan Produk
                </button>
            </div>
        </form>
    </div>
</div>

<!-- EDIT PRODUCT MODAL -->
<div id="editProductModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm hidden transition-all duration-300">
    <div class="bg-white dark:bg-slate-950 rounded-3xl w-full max-w-lg border border-slate-100 dark:border-slate-800 p-5 sm:p-8 shadow-2xl relative max-h-[calc(100vh-2rem)] overflow-y-auto">
        <button onclick="toggleEditModal(false)" class="absolute top-5 right-5 p-2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-900 transition-all duration-200">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
        </button>

        <h3 class="text-2xl font-extrabold text-slate-800 dark:text-slate-100 tracking-tight mb-6">Ubah Produk</h3>

        <form id="editForm" action="" method="POST" class="space-y-4">
            @csrf
            <div>
                <label for="edit_name" class="block text-xs font-bold text-slate-500 uppercase mb-2">Nama Produk</label>
                <input type="text" id="edit_name" name="name" required class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 focus:border-blue-500 focus:bg-white focus:outline-none rounded-2xl text-sm font-medium text-slate-800 dark:text-slate-100 transition-all duration-200">
            </div>

            <div>
                <label for="edit_category_id" class="block text-xs font-bold text-slate-500 uppercase mb-2">Kategori</label>
                <select id="edit_category_id" name="category_id" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 focus:border-blue-500 focus:bg-white focus:outline-none rounded-2xl text-sm font-medium text-slate-800 dark:text-slate-100 transition-all duration-200">
                    <option value="">-- Tanpa Kategori --</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="edit_vps_server_id" class="block text-xs font-bold text-slate-500 uppercase mb-2">Otomatisasi VPS (Opsional)</label>
                    <select id="edit_vps_server_id" name="vps_server_id" onchange="toggleVpsCommandInput('edit')" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 focus:border-blue-500 focus:bg-white focus:outline-none rounded-2xl text-sm font-medium text-slate-800 dark:text-slate-100 transition-all duration-200">
                        <option value="">-- Tanpa Otomatisasi --</option>
                        @foreach($vpsServers as $server)
                            <option value="{{ $server->id }}">{{ $server->name }} ({{ $server->ip_address }})</option>
                        @endforeach
                    </select>
                </div>
                <div id="edit_vps_command_container" class="hidden">
                    <label for="edit_vps_command_template" class="block text-xs font-bold text-slate-500 uppercase mb-2">Template Perintah CLI</label>
                    <input type="text" id="edit_vps_command_template" name="vps_command_template" placeholder="Contoh: user-add-xray {username} {duration}" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 focus:border-blue-500 focus:bg-white focus:outline-none rounded-2xl text-sm font-medium text-slate-800 dark:text-slate-100 transition-all duration-200">
                </div>
            </div>

            @if(auth()->user()->role === 'admin')
            <div>
                <label for="edit_user_id" class="block text-xs font-bold text-slate-500 uppercase mb-2">Pemilik Produk (Seller)</label>
                <select id="edit_user_id" name="user_id" required class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 focus:border-blue-500 focus:bg-white focus:outline-none rounded-2xl text-sm font-medium text-slate-800 dark:text-slate-100 transition-all duration-200">
                    @foreach($sellers as $seller)
                        <option value="{{ $seller->id }}">
                            {{ $seller->name }} ({{ ucfirst($seller->role) }})
                        </option>
                    @endforeach
                </select>
            </div>
            @endif

            @if(auth()->user()->role === 'admin')
            <div class="grid grid-cols-3 gap-4">
                <div>
                    <label for="edit_price" class="block text-xs font-bold text-slate-500 uppercase mb-2">Harga Jual (Rp)</label>
                    <input type="number" id="edit_price" name="price" required class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 focus:border-blue-500 focus:bg-white focus:outline-none rounded-2xl text-sm font-medium text-slate-800 dark:text-slate-100 transition-all duration-200">
                </div>
                <div>
                    <label for="edit_harga_modal" class="block text-xs font-bold text-slate-500 uppercase mb-2">Harga Modal (Rp)</label>
                    <input type="number" id="edit_harga_modal" name="harga_modal" placeholder="10000" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 focus:border-blue-500 focus:bg-white focus:outline-none rounded-2xl text-sm font-medium text-slate-800 dark:text-slate-100 transition-all duration-200">
                </div>
                <div>
                    <label for="edit_duration" class="block text-xs font-bold text-slate-500 uppercase mb-2">Masa Aktif (Hari)</label>
                    <input type="number" id="edit_duration" name="duration_days" required class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 focus:border-blue-500 focus:bg-white focus:outline-none rounded-2xl text-sm font-medium text-slate-800 dark:text-slate-100 transition-all duration-200">
                </div>
            </div>
            @else
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="edit_price" class="block text-xs font-bold text-slate-500 uppercase mb-2">Harga (Rp)</label>
                    <input type="number" id="edit_price" name="price" required class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 focus:border-blue-500 focus:bg-white focus:outline-none rounded-2xl text-sm font-medium text-slate-800 dark:text-slate-100 transition-all duration-200">
                </div>
                <div>
                    <label for="edit_duration" class="block text-xs font-bold text-slate-500 uppercase mb-2">Masa Aktif (Hari)</label>
                    <input type="number" id="edit_duration" name="duration_days" required class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 focus:border-blue-500 focus:bg-white focus:outline-none rounded-2xl text-sm font-medium text-slate-800 dark:text-slate-100 transition-all duration-200">
                </div>
            </div>
            @endif

            <div>
                <label for="edit_stock" class="block text-xs font-bold text-slate-500 uppercase mb-2">Jumlah Stok (Numerik Statis)</label>
                <input type="number" id="edit_stock" name="stock" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 focus:border-blue-500 focus:bg-white focus:outline-none rounded-2xl text-sm font-medium text-slate-800 dark:text-slate-100 transition-all duration-200">
            </div>

            @if(auth()->user()->role === 'admin')
            <div>
                <label for="edit_orderkuota_product_code" class="block text-xs font-bold text-slate-500 uppercase mb-2">Kode Produk Supplier (Orderkuota)</label>
                <input type="text" id="edit_orderkuota_product_code" name="orderkuota_product_code" placeholder="Contoh: TSEL10, ML86" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 focus:border-blue-500 focus:bg-white focus:outline-none rounded-2xl text-sm font-medium text-slate-800 dark:text-slate-100 transition-all duration-200">
            </div>
            @endif

            <div>
                <label for="edit_description" class="block text-xs font-bold text-slate-500 uppercase mb-2">Deskripsi Produk</label>
                <textarea id="edit_description" name="description" rows="3" placeholder="Deskripsi produk untuk katalog..." class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 focus:border-blue-500 focus:bg-white focus:outline-none rounded-2xl text-sm font-medium text-slate-800 dark:text-slate-100 transition-all duration-200"></textarea>
            </div>

            <div>
                <label for="edit_success_instruction" class="block text-xs font-bold text-slate-500 uppercase mb-2">Instruksi Pembayaran Sukses (Kustom)</label>
                <textarea id="edit_success_instruction" name="success_instruction" rows="3" placeholder="Instruksi sukses untuk pelanggan..." class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 focus:border-blue-500 focus:bg-white focus:outline-none rounded-2xl text-sm font-medium text-slate-800 dark:text-slate-100 transition-all duration-200"></textarea>
            </div>

            <div>
                <label for="edit_visibility" class="block text-xs font-bold text-slate-500 uppercase mb-2">Visibilitas Produk</label>
                <select id="edit_visibility" name="visibility" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 focus:border-blue-500 focus:bg-white focus:outline-none rounded-2xl text-sm font-medium text-slate-800 dark:text-slate-100 transition-all duration-200">
                    <option value="all">👥 Semua User</option>
                    <option value="admin_seller">🏪 Admin & Seller</option>
                    <option value="admin_only">🔒 Admin Only</option>
                </select>
                <p class="text-xs text-slate-400 dark:text-slate-500 mt-1.5">Tentukan siapa saja yang bisa melihat produk ini di katalog.</p>
            </div>

            <div class="flex justify-end space-x-2 pt-4">
                <button type="button" onclick="toggleEditModal(false)" class="px-5 py-3 bg-slate-100 hover:bg-slate-200 dark:bg-slate-850 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-300 rounded-2xl text-sm font-semibold transition-all duration-200">
                    Batal
                </button>
                <button type="submit" class="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-2xl text-sm font-bold shadow-lg shadow-blue-500/20 transition-all duration-200">
                    Perbarui Produk
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    // Track active category for filtering and creating products
    let activeCategory = 'all';

    // Category name map (built from server data)
    const categoryNames = {
        'all': 'Semua',
        'uncategorized': 'Tanpa Kategori',
        @foreach($categories as $category)
            '{{ $category->id }}': '{{ addslashes($category->name) }}',
        @endforeach
    };

    function filterByCategory(categoryId) {
        activeCategory = categoryId;

        // Update tab styles
        document.querySelectorAll('.cat-tab').forEach(tab => {
            tab.classList.remove('bg-blue-600', 'text-white', 'shadow-md', 'shadow-blue-500/10');
            tab.classList.add('bg-slate-100', 'dark:bg-slate-800', 'text-slate-600', 'dark:text-slate-350', 'hover:bg-slate-200', 'dark:hover:bg-slate-700');
        });
        const activeTab = document.getElementById(`catTab_${categoryId}`);
        if (activeTab) {
            activeTab.classList.remove('bg-slate-100', 'dark:bg-slate-800', 'text-slate-600', 'dark:text-slate-350', 'hover:bg-slate-200', 'dark:hover:bg-slate-700');
            activeTab.classList.add('bg-blue-600', 'text-white', 'shadow-md', 'shadow-blue-500/10');
        }

        // Filter table rows
        const rows = document.querySelectorAll('.product-row');
        let visibleCount = 0;
        let visibleStock = 0;

        rows.forEach(row => {
            const rowCatId = row.dataset.categoryId;
            const rowStock = parseInt(row.dataset.stock) || 0;
            let show = false;

            if (categoryId === 'all') {
                show = true;
            } else if (categoryId === 'uncategorized') {
                show = (rowCatId === 'uncategorized' || rowCatId === '' || rowCatId === 'null');
            } else {
                show = (rowCatId === categoryId);
            }

            row.style.display = show ? '' : 'none';
            if (show) {
                visibleCount++;
                visibleStock += rowStock;
            }
        });

        // Update stats
        document.getElementById('statTotalProducts').textContent = visibleCount;
        document.getElementById('statTotalStock').textContent = visibleStock;

        // Handle empty state
        const emptyRow = document.getElementById('emptyRow');
        if (emptyRow) {
            emptyRow.style.display = visibleCount === 0 ? '' : 'none';
        }
    }

    // Modals
    function toggleCreateModal(show) {
        const modal = document.getElementById('createProductModal');
        if (show) {
            // Set category from active tab
            const catId = (activeCategory === 'all' || activeCategory === 'uncategorized') ? '' : activeCategory;
            document.getElementById('create_category_id').value = catId;
            const catName = categoryNames[activeCategory] || 'Semua';
            document.getElementById('createCategoryName').textContent = catName;

            modal.classList.remove('hidden');
        } else {
            modal.classList.add('hidden');
        }
    }

    function openEditModal(product) {
        document.getElementById('edit_name').value = product.name;
        document.getElementById('edit_price').value = product.price;
        document.getElementById('edit_duration').value = product.duration_days;
        document.getElementById('edit_stock').value = product.stock;
        document.getElementById('edit_category_id').value = product.category_id || '';
        document.getElementById('edit_vps_server_id').value = product.vps_server_id || '';
        document.getElementById('edit_vps_command_template').value = product.vps_command_template || '';
        toggleVpsCommandInput('edit');
        if (document.getElementById('edit_harga_modal')) {
            document.getElementById('edit_harga_modal').value = product.harga_modal || 0;
        }
        if (document.getElementById('edit_orderkuota_product_code')) {
            document.getElementById('edit_orderkuota_product_code').value = product.orderkuota_product_code || '';
        }
        document.getElementById('edit_description').value = product.description || '';
        document.getElementById('edit_success_instruction').value = product.success_instruction || '';
        document.getElementById('edit_visibility').value = product.visibility || 'all';
        if (document.getElementById('edit_user_id')) {
            document.getElementById('edit_user_id').value = product.user_id;
        }
        
        document.getElementById('editForm').action = `/admin/products/${product.id}/update`;
        toggleEditModal(true);
    }

    function toggleEditModal(show) {
        const modal = document.getElementById('editProductModal');
        if (show) {
            modal.classList.remove('hidden');
        } else {
            modal.classList.add('hidden');
        }
    }

    // Toggle VPS inputs
    function toggleVpsCommandInput(prefix) {
        const select = document.getElementById(`${prefix}_vps_server_id`);
        const container = document.getElementById(`${prefix}_vps_command_container`);
        const input = document.getElementById(`${prefix}_vps_command_template`);
        
        if (select && select.value !== '') {
            container.classList.remove('hidden');
            input.required = true;
        } else {
            container.classList.add('hidden');
            input.required = false;
            if (prefix === 'create') {
                input.value = '';
            }
        }
    }

    // Inline Add Category Popup
    function toggleAddCategoryInline(e) {
        if (e) e.stopPropagation();
        const addPopup = document.getElementById('addCategoryInline');
        const deletePopup = document.getElementById('deleteCategoryInline');
        
        if (addPopup) {
            addPopup.classList.toggle('hidden');
            // Auto-focus input when opened
            if (!addPopup.classList.contains('hidden')) {
                setTimeout(() => {
                    document.getElementById('inlineCategoryName')?.focus();
                }, 50);
            }
        }
        if (deletePopup) {
            deletePopup.classList.add('hidden');
        }
    }

    // Inline Delete Category Popup
    function toggleDeleteCategoryInline(e) {
        if (e) e.stopPropagation();
        const addPopup = document.getElementById('addCategoryInline');
        const deletePopup = document.getElementById('deleteCategoryInline');
        
        if (deletePopup) {
            deletePopup.classList.toggle('hidden');
        }
        if (addPopup) {
            addPopup.classList.add('hidden');
        }
    }

    // Close popups when clicking outside
    document.addEventListener('click', function(e) {
        const addContainer = document.getElementById('addCategoryContainer');
        const addPopup = document.getElementById('addCategoryInline');
        if (addContainer && addPopup && !addContainer.contains(e.target)) {
            addPopup.classList.add('hidden');
        }

        const deleteContainer = document.getElementById('deleteCategoryContainer');
        const deletePopup = document.getElementById('deleteCategoryInline');
        if (deleteContainer && deletePopup && !deleteContainer.contains(e.target)) {
            deletePopup.classList.add('hidden');
        }
    });

    function escapeHtml(text) {
        return text.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
    }

    function createCategoryInline() {
        const input = document.getElementById('inlineCategoryName');
        const name = input.value.trim();
        if (!name) return;
        
        fetch("{{ route('admin.categories.store') }}", {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({ name: name })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                input.value = '';
                // Reload to reflect new category tab
                window.location.reload();
            } else {
                alert(data.message || 'Gagal menambahkan kategori.');
            }
        })
        .catch(err => {
            console.error(err);
            alert('Terjadi kesalahan koneksi.');
        });
    }

    function deleteCategory(id) {
        if (!confirm('Apakah Anda yakin ingin menghapus kategori ini? Produk di kategori ini akan menjadi Tanpa Kategori.')) {
            return;
        }
        
        fetch(`/admin/categories/${id}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                window.location.reload();
            } else {
                alert(data.message || 'Gagal menghapus kategori.');
            }
        })
        .catch(err => {
            console.error(err);
            alert('Terjadi kesalahan koneksi.');
        });
    }

    // Submit inline category with Enter key
    document.getElementById('inlineCategoryName')?.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            createCategoryInline();
        }
    });
</script>
@endsection
