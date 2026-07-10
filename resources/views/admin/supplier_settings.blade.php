@extends('layouts.app', ['title' => 'Pengaturan API Supplier'])

@section('content')
<div class="space-y-6 sm:space-y-8 max-w-3xl">
    
    <!-- Header -->
    <div class="border-b border-slate-200 dark:border-slate-800 pb-5">
        <h2 class="text-2xl sm:text-3xl font-extrabold text-slate-855 dark:text-slate-100 tracking-tight">Pengaturan API Supplier</h2>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Konfigurasikan kredensial H2H Orderkuota.com Anda untuk otomatisasi produk</p>
    </div>

    <!-- Configuration Form -->
    <div class="bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 rounded-3xl p-5 sm:p-8 shadow-sm">
        
        <form action="{{ route('admin.supplier_settings.update') }}" method="POST" class="space-y-6">
            @csrf

            <!-- Member ID / Username -->
            <div class="space-y-2">
                <label for="orderkuota_member_id" class="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Orderkuota Member ID / Username</label>
                <input type="text" id="orderkuota_member_id" name="orderkuota_member_id" value="{{ $memberId }}" placeholder="Masukkan Member ID atau Username Orderkuota..." class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 focus:border-blue-500 focus:bg-white focus:outline-none rounded-2xl text-sm text-slate-800 dark:text-slate-100 transition-all duration-200">
                <p class="text-[11px] text-slate-450 dark:text-slate-500 leading-relaxed">
                    Username atau ID keanggotaan Anda yang terdaftar secara resmi di platform Orderkuota.
                </p>
            </div>

            <!-- API Key / Token -->
            <div class="space-y-2">
                <label for="orderkuota_api_key" class="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Orderkuota API Key / Token</label>
                <input type="password" id="orderkuota_api_key" name="orderkuota_api_key" value="{{ $apiKey }}" placeholder="Masukkan API Key / Token Anda..." class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 focus:border-blue-500 focus:bg-white focus:outline-none rounded-2xl text-sm font-mono text-slate-800 dark:text-slate-100 transition-all duration-200">
                <p class="text-[11px] text-slate-450 dark:text-slate-500 leading-relaxed">
                    Dapatkan API Key di halaman profil / pengaturan developer pada panel web member H2H Orderkuota Anda. Token ini dirahasiakan dan disimpan dengan aman.
                </p>
            </div>

            <!-- PIN Transaksi -->
            <div class="space-y-2">
                <label for="orderkuota_pin" class="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Orderkuota PIN Transaksi</label>
                <input type="password" id="orderkuota_pin" name="orderkuota_pin" value="{{ $pin }}" placeholder="Masukkan PIN Transaksi Anda (misal: 1234)..." class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 focus:border-blue-500 focus:bg-white focus:outline-none rounded-2xl text-sm font-mono text-slate-800 dark:text-slate-100 transition-all duration-200">
                <p class="text-[11px] text-slate-450 dark:text-slate-500 leading-relaxed">
                    PIN transaksi 4-6 digit yang Anda buat di panel web member H2H Orderkuota.
                </p>
            </div>

            <!-- Status Mode (Sandbox / Production Toggle) -->
            <div class="space-y-2">
                <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-2">Mode Transaksi API</label>
                <div class="flex items-center space-x-3">
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="radio" name="orderkuota_mode" value="sandbox" class="sr-only peer" {{ $mode === 'sandbox' ? 'checked' : '' }}>
                        <div class="px-5 py-2.5 rounded-xl text-sm font-bold border border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-400 peer-checked:bg-amber-500 peer-checked:text-white peer-checked:border-amber-500 hover:bg-slate-50 dark:hover:bg-slate-800/40 peer-checked:hover:bg-amber-600 transition-all duration-200">
                            Sandbox (Testing)
                        </div>
                    </label>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="radio" name="orderkuota_mode" value="production" class="sr-only peer" {{ $mode === 'production' ? 'checked' : '' }}>
                        <div class="px-5 py-2.5 rounded-xl text-sm font-bold border border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-400 peer-checked:bg-emerald-600 peer-checked:text-white peer-checked:border-emerald-600 hover:bg-slate-50 dark:hover:bg-slate-800/40 peer-checked:hover:bg-emerald-700 transition-all duration-200">
                            Production (Live)
                        </div>
                    </label>
                </div>
                <p class="text-[11px] text-slate-450 dark:text-slate-500 leading-relaxed">
                    Gunakan <strong>Sandbox</strong> untuk melakukan uji coba transaksi tanpa memotong saldo asli. Aktifkan <strong>Production</strong> jika integrasi sudah siap dan Anda ingin menembak transaksi real ke API Orderkuota.
                </p>
            </div>

            <!-- Okeconnect Price List ID -->
            <div class="space-y-2">
                <label for="okeconnect_price_list_id" class="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Okeconnect Price List ID</label>
                <input type="text" id="okeconnect_price_list_id" name="okeconnect_price_list_id" value="{{ $priceListId }}" placeholder="Masukkan ID Daftar Harga (misal: 905ccd028329b0a)..." class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 focus:border-blue-500 focus:bg-white focus:outline-none rounded-2xl text-sm font-mono text-slate-800 dark:text-slate-100 transition-all duration-200">
                <p class="text-[11px] text-slate-450 dark:text-slate-500 leading-relaxed">
                    ID unik rencana harga Anda yang tertera di URL halaman daftar harga Okeconnect (contoh: <code>905ccd028329b0a</code> dari <code>https://okeconnect.com/harga/list?id=905ccd028329b0a</code>).
                </p>
            </div>

            <!-- Okeconnect Markup Price -->
            <div class="space-y-2">
                <label for="okeconnect_markup_price" class="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Default Markup Harga (Rupiah)</label>
                <input type="number" id="okeconnect_markup_price" name="okeconnect_markup_price" value="{{ $markupPrice }}" placeholder="Masukkan keuntungan harga (contoh: 1000)..." class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 focus:border-blue-500 focus:bg-white focus:outline-none rounded-2xl text-sm font-mono text-slate-800 dark:text-slate-100 transition-all duration-200">
                <p class="text-[11px] text-slate-450 dark:text-slate-500 leading-relaxed">
                    Jumlah keuntungan (markup) dalam Rupiah yang ditambahkan ke harga modal dari Okeconnect saat membuat/memperbarui produk (Harga Jual = Harga Modal + Markup).
                </p>
            </div>

            <!-- Save Button -->
            <div class="pt-4 border-t border-slate-100 dark:border-slate-800 flex justify-end">
                <button type="submit" class="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-2xl text-sm font-bold shadow-lg shadow-blue-500/20 active:scale-95 transition-all duration-200">
                    Simpan Konfigurasi
                </button>
            </div>
        </form>

    </div>

    <!-- Bulk Import and Sync Section -->
    <div class="bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 rounded-3xl p-5 sm:p-8 shadow-sm mt-6">
        <h3 class="text-lg font-bold text-slate-800 dark:text-slate-100">Impor & Pembaruan Massal Produk H2H</h3>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 mb-4">Lakukan impor semua produk dari daftar harga JSON Okeconnect secara massal. Kategori dan produk akan dibuat atau diperbarui otomatis.</p>
        
        <form action="{{ route('admin.supplier_settings.import') }}" method="POST">
            @csrf
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between p-4 bg-blue-50/50 dark:bg-blue-950/20 border border-blue-100 dark:border-blue-900/50 rounded-2xl gap-4">
                <div class="space-y-1">
                    <span class="block text-sm font-bold text-slate-700 dark:text-slate-300">Jalankan Proses Impor Massal</span>
                    <span class="block text-[11px] text-slate-500 dark:text-slate-400 leading-normal">
                        Ini akan mendaftarkan pekerjaan latar belakang (*Background Queue Job*) untuk menarik ribuan data produk Okeconnect. Proses ini aman dan tidak akan menyebabkan timeout pada browser Anda.
                    </span>
                </div>
                <button type="submit" class="px-5 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold transition-all duration-200 whitespace-nowrap active:scale-95 shadow-sm">
                    🚀 Impor Sekarang
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
