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

            <!-- Save Button -->
            <div class="pt-4 border-t border-slate-100 dark:border-slate-800 flex justify-end">
                <button type="submit" class="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-2xl text-sm font-bold shadow-lg shadow-blue-500/20 active:scale-95 transition-all duration-200">
                    Simpan Konfigurasi
                </button>
            </div>
        </form>

    </div>
</div>
@endsection
