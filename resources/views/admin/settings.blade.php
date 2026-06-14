@extends('layouts.app', ['title' => 'Konfigurasi QRIS & API'])

@section('content')
<div class="space-y-8 max-w-3xl">
    
    <!-- Header -->
    <div class="border-b border-slate-200 dark:border-slate-800 pb-5">
        <h2 class="text-3xl font-extrabold text-slate-855 dark:text-slate-100 tracking-tight">Pengaturan QRIS & API</h2>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Konfigurasikan QRIS dasar dan kredensial webhook Android Anda</p>
    </div>

    <!-- Configuration Form -->
    <div class="bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 rounded-3xl p-8 shadow-sm">
        
        <form action="{{ route('admin.settings.update') }}" method="POST" class="space-y-6">
            @csrf

            <!-- Static QRIS String -->
            <div class="space-y-2">
                <label for="qris_static_string" class="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">String QRIS Statis Dasar</label>
                <textarea id="qris_static_string" name="qris_static_string" rows="4" placeholder="Salin string QRIS statis Anda di sini (Contoh: 0002010102112657...)" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 focus:border-blue-500 focus:bg-white focus:outline-none rounded-2xl text-sm font-mono text-slate-800 dark:text-slate-100 transition-all duration-200">{{ $qrisStaticString }}</textarea>
                <p class="text-[11px] text-slate-450 dark:text-slate-500 leading-relaxed">
                    Dapatkan string ini dengan melakukan scan QRIS Statis Anda (misal QRIS GoPay, OVO, ShopeePay, DANA Merchant) menggunakan aplikasi QR Code Scanner biasa, lalu copy-paste isinya di sini. Sistem akan menginjeksi nominal pesanan secara dinamis dan menghitung ulang CRC16 secara otomatis.
                </p>
            </div>

            <!-- API Secret Key (Android Callback Token) -->
            <div class="space-y-2">
                <label for="api_secret_key" class="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Secret API Token (Android Callback)</label>
                <div class="flex space-x-2">
                    <input type="text" id="api_secret_key" name="api_secret_key" value="{{ $apiSecretKey }}" placeholder="Masukkan atau generate token rahasia..." class="flex-1 px-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 focus:border-blue-500 focus:bg-white focus:outline-none rounded-2xl text-sm font-mono text-slate-800 dark:text-slate-100 transition-all duration-200">
                    <button type="button" onclick="generateApiKey()" class="px-5 py-3 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-350 font-bold rounded-2xl text-xs transition-all duration-200 whitespace-nowrap">
                        Generate Token
                    </button>
                </div>
                <p class="text-[11px] text-slate-450 dark:text-slate-500 leading-relaxed">
                    Token ini digunakan untuk mengamankan callback endpoint <code>/api/v1/payment/callback-notification</code>. Masukkan token yang sama ke dalam aplikasi pembaca notifikasi SMS/Mutasi bank/E-Wallet di Android Anda agar notifikasi pembayaran dapat diverifikasi otomatis.
                </p>
            </div>

            <!-- Save Button -->
            <div class="pt-4 border-t border-slate-100 dark:border-slate-800 flex justify-end">
                <button type="submit" class="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-2xl text-sm font-bold shadow-lg shadow-blue-500/20 active:scale-95 transition-all duration-200">
                    Simpan Pengaturan
                </button>
            </div>
        </form>

    </div>
</div>

<script>
    function generateApiKey() {
        const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        let token = '';
        for (let i = 0; i < 32; i++) {
            token += chars.charAt(Math.floor(Math.random() * chars.length));
        }
        document.getElementById('api_secret_key').value = token;
    }
</script>
@endsection
