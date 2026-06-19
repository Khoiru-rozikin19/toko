@extends('layouts.app')

@section('content')
<style>
    /* Custom scrollbar for VPN configurations */
    .custom-scrollbar::-webkit-scrollbar {
        width: 8px;
    }
    .custom-scrollbar::-webkit-scrollbar-track {
        background: transparent;
    }
    .custom-scrollbar::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 4px;
    }
    .custom-scrollbar::-webkit-scrollbar-thumb:hover {
        background: #94a3b8;
    }
    .dark .custom-scrollbar::-webkit-scrollbar-thumb {
        background: #475569;
    }
    .dark .custom-scrollbar::-webkit-scrollbar-thumb:hover {
        background: #64748b;
    }
</style>
<div class="space-y-8">
    
    <!-- Catalog Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-5">
        <div>
            <h2 class="text-2xl sm:text-3xl font-extrabold text-slate-800 dark:text-slate-100 tracking-tight">Katalog Produk</h2>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ $products->count() }} produk tersedia</p>
        </div>
        
        @if(!$qris_configured)
        <div class="mt-4 md:mt-0 p-3 bg-amber-50 dark:bg-amber-950/20 border border-amber-200 dark:border-amber-900/50 rounded-xl text-amber-800 dark:text-amber-400 text-xs flex items-center space-x-2">
            <svg class="w-5 h-5 text-amber-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
            <span><strong>Catatan:</strong> QRIS Statis belum dikonfigurasi oleh admin. Checkout tidak dapat diproses.</span>
        </div>
        @endif
    </div>
 
    <!-- Categories Filter -->
    <div class="flex items-center space-x-2 overflow-x-auto pb-3 scrollbar-hide -mx-4 px-4 sm:mx-0 sm:px-0">
        <a href="{{ route('catalog') }}" class="px-4 py-2 rounded-full text-xs font-bold transition-all duration-200 whitespace-nowrap {{ !request()->filled('category_id') ? 'bg-blue-600 text-white shadow-md shadow-blue-500/10' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-350 hover:bg-slate-200 dark:hover:bg-slate-700' }}">
            Semua
        </a>
        @foreach($categories as $category)
            <a href="{{ route('catalog', ['category_id' => $category->id]) }}" class="px-4 py-2 rounded-full text-xs font-bold transition-all duration-200 whitespace-nowrap {{ request('category_id') == $category->id ? 'bg-blue-600 text-white shadow-md shadow-blue-500/10' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-350 hover:bg-slate-200 dark:hover:bg-slate-700' }}">
                {{ $category->name }}
            </a>
        @endforeach
    </div>

    <!-- Product Grid -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-3 sm:gap-4">
        @forelse($products as $product)
            <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-100 dark:border-slate-800 p-3 sm:p-4 flex flex-col justify-between hover:shadow-lg hover:-translate-y-0.5 transition-all duration-300 shadow-sm">
                <div>
                    <!-- Badge Stock -->
                    <div class="flex items-center justify-between mb-2">
                        @if($product->stock > 0)
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-50 dark:bg-emerald-950/30 text-emerald-700 dark:text-emerald-400 space-x-1">
                                <span class="w-1 h-1 rounded-full bg-emerald-500"></span>
                                <span>{{ $product->stock }} stok</span>
                            </span>
                        @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-rose-50 dark:bg-rose-950/30 text-rose-700 dark:text-rose-400 space-x-1">
                                <span class="w-1 h-1 rounded-full bg-rose-500"></span>
                                <span>Habis</span>
                            </span>
                        @endif
                    </div>

                    <!-- Package Icon Container or Product Image -->
                    @if($product->image_path)
                        <div class="w-full h-20 sm:h-24 mb-3 rounded-xl overflow-hidden border border-slate-100 dark:border-slate-800">
                            <img src="{{ asset('storage/' . $product->image_path) }}" alt="{{ $product->name }}" class="w-full h-full object-cover">
                        </div>
                    @else
                        <div class="w-full h-20 sm:h-24 bg-blue-50 dark:bg-blue-950/20 rounded-xl flex items-center justify-center mb-3 border border-blue-100/50 dark:border-blue-950/50">
                            <svg class="w-8 h-8 sm:w-10 sm:h-10 text-blue-500/80" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                            </svg>
                        </div>
                    @endif

                    <!-- Title & Details -->
                    <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100 leading-snug mb-1 line-clamp-2">{{ $product->name }}</h3>
                    <div class="text-[10px] sm:text-[11px] text-slate-500 dark:text-slate-400 mb-2 line-clamp-2 leading-relaxed">
                        @if($product->description)
                            {!! $product->description !!}
                        @else
                            Masa aktif: <strong>{{ $product->duration_days }} hari</strong>
                        @endif
                    </div>

                    <div class="flex items-center space-x-1 text-[10px] text-slate-400 dark:text-slate-500 mb-3">
                        <svg class="w-3 h-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                        <span class="truncate">Seller: {{ $product->seller ? $product->seller->name : 'Admin Utama' }}</span>
                    </div>
                </div>

                <!-- Footer Card Info & Action -->
                <div class="flex items-center justify-between pt-2.5 border-t border-slate-100 dark:border-slate-800">
                    <span class="text-sm sm:text-base font-extrabold text-blue-600 dark:text-blue-400">Rp {{ number_format($product->price, 0, ',', '.') }}</span>
                    <div class="flex space-x-1.5">
                        <button data-description="{{ $product->description }}" onclick="openDetailModal('{{ addslashes($product->name) }}', '{{ $product->price }}', '{{ $product->duration_days }}', this.getAttribute('data-description'))" class="px-2 py-1.5 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 rounded-lg text-[10px] font-semibold hover:bg-slate-50 dark:hover:bg-slate-700 transition-all duration-200">
                            Detail
                        </button>
                        
                        @if($product->stock > 0 && $qris_configured)
                            @auth
                                <button onclick="openBuyModal({{ $product->id }}, '{{ $product->name }}', {{ $product->price }}, '{{ $product->orderkuota_product_code }}')" class="flex items-center space-x-1 px-2.5 py-1.5 bg-blue-600 hover:bg-blue-750 active:scale-95 text-white rounded-lg text-[10px] font-bold transition-all duration-200 shadow-sm shadow-blue-500/10">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                                    <span>Beli</span>
                                </button>
                            @else
                                <a href="{{ route('login') }}" class="flex items-center space-x-1 px-2.5 py-1.5 bg-blue-600 hover:bg-blue-750 active:scale-95 text-white rounded-lg text-[10px] font-bold transition-all duration-200 shadow-sm shadow-blue-500/10">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                                    <span>Beli</span>
                                </a>
                            @endauth
                        @else
                            <button disabled class="flex items-center space-x-1 px-2.5 py-1.5 bg-slate-100 dark:bg-slate-800 text-slate-400 dark:text-slate-600 rounded-lg text-[10px] font-bold cursor-not-allowed">
                                <span>Beli</span>
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="col-span-full py-12 text-center">
                <svg class="w-16 h-16 text-slate-300 dark:text-slate-700 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
                <h3 class="text-lg font-bold text-slate-600 dark:text-slate-400">Tidak ada produk tersedia</h3>
                <p class="text-sm text-slate-400 dark:text-slate-600 mt-1">Silakan tambahkan produk di dashboard admin.</p>
            </div>
        @endforelse
    </div>
</div>

<!-- PURCHASE MODAL -->
<div id="purchaseModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm hidden transition-all duration-300">
    <div class="bg-white dark:bg-slate-950 rounded-3xl w-full max-w-lg border border-slate-100 dark:border-slate-800 p-5 sm:p-8 shadow-2xl relative max-h-[calc(100vh-2rem)] overflow-y-auto">
        <!-- Close Button -->
        <button onclick="closeModal()" class="absolute top-5 right-5 p-2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-900 transition-all duration-200">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
        </button>

        <!-- Dynamic Modal States -->
        <div id="modalStepInput">
            <h3 class="text-2xl font-extrabold text-slate-800 dark:text-slate-100 leading-tight">Konfirmasi Pembelian</h3>
            <p id="modalProductTitle" class="text-sm text-blue-600 dark:text-blue-400 font-semibold mt-1">Nama Paket</p>
            
            <form id="purchaseForm" onsubmit="submitPurchase(event)" class="mt-6 space-y-5">
                @csrf
                <input type="hidden" id="formProductId" name="product_id">
                
                <div>
                    <label for="email_or_whatsapp" class="block text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-2">Email / WhatsApp Pembeli</label>
                    <input type="text" id="email_or_whatsapp" name="email_or_whatsapp" required value="{{ Auth::check() ? (Auth::user()->email ?: Auth::user()->phone) : '' }}" placeholder="Contoh: user@gmail.com atau 08123456789" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 focus:border-blue-500 focus:bg-white focus:outline-none rounded-2xl text-sm font-medium text-slate-800 dark:text-slate-100 transition-all duration-200">
                    <p class="text-[11px] text-slate-400 dark:text-slate-500 mt-1.5">File konfigurasi VPN Anda akan siap diunduh di halaman ini setelah pembayaran sukses.</p>
                </div>

                <div id="targetPhoneContainer" class="hidden">
                    <label for="target_phone" class="block text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-2">Nomor HP Tujuan / ID Pelanggan</label>
                    <input type="text" id="target_phone" name="target_phone" placeholder="Contoh: 081234567890" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 focus:border-blue-500 focus:bg-white focus:outline-none rounded-2xl text-sm font-medium text-slate-800 dark:text-slate-100 transition-all duration-200">
                    <p class="text-[11px] text-slate-400 dark:text-slate-500 mt-1.5">Nomor HP ini akan diisi pulsa/kuota otomatis oleh supplier setelah pembayaran sukses.</p>
                </div>

                @auth
                <div>
                    <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-2">Metode Pembayaran</label>
                    <div class="grid grid-cols-2 gap-3">
                        <label class="relative flex flex-col p-4 bg-slate-50 dark:bg-slate-900 border-2 border-blue-600 rounded-2xl cursor-pointer hover:bg-slate-100/50 dark:hover:bg-slate-900/50 transition" id="payMethodQrisLabel">
                            <input type="radio" name="payment_method" value="qris" checked onchange="togglePaymentMethod('qris')" class="absolute top-4 right-4 text-blue-600 focus:ring-blue-500">
                            <span class="font-bold text-sm text-slate-850 dark:text-slate-200">QRIS</span>
                            <span class="text-[10px] text-slate-400 dark:text-slate-500 mt-1">Otomatis / Instan</span>
                        </label>
                        <label class="relative flex flex-col p-4 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl cursor-pointer hover:bg-slate-100/50 dark:hover:bg-slate-900/50 transition" id="payMethodBalanceLabel">
                            <input type="radio" name="payment_method" value="balance" onchange="togglePaymentMethod('balance')" class="absolute top-4 right-4 text-blue-600 focus:ring-blue-500">
                            <span class="font-bold text-sm text-slate-850 dark:text-slate-200">Saldo Akun</span>
                            <span class="text-[10px] text-slate-400 dark:text-slate-500 mt-1" id="checkoutUserBalanceText">Memuat...</span>
                        </label>
                    </div>
                </div>
                @else
                <input type="hidden" name="payment_method" value="qris">
                @endauth

                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pt-4 border-t border-slate-100 dark:border-slate-800">
                    <div>
                        <span class="text-xs text-slate-400 dark:text-slate-500 block">Harga Dasar:</span>
                        <span id="modalProductPrice" class="text-lg font-extrabold text-slate-800 dark:text-slate-100">Rp 0</span>
                    </div>
                    <button type="submit" id="submitBtn" class="w-full sm:w-auto flex items-center justify-center space-x-2 px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-2xl text-sm font-bold shadow-lg shadow-blue-500/20 active:scale-95 transition-all duration-200">
                        <span>Lanjut ke Pembayaran</span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                    </button>
                </div>
            </form>
        </div>

        <!-- PAYMENT INSTRUCTION STATE -->
        <div id="modalStepPayment" class="hidden text-center space-y-6">
            <div>
                <span class="px-3 py-1 bg-amber-50 dark:bg-amber-950/20 border border-amber-200 dark:border-amber-900/50 rounded-full text-[11px] font-bold text-amber-700 dark:text-amber-400 uppercase tracking-wider">Menunggu Pembayaran</span>
                <h3 class="text-2xl font-extrabold text-slate-800 dark:text-slate-100 leading-tight mt-3">Scan QRIS Untuk Bayar</h3>
                <p id="paymentOrderCode" class="text-xs text-slate-400 dark:text-slate-500 mt-1">Order ID: ORD-XXXXXX</p>
            </div>

            <!-- QR Code Render container -->
            <div class="relative w-60 h-60 mx-auto bg-white p-3 border border-slate-100 dark:border-slate-800 rounded-3xl flex items-center justify-center shadow-lg">
                <img id="qrisImage" src="" alt="QRIS QR Code" class="w-full h-full object-contain">
                
                <!-- QR Loading overlay -->
                <div id="qrLoading" class="absolute inset-0 bg-white/95 dark:bg-slate-950/95 flex flex-col items-center justify-center rounded-3xl">
                    <svg class="animate-spin w-8 h-8 text-blue-600" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                    <span class="text-xs text-slate-500 dark:text-slate-400 mt-2 font-medium">Membuat QRIS Dinamis...</span>
                </div>
            </div>

            <!-- Amount details with COPY button -->
            <div class="bg-slate-50 dark:bg-slate-900 p-4 border border-slate-150 dark:border-slate-800/80 rounded-2xl space-y-1 relative">
                <span class="text-xs text-slate-400 dark:text-slate-500 block uppercase tracking-wider font-semibold">Jumlah yang Harus Dibayar:</span>
                <div class="flex items-center justify-center space-x-2">
                    <span id="paymentTotalAmount" class="text-2xl font-black text-rose-600 dark:text-rose-400 tracking-tight">Rp 0</span>
                    <button onclick="copyAmount()" class="p-1.5 hover:bg-slate-200 dark:hover:bg-slate-800 text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 rounded-lg transition-all duration-150" title="Salin Nominal">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2"></path></svg>
                    </button>
                </div>
                <p class="text-[10px] text-amber-600 dark:text-amber-400 font-medium leading-relaxed">
                    *PENTING: Transfer HARUS persis hingga 3 angka terakhir untuk verifikasi otomatis!
                </p>
            </div>

            <!-- Countdown Timer -->
            <div class="flex items-center justify-center space-x-2.5 text-slate-600 dark:text-slate-400">
                <svg class="w-5 h-5 text-slate-400 dark:text-slate-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <span class="text-sm">Batas Waktu:</span>
                <span id="paymentCountdown" class="text-sm font-bold bg-slate-100 dark:bg-slate-900 px-3 py-1 rounded-xl text-slate-800 dark:text-slate-200">15:00</span>
            </div>

            <!-- Real-time Loading Spinner -->
            <div class="flex items-center justify-center space-x-3 text-sm text-slate-400 dark:text-slate-500">
                <svg class="animate-spin w-4 h-4 text-blue-500" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                <span>Mencocokkan mutasi otomatis...</span>
            </div>
        </div>

        <!-- TRANSACTION SUCCESS STATE -->
        <div id="modalStepSuccess" class="hidden space-y-6">
            <div class="text-center">
                <h3 class="text-2xl font-extrabold text-slate-800 dark:text-slate-100 leading-tight">Detail Akun VPN Anda</h3>
                <p id="successModalOrderId" class="text-xs text-slate-400 dark:text-slate-500 mt-1">Order ID: ORD-XXXXXX</p>
            </div>

            <div id="successDetailsContainer" class="bg-blue-50 dark:bg-blue-950/20 p-5 border border-blue-100 dark:border-blue-900/50 rounded-3xl text-slate-800 dark:text-slate-200">
                <!-- Dinamis diisi lewat JavaScript -->
            </div>
        </div>

        <!-- EXPIRED / ERROR STATE -->
        <div id="modalStepExpired" class="hidden text-center space-y-6 py-6">
            <div class="w-20 h-20 bg-rose-100 dark:bg-rose-950/40 rounded-full flex items-center justify-center mx-auto text-rose-600 shadow-xl shadow-rose-500/10">
                <svg class="w-10 h-10" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
            </div>

            <div>
                <h3 class="text-2xl font-black text-slate-800 dark:text-slate-100">Batas Waktu Habis</h3>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-2">Maaf, pesanan Anda telah kedaluwarsa karena tidak ada pembayaran yang terdeteksi dalam 15 menit.</p>
            </div>

            <button onclick="closeModal()" class="px-6 py-2.5 bg-slate-100 hover:bg-slate-200 dark:bg-slate-850 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-300 rounded-2xl text-sm font-semibold transition-all duration-200">
                Tutup & Coba Lagi
            </button>
        </div>

    </div>
</div>

<!-- INFO DETAIL MODAL -->
<div id="detailModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm hidden">
    <div class="bg-white dark:bg-slate-950 rounded-3xl w-full max-w-md border border-slate-100 dark:border-slate-800 p-5 sm:p-8 shadow-2xl relative max-h-[calc(100vh-2rem)] overflow-y-auto">
        <button onclick="closeDetailModal()" class="absolute top-5 right-5 p-2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-900 transition-all duration-200">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
        </button>
        <h3 id="detailProductTitle" class="text-xl font-bold text-slate-800 dark:text-slate-100">Detail Produk</h3>
        <div class="mt-4 space-y-3 text-sm text-slate-600 dark:text-slate-400">
            <p><strong>Nama:</strong> <span id="detailName"></span></p>
            <p><strong>Harga:</strong> <span id="detailPrice"></span></p>
            <p><strong>Masa Aktif:</strong> <span id="detailDuration"></span> Hari</p>
            <p><strong>Deskripsi:</strong></p>
            <p id="detailDesc" class="text-xs text-slate-500 dark:text-slate-400 bg-slate-50 dark:bg-slate-900/60 p-3.5 rounded-2xl border border-slate-100 dark:border-slate-800 whitespace-pre-wrap leading-relaxed"></p>
        </div>
        <div class="mt-6 flex justify-end">
            <button onclick="closeDetailModal()" class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold transition-all duration-200">
                Mengerti
            </button>
        </div>
    </div>
</div>

<!-- Polling & Countdown Script -->
<script>
    let activeOrderId = null;
    let pollInterval = null;
    let countdownInterval = null;
    let currentRawAmount = 0;

    // Open detail modal
    function openDetailModal(name, price, duration, description) {
        document.getElementById('detailName').innerText = name;
        document.getElementById('detailPrice').innerText = 'Rp ' + parseInt(price).toLocaleString('id-ID');
        document.getElementById('detailDuration').innerText = duration;
        document.getElementById('detailDesc').innerHTML = description || `Masa aktif: <strong>${duration} hari</strong>. Konfigurasi siap pakai langsung unduh setelah sukses verifikasi pembayaran.`;
        document.getElementById('detailModal').classList.remove('hidden');
    }

    function closeDetailModal() {
        document.getElementById('detailModal').classList.add('hidden');
    }

    // Open buy modal
    function openBuyModal(productId, productName, productPrice, hasSupplierCode) {
        document.getElementById('formProductId').value = productId;
        document.getElementById('modalProductTitle').innerText = productName;
        document.getElementById('modalProductPrice').innerText = 'Rp ' + parseInt(productPrice).toLocaleString('id-ID');
        
        const targetPhoneContainer = document.getElementById('targetPhoneContainer');
        const targetPhoneInput = document.getElementById('target_phone');
        
        if (hasSupplierCode && hasSupplierCode !== '') {
            targetPhoneContainer.classList.remove('hidden');
            targetPhoneInput.required = true;
        } else {
            targetPhoneContainer.classList.add('hidden');
            targetPhoneInput.required = false;
            targetPhoneInput.value = '';
        }

        // Set default payment method selection to QRIS
        const qrisInput = document.querySelector('input[name="payment_method"][value="qris"]');
        if (qrisInput) {
            qrisInput.checked = true;
            togglePaymentMethod('qris');
        }

        // Fetch balance if user is authenticated
        const balanceText = document.getElementById('checkoutUserBalanceText');
        const balanceInput = document.querySelector('input[name="payment_method"][value="balance"]');
        const balanceLabel = document.getElementById('payMethodBalanceLabel');
        if (balanceText) {
            balanceText.innerText = 'Memuat...';
            if (balanceInput) {
                balanceInput.disabled = true;
                balanceLabel.classList.add('opacity-50', 'cursor-not-allowed');
            }
            fetch('/api/balance')
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    balanceText.innerText = data.formatted_balance;
                    if (data.balance >= productPrice) {
                        if (balanceInput) {
                            balanceInput.disabled = false;
                            balanceLabel.classList.remove('opacity-50', 'cursor-not-allowed');
                        }
                    } else {
                        balanceText.innerText += ' (Kurang)';
                    }
                } else {
                    balanceText.innerText = 'Gagal memuat';
                }
            })
            .catch(() => {
                balanceText.innerText = 'Error';
            });
        }
        
        // Reset states
        document.getElementById('modalStepInput').classList.remove('hidden');
        document.getElementById('modalStepPayment').classList.add('hidden');
        document.getElementById('modalStepSuccess').classList.add('hidden');
        document.getElementById('modalStepExpired').classList.add('hidden');
        
        document.getElementById('purchaseModal').classList.remove('hidden');
    }

    function togglePaymentMethod(method) {
        const qrisLabel = document.getElementById('payMethodQrisLabel');
        const balanceLabel = document.getElementById('payMethodBalanceLabel');
        const submitBtn = document.getElementById('submitBtn');

        if (!qrisLabel || !balanceLabel) return;

        if (method === 'qris') {
            qrisLabel.className = "relative flex flex-col p-4 bg-slate-50 dark:bg-slate-900 border-2 border-blue-600 rounded-2xl cursor-pointer hover:bg-slate-100/50 dark:hover:bg-slate-900/50 transition";
            balanceLabel.className = "relative flex flex-col p-4 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl cursor-pointer hover:bg-slate-100/50 dark:hover:bg-slate-900/50 transition";
            if (submitBtn) {
                submitBtn.innerHTML = `
                    <span>Lanjut ke Pembayaran</span>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                `;
            }
        } else {
            balanceLabel.className = "relative flex flex-col p-4 bg-slate-50 dark:bg-slate-900 border-2 border-blue-600 rounded-2xl cursor-pointer hover:bg-slate-100/50 dark:hover:bg-slate-900/50 transition";
            qrisLabel.className = "relative flex flex-col p-4 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl cursor-pointer hover:bg-slate-100/50 dark:hover:bg-slate-900/50 transition";
            if (submitBtn) {
                submitBtn.innerHTML = `
                    <span>Bayar Sekarang</span>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg>
                `;
            }
        }
    }

    function closeModal() {
        document.getElementById('purchaseModal').classList.add('hidden');
        resetOrderTracker();
    }

    function resetOrderTracker() {
        activeOrderId = null;
        if (pollInterval) clearInterval(pollInterval);
        if (countdownInterval) clearInterval(countdownInterval);
    }

    // Submit purchase form via AJAX
    function submitPurchase(e) {
        e.preventDefault();
        
        const emailOrWhatsapp = document.getElementById('email_or_whatsapp').value;
        const productId = document.getElementById('formProductId').value;
        const targetPhoneInput = document.getElementById('target_phone');
        const targetPhoneContainer = document.getElementById('targetPhoneContainer');
        const targetPhone = targetPhoneInput.value.trim();
        
        // Client-side validation for target_phone if visible
        if (!targetPhoneContainer.classList.contains('hidden')) {
            if (!/^[0-9]+$/.test(targetPhone) || targetPhone.length < 10 || targetPhone.length > 13) {
                alert('Nomor HP Tujuan harus berupa angka dengan panjang 10-13 karakter!');
                return;
            }
        }

        const paymentMethodEl = document.querySelector('input[name="payment_method"]:checked');
        const paymentMethod = paymentMethodEl ? paymentMethodEl.value : 'qris';
        
        const submitBtn = document.getElementById('submitBtn');
        const defaultBtnHtml = submitBtn.innerHTML;
        
        submitBtn.disabled = true;
        submitBtn.innerHTML = 'Memproses...';
 
        fetch("{{ route('buy') }}", {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({
                product_id: productId,
                email_or_whatsapp: emailOrWhatsapp,
                target_phone: targetPhone,
                payment_method: paymentMethod
            })
        })
        .then(response => response.json())
        .then(data => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = defaultBtnHtml;
            
            if (data.success) {
                if (data.payment_method === 'balance') {
                    document.getElementById('modalStepInput').classList.add('hidden');
                    document.getElementById('modalStepSuccess').classList.remove('hidden');
                    populateSuccessDetails(data.order.vpn_config, data.order.success_instruction, data.order.id);
                } else {
                    renderPaymentInstructions(data.order);
                }
            } else {
                alert(data.message || 'Gagal memproses pesanan.');
            }
        })
        .catch(err => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = defaultBtnHtml;
            alert('Terjadi kesalahan koneksi server.');
            console.error(err);
        });
    }

    // Show QRIS details and start countdown + polling
    function renderPaymentInstructions(order) {
        activeOrderId = order.id;
        currentRawAmount = order.total_amount;
        
        // Show payment state, hide input state
        document.getElementById('modalStepInput').classList.add('hidden');
        document.getElementById('modalStepPayment').classList.remove('hidden');
        
        // Populate instructions
        document.getElementById('paymentOrderCode').innerText = 'Order ID: ' + order.id;
        document.getElementById('paymentTotalAmount').innerText = 'Rp ' + parseInt(order.total_amount).toLocaleString('id-ID');
        
        // Load QR Code Image
        const qrLoading = document.getElementById('qrLoading');
        const qrisImage = document.getElementById('qrisImage');
        qrLoading.classList.remove('hidden');
        
        const qrisPayloadUrl = encodeURIComponent(order.qris_payload);
        qrisImage.src = `https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=${qrisPayloadUrl}`;
        
        qrisImage.onload = () => {
            qrLoading.classList.add('hidden');
        };

        // Start countdown timer
        startCountdown(order.expired_at, order.server_time);
        
        // Start polling for payment verification
        startPolling();
    }

    // Countdown Timer logic
    function startCountdown(expiryStr, serverTimeStr) {
        const expiry = new Date(expiryStr).getTime();
        const serverTime = new Date(serverTimeStr).getTime();
        const localStartTime = new Date().getTime();
        const serverDiff = serverTime - localStartTime; // calculate delay between server and client clock

        const countdownElement = document.getElementById('paymentCountdown');
        
        if (countdownInterval) clearInterval(countdownInterval);

        countdownInterval = setInterval(() => {
            const now = new Date().getTime() + serverDiff;
            const distance = expiry - now;

            if (distance < 0) {
                clearInterval(countdownInterval);
                showExpiredState();
                return;
            }

            const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((distance % (1000 * 60)) / 1000);

            countdownElement.innerText = 
                (minutes < 10 ? '0' : '') + minutes + ':' + 
                (seconds < 10 ? '0' : '') + seconds;
        }, 1000);
    }

    // Polling logic
    function startPolling() {
        if (pollInterval) clearInterval(pollInterval);
        
        pollInterval = setInterval(() => {
            if (!activeOrderId) return;
            
            fetch(`/orders/${activeOrderId}/status`)
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success' || data.status === 'paid') {
                    showSuccessState(data.vpn_config, data.success_instruction);
                } else if (data.status === 'expired') {
                    showExpiredState();
                }
            })
            .catch(err => console.error("Error polling order status:", err));
        }, 4000); // Poll every 4 seconds
    }

    function showSuccessState(vpnConfig, successInstruction) {
        resetOrderTracker();
        document.getElementById('modalStepPayment').classList.add('hidden');
        document.getElementById('modalStepSuccess').classList.remove('hidden');
        
        populateSuccessDetails(vpnConfig, successInstruction, activeOrderId);
    }

    function populateSuccessDetails(config, successInstruction, orderId) {
        const container = document.getElementById('successDetailsContainer');
        if (!container) return;
        
        const orderIdSubtitle = document.getElementById('successModalOrderId');
        if (orderIdSubtitle) {
            orderIdSubtitle.innerText = 'Order ID: ' + orderId;
        }
        
        container.className = "bg-blue-50 dark:bg-blue-950/20 p-5 border border-blue-100 dark:border-blue-900/50 rounded-3xl max-w-lg mx-auto space-y-3 text-slate-800 dark:text-slate-200";

        // Extract node link (vmess, vless, trojan, ss, shadowsocks)
        const trimmedConfig = config ? config.trim() : '';
        let nodeLink = '';
        const match = trimmedConfig.match(/(vmess|vless|trojan|ss|shadowsocks):\/\/[^\s"]+/i);
        if (match) {
            nodeLink = match[0];
        }

        // Case 1: Config is a node link (vmess, vless, trojan, etc.)
        if (nodeLink) {
            let html = `
                <div class="space-y-4">
                    <span class="text-xs font-bold text-blue-600 dark:text-blue-400 block uppercase tracking-wider text-left">DETAIL AKUN VPN:</span>
                    <div class="flex flex-col md:flex-row gap-5 items-stretch justify-between text-left">
                        <div class="flex-1 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-5 rounded-3xl flex flex-col justify-between w-full">
                            <textarea readonly class="w-full bg-transparent text-xs font-mono text-slate-800 dark:text-slate-200 border-none outline-none focus:ring-0 resize-none h-28 pr-1 custom-scrollbar" id="successConfigText">${escapeHtml(nodeLink)}</textarea>
                            <div class="flex justify-center mt-4">
                                <button onclick="copySuccessConfig()" class="inline-flex items-center space-x-2 px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-full text-xs font-bold transition-all duration-200 shadow-md shadow-blue-500/10">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2"></path></svg>
                                    <span id="copySuccessBtnText">Salin Akun</span>
                                </button>
                            </div>
                        </div>
                        <div class="w-full md:w-44 bg-white dark:bg-slate-900 p-4 border border-slate-200 dark:border-slate-800 rounded-3xl flex items-center justify-center flex-shrink-0 shadow-sm aspect-square md:aspect-auto">
                            <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=${encodeURIComponent(nodeLink)}" alt="QR Code" class="w-full h-full object-contain rounded-xl">
                        </div>
                    </div>
                </div>
            `;

            // If there's also a success instruction, append it below the node link details
            if (successInstruction && successInstruction.trim() !== '') {
                html += `
                    <div class="mt-4 p-4 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl text-left">
                        <span class="text-xs text-blue-600 dark:text-blue-400 font-bold block uppercase tracking-wider mb-2">Petunjuk Tambahan:</span>
                        <div class="text-xs text-slate-600 dark:text-slate-400 leading-relaxed whitespace-pre-wrap">${escapeHtml(successInstruction)}</div>
                    </div>
                `;
            }

            container.innerHTML = html;
            return;
        }

        // Case 2: No node link, but success instruction exists
        if (successInstruction && successInstruction.trim() !== '') {
            container.innerHTML = `
                <div class="text-left space-y-2 p-1">
                    <span class="text-xs text-blue-600 dark:text-blue-400 font-bold block uppercase tracking-wider">Petunjuk Pembayaran Sukses:</span>
                    <div class="text-sm text-slate-700 dark:text-slate-300 leading-relaxed whitespace-pre-wrap">${escapeHtml(successInstruction)}</div>
                </div>
            `;
            return;
        }

        // Case 3: Plain single-line credentials (e.g. vip.rzkstores.my.id:80@admin123)
        const isSingleLine = trimmedConfig && !trimmedConfig.includes('\n') && !trimmedConfig.includes('\r');
        if (isSingleLine) {
            container.innerHTML = `
                <div class="text-left space-y-3 p-1">
                    <span class="text-xs text-blue-600 dark:text-blue-400 font-bold block uppercase tracking-wider">Konfigurasi VPN Anda:</span>
                    <div class="relative bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-4 rounded-2xl flex items-center justify-between gap-3">
                        <span class="text-sm font-mono text-slate-800 dark:text-slate-200 select-all break-all" id="successConfigTextPlain">${escapeHtml(config)}</span>
                        <button onclick="copySuccessConfigPlain()" class="flex-shrink-0 p-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-bold transition-all duration-200" title="Salin Akun">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2"></path></svg>
                        </button>
                    </div>
                </div>
            `;
            return;
        }

        // Case 4: Default download button (.ovpn configuration file)
        container.innerHTML = `
            <div class="space-y-3 p-1">
                <span class="text-xs text-blue-600 dark:text-blue-400 font-bold block uppercase tracking-wider">Konfigurasi VPN Anda:</span>
                <p class="text-xs text-slate-500 dark:text-slate-400">Tekan tombol di bawah untuk mengunduh konfigurasi siap pakai Anda (.ovpn):</p>
                <a href="/orders/${orderId}/download" class="mt-4 inline-flex items-center space-x-2 px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-2xl text-sm font-bold shadow-lg shadow-blue-500/20 transition-all duration-200">
                    <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                    <span>Unduh Konfigurasi (.ovpn)</span>
                </a>
            </div>
        `;
    }

    function escapeHtml(text) {
        if (!text) return '';
        return text
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    function copySuccessConfig() {
        const copyText = document.getElementById("successConfigText");
        copyText.select();
        copyText.setSelectionRange(0, 99999);
        navigator.clipboard.writeText(copyText.value);
        
        const btnText = document.getElementById("copySuccessBtnText");
        btnText.innerText = "Tersalin!";
        setTimeout(() => {
            btnText.innerText = "Salin Akun";
        }, 2000);
    }

    function copySuccessConfigPlain() {
        const copyText = document.getElementById("successConfigTextPlain").innerText;
        navigator.clipboard.writeText(copyText);
        alert("Konfigurasi VPN berhasil disalin ke clipboard!");
    }

    function showExpiredState() {
        resetOrderTracker();
        document.getElementById('modalStepPayment').classList.add('hidden');
        document.getElementById('modalStepExpired').classList.remove('hidden');
    }

    function copyAmount() {
        if (!currentRawAmount) return;
        navigator.clipboard.writeText(currentRawAmount);
        
        // Minimal visual indicator
        const btn = document.querySelector('[onclick="copyAmount()"]');
        const originalHtml = btn.innerHTML;
        btn.innerHTML = '<span class="text-xs text-emerald-500 font-bold">Tersalin!</span>';
        setTimeout(() => {
            btn.innerHTML = originalHtml;
        }, 1500);
    }
</script>
@endsection
