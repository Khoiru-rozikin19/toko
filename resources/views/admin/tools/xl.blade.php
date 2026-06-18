@extends('layouts.app', ['title' => 'Cek & Reset Kuota XL'])

@section('content')
<div class="max-w-7xl mx-auto space-y-8">
    
    <!-- Header Summary Status Card -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-6 sm:p-8 flex flex-col md:flex-row md:items-center justify-between gap-6 shadow-sm transition-all duration-300">
        <div class="flex items-center space-x-4">
            <div class="w-16 h-16 rounded-2xl bg-blue-600/10 dark:bg-blue-600/20 text-blue-600 dark:text-blue-400 flex items-center justify-center font-bold text-2xl shadow-sm">
                XL
            </div>
            <div>
                <h2 class="text-xl font-bold text-slate-800 dark:text-slate-100">Cek & Reset Kuota XL</h2>
                <p class="text-sm text-slate-400 dark:text-slate-500 mt-1">
                    Porting Fitur Premium Client MyXL dari <code class="bg-slate-100 dark:bg-slate-850 px-1.5 py-0.5 rounded font-mono text-xs">me-cli-sunset</code>.
                </p>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            @if($activeSession)
                <div class="px-4 py-2 bg-emerald-50 dark:bg-emerald-950/20 border border-emerald-200/30 text-emerald-700 dark:text-emerald-400 rounded-2xl text-sm font-semibold flex items-center space-x-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
                    <span>Aktif: {{ $activeSession->label }} ({{ $activeSession->msisdn }})</span>
                </div>
            @else
                <div class="px-4 py-2 bg-slate-50 dark:bg-slate-950/20 border border-slate-200/30 text-slate-500 dark:text-slate-400 rounded-2xl text-sm font-semibold flex items-center space-x-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-slate-400"></span>
                    <span>Belum ada akun XL aktif</span>
                </div>
            @endif
        </div>
    </div>

    <!-- QRIS Invoice Notification Overlay (If QRIS payment was generated) -->
    @if(session('qris_code'))
        <div class="bg-linear-to-r from-blue-600 to-indigo-600 border border-blue-500 text-white rounded-3xl p-6 sm:p-8 shadow-xl shadow-blue-500/10 flex flex-col md:flex-row items-center justify-between gap-6 transition-all duration-300">
            <div class="space-y-3 max-w-lg">
                <span class="px-3 py-1 bg-white/20 border border-white/30 text-xs font-bold uppercase rounded-full tracking-wider">Invoice QRIS Sukses Dibuat</span>
                <h3 class="text-2xl font-black">Scan QRIS untuk Menyelesaikan Pembayaran</h3>
                <p class="text-sm text-blue-100">
                    Total tagihan sebesar <strong class="text-white font-extrabold text-lg">Rp {{ number_format(session('qris_amount')) }}</strong> untuk Kode Transaksi <code class="bg-black/20 px-1.5 py-0.5 rounded text-xs font-mono font-bold">{{ session('qris_trx') }}</code>. QRIS ini berlaku dinamis dari MyXL.
                </p>
                <div class="pt-2">
                    <a href="https://ki-ar-kod.netlify.app/?data={{ urlencode(base64_encode(session('qris_code'))) }}" target="_blank" class="inline-flex items-center space-x-2 bg-white hover:bg-slate-100 text-blue-600 px-5 py-2.5 rounded-2xl text-xs font-bold transition-all duration-200">
                        <span>Buka QRIS di Tab Baru</span>
                    </a>
                </div>
            </div>
            <div class="bg-white p-4 rounded-3xl shadow-lg shadow-black/20 flex flex-col items-center">
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=160x160&data={{ urlencode(session('qris_code')) }}" alt="QRIS Code" class="w-40 h-40">
                <span class="text-[9px] font-bold text-slate-400 uppercase mt-2.5 tracking-wider">QRIS Dinamis MyXL</span>
            </div>
        </div>
    @endif

    @if($errors->any())
        <div class="p-4 bg-rose-50 dark:bg-rose-950/20 border border-rose-200 dark:border-rose-900/50 text-rose-800 dark:text-rose-400 rounded-2xl flex items-center space-x-3">
            <svg class="w-5 h-5 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
            <span class="text-sm font-medium">{{ $errors->first() }}</span>
        </div>
    @endif

    <!-- Dasbor Tabs Area -->
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
        
        <!-- Navigation Menu Tabs -->
        <div class="lg:col-span-1 space-y-2">
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-4 shadow-sm flex flex-col space-y-1">
                <button onclick="switchTab('tab-overview')" id="btn-tab-overview" class="tab-btn w-full text-left px-4 py-3 rounded-xl text-sm font-bold flex items-center space-x-3 transition-all duration-200 bg-blue-50 dark:bg-blue-950/30 text-blue-600 dark:text-blue-400">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2H6a2 2 0 01-2-2v-4zM14 16a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2h-2a2 2 0 01-2-2v-4z"></path></svg>
                    <span>Overview & Akun</span>
                </button>
                <button onclick="switchTab('tab-quota')" id="btn-tab-quota" class="tab-btn w-full text-left px-4 py-3 rounded-xl text-sm font-bold flex items-center space-x-3 transition-all duration-200 text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800/80">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                    <span>Paket Saya</span>
                </button>
                <button onclick="switchTab('tab-store')" id="btn-tab-store" class="tab-btn w-full text-left px-4 py-3 rounded-xl text-sm font-bold flex items-center space-x-3 transition-all duration-200 text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800/80">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>
                    <span>Beli Paket</span>
                </button>
                <button onclick="switchTab('tab-akrab')" id="btn-tab-akrab" class="tab-btn w-full text-left px-4 py-3 rounded-xl text-sm font-bold flex items-center space-x-3 transition-all duration-200 text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800/80">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                    <span>Akrab Organizer</span>
                </button>
                <button onclick="switchTab('tab-history')" id="btn-tab-history" class="tab-btn w-full text-left px-4 py-3 rounded-xl text-sm font-bold flex items-center space-x-3 transition-all duration-200 text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800/80">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path></svg>
                    <span>Riwayat Transaksi</span>
                </button>
                <button onclick="switchTab('tab-settings')" id="btn-tab-settings" class="tab-btn w-full text-left px-4 py-3 rounded-xl text-sm font-bold flex items-center space-x-3 transition-all duration-200 text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800/80">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                    <span>Pengaturan API</span>
                </button>
            </div>
        </div>

        <!-- Tab Contents Area -->
        <div class="lg:col-span-3 space-y-8">
            
            <!-- OVERVIEW & ACCOUNT MANAGEMENT -->
            <div id="tab-overview" class="tab-content space-y-8">
                
                @if($activeSession && $profile)
                    <!-- Active Account Profil Details -->
                    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-6 sm:p-8 shadow-sm">
                        <h3 class="text-lg font-bold text-slate-800 dark:text-slate-100 mb-6">Detail Profil Aktif</h3>
                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-6">
                            <div class="bg-slate-50 dark:bg-slate-950 p-5 rounded-2xl border border-slate-100 dark:border-slate-850">
                                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Nama Akun</span>
                                <p class="text-lg font-extrabold text-slate-800 dark:text-slate-100 mt-1.5">{{ $profile['profile']['name'] ?? 'N/A' }}</p>
                            </div>
                            <div class="bg-slate-50 dark:bg-slate-950 p-5 rounded-2xl border border-slate-100 dark:border-slate-850">
                                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Nomor HP (MSISDN)</span>
                                <p class="text-lg font-extrabold text-slate-800 dark:text-slate-100 mt-1.5">{{ $activeSession->msisdn }}</p>
                            </div>
                            <div class="bg-slate-50 dark:bg-slate-950 p-5 rounded-2xl border border-slate-100 dark:border-slate-850">
                                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Tipe Layanan / Kartu</span>
                                <p class="text-lg font-extrabold text-slate-800 dark:text-slate-100 mt-1.5">{{ $profile['profile']['subscription_type'] ?? 'N/A' }}</p>
                            </div>
                            <div class="bg-slate-50 dark:bg-slate-950 p-5 rounded-2xl border border-slate-100 dark:border-slate-850">
                                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Sisa Pulsa / Limit</span>
                                <p class="text-xl font-black text-blue-600 dark:text-blue-400 mt-1.5">Rp {{ number_format($balance['remaining'] ?? 0) }}</p>
                            </div>
                            <div class="bg-slate-50 dark:bg-slate-950 p-5 rounded-2xl border border-slate-100 dark:border-slate-850">
                                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider font-bold">Masa Aktif Selesai</span>
                                <p class="text-sm font-extrabold text-slate-800 dark:text-slate-100 mt-2.5">
                                    {{ isset($balance['expired_at']) ? date('d F Y', $balance['expired_at']) : 'N/A' }}
                                </p>
                            </div>
                            <div class="bg-slate-50 dark:bg-slate-950 p-5 rounded-2xl border border-slate-100 dark:border-slate-850">
                                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Poin & Tier Loyalty</span>
                                <p class="text-sm font-extrabold text-slate-800 dark:text-slate-100 mt-2.5">
                                    {{ $loyaltyInfo['current_point'] ?? 0 }} Poin | {{ $loyaltyInfo['tier'] ?? 'N/A' }}
                                </p>
                            </div>
                        </div>
                    </div>
                @endif

                <!-- Accounts Listing -->
                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-6 sm:p-8 shadow-sm">
                    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
                        <div>
                            <h3 class="text-lg font-bold text-slate-800 dark:text-slate-100">Daftar Sesi Nomor XL</h3>
                            <p class="text-xs text-slate-400 dark:text-slate-500 mt-1">Kelola dan pilih nomor XL aktif yang ingin dioperasikan.</p>
                        </div>
                        <button onclick="toggleModal('add-account-modal')" class="bg-blue-600 hover:bg-blue-700 active:scale-95 text-white px-4 py-2.5 rounded-2xl text-xs font-bold transition-all duration-200 flex items-center space-x-1.5 shadow-md shadow-blue-500/10">
                            <span>+ Tambah Akun</span>
                        </button>
                    </div>

                    @if($sessions->isEmpty())
                        <div class="text-center py-12 text-slate-400 dark:text-slate-500">
                            Belum ada akun XL terdaftar. Klik "+ Tambah Akun" untuk memulai.
                        </div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="border-b border-slate-100 dark:border-slate-800/80">
                                        <th class="pb-3 text-xs font-bold text-slate-400 uppercase">Label / MSISDN</th>
                                        <th class="pb-3 text-xs font-bold text-slate-400 uppercase">Tipe</th>
                                        <th class="pb-3 text-xs font-bold text-slate-400 uppercase">Status</th>
                                        <th class="pb-3 text-xs font-bold text-slate-400 uppercase text-right">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($sessions as $session)
                                        <tr class="border-b border-slate-50 dark:border-slate-800/40 hover:bg-slate-50/50 dark:hover:bg-slate-850/20 transition-all duration-150">
                                            <td class="py-4">
                                                <h5 class="font-bold text-slate-850 dark:text-slate-100 text-sm">{{ $session->label }}</h5>
                                                <p class="text-xs text-slate-400 dark:text-slate-500 mt-0.5">{{ $session->msisdn }}</p>
                                            </td>
                                            <td class="py-4 text-xs font-medium text-slate-500 dark:text-slate-400">
                                                {{ $session->subscription_type ?: 'PREPAID' }}
                                            </td>
                                            <td class="py-4">
                                                @if($session->is_active)
                                                    <span class="px-2.5 py-1 bg-emerald-50 dark:bg-emerald-950/20 border border-emerald-200/20 text-emerald-700 dark:text-emerald-400 rounded-full text-[10px] font-bold uppercase tracking-wider">Aktif</span>
                                                @else
                                                    <span class="px-2.5 py-1 bg-slate-50 dark:bg-slate-800/40 border border-slate-200/10 text-slate-500 dark:text-slate-400 rounded-full text-[10px] font-bold uppercase tracking-wider">Nonaktif</span>
                                                @endif
                                            </td>
                                            <td class="py-4 text-right">
                                                <div class="inline-flex items-center space-x-2">
                                                    @if(!$session->is_active)
                                                        <form action="{{ route('admin.tools.xl.active') }}" method="POST">
                                                            @csrf
                                                            <input type="hidden" name="session_id" value="{{ $session->id }}">
                                                            <button type="submit" class="px-3 py-1.5 bg-blue-50 dark:bg-blue-950/30 hover:bg-blue-600 hover:text-white text-blue-600 dark:text-blue-400 rounded-xl text-xs font-bold transition-all duration-200">
                                                                Aktifkan
                                                            </button>
                                                        </form>
                                                    @endif
                                                    <form action="{{ route('admin.tools.xl.delete', $session->id) }}" method="POST" onsubmit="return confirm('Hapus sesi untuk nomor ini?')">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="p-1.5 text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/20 rounded-xl transition-all duration-200" title="Hapus Akun">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>

            </div>

            <!-- MY PACKAGES (QUOTAS LIST) -->
            <div id="tab-quota" class="tab-content space-y-8 hidden">
                
                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-6 sm:p-8 shadow-sm">
                    <h3 class="text-lg font-bold text-slate-800 dark:text-slate-100 mb-6">Paket Aktif & Rincian Kuota</h3>
                    
                    @if(empty($activePackages))
                        <div class="text-center py-12 text-slate-400 dark:text-slate-500">
                            Tidak ada paket aktif yang ditemukan untuk nomor ini.
                        </div>
                    @else
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            @foreach($activePackages as $pkg)
                                <div class="bg-slate-50 dark:bg-slate-950 p-6 rounded-3xl border border-slate-100 dark:border-slate-850 flex flex-col justify-between space-y-6">
                                    <div class="flex items-start justify-between">
                                        <div>
                                            <span class="px-2 py-0.5 bg-blue-50 dark:bg-blue-950/30 text-blue-600 dark:text-blue-400 rounded-lg text-[9px] font-bold uppercase tracking-wider">
                                                {{ $pkg['product_domain'] }}
                                            </span>
                                            <h4 class="font-extrabold text-slate-800 dark:text-slate-100 text-sm mt-2">{{ $pkg['name'] }}</h4>
                                            <p class="text-[11px] text-slate-400 mt-1">
                                                Kedaluwarsa: {{ $pkg['expired_at'] ? date('d F Y | H:i', strtotime($pkg['expired_at'])) : 'N/A' }} WIB
                                            </p>
                                        </div>
                                        
                                        <!-- Unsubscribe Button -->
                                        @if($pkg['quota_code'])
                                            <form action="{{ route('admin.tools.xl.unsubscribe') }}" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin menonaktifkan paket ini? Tindakan ini tidak dapat dibatalkan.')">
                                                @csrf
                                                <input type="hidden" name="quota_code" value="{{ $pkg['quota_code'] }}">
                                                <input type="hidden" name="product_domain" value="{{ $pkg['product_domain'] }}">
                                                <input type="hidden" name="product_subscription_type" value="{{ $pkg['product_subscription_type'] }}">
                                                <button type="submit" class="px-2.5 py-1.5 text-[10px] font-bold bg-rose-50 dark:bg-rose-950/20 text-rose-600 dark:text-rose-450 hover:bg-rose-600 hover:text-white rounded-xl transition-all duration-200">
                                                    Stop Paket
                                                </button>
                                            </form>
                                        @endif
                                    </div>

                                    <!-- Progress quota bar -->
                                    <div class="space-y-2">
                                        <div class="flex justify-between text-xs font-bold">
                                            <span class="text-slate-400 dark:text-slate-500">Sisa Kuota</span>
                                            <span class="text-blue-600 dark:text-blue-400">{{ $pkg['quota_remaining'] }} / {{ $pkg['quota_total'] }}</span>
                                        </div>
                                        
                                        @php
                                            // Parse total and remaining quota (GB/MB string format) to calculate percentage
                                            $totalNum = (float) filter_var($pkg['quota_total'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
                                            $remNum = (float) filter_var($pkg['quota_remaining'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
                                            $pct = $totalNum > 0 ? min(100, max(0, ($remNum / $totalNum) * 100)) : 0;
                                        @endphp
                                        <div class="w-full bg-slate-200 dark:bg-slate-800 rounded-full h-2">
                                            <div class="bg-blue-600 h-2 rounded-full transition-all duration-500" style="width: {{ $pct }}%"></div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

            </div>

            <!-- BUY PACKAGES (STORE LIST) -->
            <div id="tab-store" class="tab-content space-y-8 hidden">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    
                    <!-- Direct Buy Form -->
                    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-6 sm:p-8 shadow-sm">
                        <h3 class="text-lg font-bold text-slate-800 dark:text-slate-100 mb-6">Pembelian Paket Baru</h3>
                        
                        <form action="{{ route('admin.tools.xl.purchase') }}" method="POST" class="space-y-5">
                            @csrf
                            
                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Metode Input</label>
                                <div class="grid grid-cols-2 gap-3">
                                    <label class="flex items-center space-x-2.5 p-3.5 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-850 rounded-2xl cursor-pointer">
                                        <input type="radio" name="purchase_type" value="option_code" checked onchange="togglePurchaseInputs('option_code')" class="text-blue-600 focus:ring-0">
                                        <span class="text-sm font-medium text-slate-700 dark:text-slate-300">Option Code</span>
                                    </label>
                                    <label class="flex items-center space-x-2.5 p-3.5 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-850 rounded-2xl cursor-pointer">
                                        <input type="radio" name="purchase_type" value="family_code" onchange="togglePurchaseInputs('family_code')" class="text-blue-600 focus:ring-0">
                                        <span class="text-sm font-medium text-slate-700 dark:text-slate-300">Family Code</span>
                                    </label>
                                </div>
                            </div>

                            <div id="input-option-code-container">
                                <label for="option_code" class="block text-xs font-bold text-slate-500 uppercase mb-2">Option Code (ID Paket)</label>
                                <input type="text" id="option_code" name="option_code" placeholder="Contoh: 5110376" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 focus:border-blue-500 focus:bg-white focus:outline-none rounded-2xl text-sm font-medium text-slate-850 dark:text-slate-100 transition-all duration-200">
                            </div>

                            <div id="input-family-code-container" class="hidden">
                                <label for="family_code" class="block text-xs font-bold text-slate-500 uppercase mb-2">Family Code</label>
                                <input type="text" id="family_code" name="family_code" placeholder="Contoh: b0a20d74-0c54-4e3b-8f3f-01e7482e50bf" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 focus:border-blue-500 focus:bg-white focus:outline-none rounded-2xl text-sm font-medium text-slate-850 dark:text-slate-100 transition-all duration-200">
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Metode Pembayaran</label>
                                <div class="grid grid-cols-2 gap-3">
                                    <label class="flex items-center space-x-2.5 p-3.5 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-850 rounded-2xl cursor-pointer">
                                        <input type="radio" name="payment_method" value="balance" checked class="text-blue-600 focus:ring-0">
                                        <span class="text-sm font-medium text-slate-700 dark:text-slate-300">Potong Pulsa</span>
                                    </label>
                                    <label class="flex items-center space-x-2.5 p-3.5 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-850 rounded-2xl cursor-pointer">
                                        <input type="radio" name="payment_method" value="qris" class="text-blue-600 focus:ring-0">
                                        <span class="text-sm font-medium text-slate-700 dark:text-slate-300">QRIS Dinamis</span>
                                    </label>
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Decoy Bypass (Tembak Paket)</label>
                                <div class="grid grid-cols-2 gap-3">
                                    <label class="flex items-center space-x-2.5 p-3.5 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-850 rounded-2xl cursor-pointer">
                                        <input type="radio" name="use_decoy" value="1" checked class="text-blue-600 focus:ring-0">
                                        <span class="text-sm font-medium text-slate-700 dark:text-slate-300">Bypass Aktif</span>
                                    </label>
                                    <label class="flex items-center space-x-2.5 p-3.5 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-850 rounded-2xl cursor-pointer">
                                        <input type="radio" name="use_decoy" value="0" class="text-blue-600 focus:ring-0">
                                        <span class="text-sm font-medium text-slate-700 dark:text-slate-300">Tanpa Bypass</span>
                                    </label>
                                </div>
                            </div>

                            <button type="submit" class="w-full py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-2xl text-sm font-bold shadow-md shadow-blue-500/10 active:scale-98 transition-all duration-200">
                                Konfirmasi Pembelian
                            </button>
                        </form>
                    </div>

                    <!-- Shortcut Promo HOT Packages -->
                    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-6 sm:p-8 shadow-sm">
                        <h3 class="text-lg font-bold text-slate-800 dark:text-slate-100 mb-6">Shortcut Promo HOT</h3>
                        
                        <div class="space-y-3">
                            @foreach($hotPromoPackages as $promo)
                                <div class="flex items-center justify-between p-4 bg-slate-50 dark:bg-slate-950 border border-slate-100 dark:border-slate-850 rounded-2xl">
                                    <div>
                                        <h5 class="font-bold text-sm text-slate-800 dark:text-slate-100">{{ $promo['name'] }}</h5>
                                        <p class="text-[11px] text-slate-400 mt-1">
                                            Code: <code class="bg-slate-200 dark:bg-slate-800/80 px-1 py-0.5 rounded text-blue-600 dark:text-blue-400 font-mono text-[10px]">{{ $promo['option_code'] }}</code> | Price: Rp {{ number_format($promo['price']) }}
                                        </p>
                                    </div>
                                    <button onclick="fillOptionCode('{{ $promo['option_code'] }}')" class="px-3 py-1.5 bg-blue-600 text-white hover:bg-blue-700 rounded-xl text-xs font-bold transition-all duration-200 active:scale-95">
                                        Pilih
                                    </button>
                                </div>
                            @endforeach
                        </div>
                    </div>

                </div>

            </div>

            <!-- AKRAB ORGANIZER (FAMILY PLAN MANAGER) -->
            <div id="tab-akrab" class="tab-content space-y-8 hidden">
                
                @if(!$familyData || ($familyData['member_info']['plan_type'] ?? '') === '')
                    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-8 text-center text-slate-400 dark:text-slate-500">
                        Nomor aktif Anda bukan merupakan penyelenggara paket keluarga (Family Plan Organizer / Paket Akrab).
                    </div>
                @else
                    @php
                        $mi = $familyData['member_info'];
                        $totalQ = $mi['total_quota'] ?? 1;
                        $remQ = $mi['remaining_quota'] ?? 0;
                        $sharedPct = min(100, max(0, ($remQ / $totalQ) * 100));
                        
                        // Human readable calculation for general info
                        $totalHuman = number_format($totalQ / (1024*1024*1024), 2) . ' GB';
                        $remHuman = number_format($remQ / (1024*1024*1024), 2) . ' GB';
                    @endphp

                    <!-- Akrab Overview shared quota -->
                    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-6 sm:p-8 shadow-sm space-y-6">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                            <div>
                                <span class="px-2.5 py-1 bg-blue-50 dark:bg-blue-950/30 text-blue-600 dark:text-blue-400 rounded-lg text-xs font-bold uppercase tracking-wider">Penyelenggara</span>
                                <h3 class="text-xl font-bold text-slate-800 dark:text-slate-100 mt-2">Paket Akrab: {{ $mi['plan_type'] }}</h3>
                            </div>
                            <div class="text-right">
                                <span class="text-xs text-slate-400">Habis Masa Berlaku</span>
                                <p class="text-sm font-bold text-slate-800 dark:text-slate-100 mt-0.5">
                                    {{ isset($mi['end_date']) ? date('d F Y', $mi['end_date']) : 'N/A' }}
                                </p>
                            </div>
                        </div>

                        <!-- shared quota progress -->
                        <div class="space-y-2">
                            <div class="flex justify-between text-xs font-bold">
                                <span class="text-slate-400 dark:text-slate-500">Kuota Bersama Akrab</span>
                                <span class="text-blue-600 dark:text-blue-400">{{ $remHuman }} / {{ $totalHuman }} Tersisa</span>
                            </div>
                            <div class="w-full bg-slate-200 dark:bg-slate-800 rounded-full h-3">
                                <div class="bg-blue-600 h-3 rounded-full transition-all duration-500" style="width: {{ $sharedPct }}%"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Slots listing -->
                    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-6 sm:p-8 shadow-sm">
                        <h3 class="text-lg font-bold text-slate-800 dark:text-slate-100 mb-6">Anggota Terdaftar & Slot Kuota</h3>
                        
                        <div class="space-y-4">
                            @foreach($mi['members'] as $idx => $member)
                                <div class="p-5 bg-slate-50 dark:bg-slate-950 border border-slate-100 dark:border-slate-850 rounded-2xl flex flex-col md:flex-row md:items-center justify-between gap-6">
                                    <div class="space-y-3 flex-1">
                                        <div class="flex items-center space-x-2">
                                            <span class="w-6 h-6 rounded-full bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-350 text-xs font-extrabold flex items-center justify-center">
                                                {{ $idx + 1 }}
                                            </span>
                                            @if($member['msisdn'])
                                                <h4 class="font-extrabold text-sm text-slate-800 dark:text-slate-100">{{ $member['alias'] }}</h4>
                                                <span class="text-xs text-slate-400">({{ $member['msisdn'] }})</span>
                                            @else
                                                <h4 class="font-bold text-sm text-slate-400 italic">Slot Kosong</h4>
                                            @endif
                                        </div>

                                        @if($member['msisdn'])
                                            @php
                                                $allocated = $member['usage']['quota_allocated'] ?? 0;
                                                $used = $member['usage']['quota_used'] ?? 0;
                                                $rem = max(0, $allocated - $used);
                                                $allocHuman = $allocated > 0 ? number_format($allocated / (1024*1024*1024), 2) . ' GB' : 'Unlimited';
                                                $usedHuman = number_format($used / (1024*1024*1024), 2) . ' GB';
                                                $memberPct = $allocated > 0 ? min(100, max(0, ($used / $allocated) * 100)) : 0;
                                            @endphp
                                            <div class="space-y-1">
                                                <div class="flex justify-between text-[11px] font-bold text-slate-400">
                                                    <span>Pemakaian Kuota</span>
                                                    <span>{{ $usedHuman }} / {{ $allocHuman }}</span>
                                                </div>
                                                <div class="w-full bg-slate-200 dark:bg-slate-800 rounded-full h-1.5">
                                                    <div class="bg-indigo-600 h-1.5 rounded-full" style="width: {{ $memberPct }}%"></div>
                                                </div>
                                            </div>
                                        @endif
                                    </div>

                                    <div class="flex flex-wrap items-center gap-2">
                                        @if($member['msisdn'])
                                            <!-- Set quota limit button -->
                                            <button onclick="openQuotaLimitModal('{{ $member['family_member_id'] }}', '{{ $member['usage']['quota_allocated'] }}')" class="px-3 py-1.5 bg-blue-50 dark:bg-blue-950/30 text-blue-600 dark:text-blue-450 hover:bg-blue-600 hover:text-white rounded-xl text-xs font-bold transition-all duration-200">
                                                Limit Kuota
                                            </button>

                                            <!-- Remove member button -->
                                            <form action="{{ route('admin.tools.xl.family.member.remove') }}" method="POST" onsubmit="return confirm('Keluarkan anggota ini dari grup Akrab?')">
                                                @csrf
                                                <input type="hidden" name="family_member_id" value="{{ $member['family_member_id'] }}">
                                                <button type="submit" class="px-3 py-1.5 bg-rose-50 dark:bg-rose-950/20 text-rose-600 hover:bg-rose-600 hover:text-white rounded-xl text-xs font-bold transition-all duration-200">
                                                    Keluarkan
                                                </button>
                                            </form>
                                        @else
                                            <!-- Add/Change member button -->
                                            <button onclick="openAddMemberModal('{{ $member['slot_id'] }}', '{{ $member['family_member_id'] }}')" class="px-3 py-1.5 bg-emerald-50 dark:bg-emerald-950/20 text-emerald-600 hover:bg-emerald-600 hover:text-white rounded-xl text-xs font-bold transition-all duration-200">
                                                Undang Anggota
                                            </button>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

            </div>

            <!-- TRANSACTION HISTORY -->
            <div id="tab-history" class="tab-content space-y-8 hidden">
                
                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-6 sm:p-8 shadow-sm">
                    <h3 class="text-lg font-bold text-slate-800 dark:text-slate-100 mb-6">Riwayat Transaksi MyXL</h3>
                    
                    @if(empty($transactions) || empty($transactions['list']))
                        <div class="text-center py-12 text-slate-400 dark:text-slate-500">
                            Tidak ada riwayat transaksi yang ditemukan.
                        </div>
                    @else
                        <div class="space-y-4">
                            @foreach($transactions['list'] as $trx)
                                <div class="p-4 bg-slate-50 dark:bg-slate-950 border border-slate-100 dark:border-slate-850 rounded-2xl flex items-center justify-between">
                                    <div>
                                        <h5 class="font-extrabold text-sm text-slate-800 dark:text-slate-100">{{ $trx['title'] }}</h5>
                                        <p class="text-[11px] text-slate-400 mt-1">
                                            {{ $trx['formated_date'] }} | Metode: {{ $trx['payment_method_label'] }}
                                        </p>
                                    </div>
                                    <div class="text-right">
                                        <span class="text-sm font-black text-slate-800 dark:text-slate-100">{{ $trx['price'] }}</span>
                                        <div class="mt-1">
                                            @if($trx['payment_status'] === 'SUCCESS')
                                                <span class="text-[9px] font-bold text-emerald-500 uppercase tracking-wider">Sukses</span>
                                            @else
                                                <span class="text-[9px] font-bold text-rose-500 uppercase tracking-wider">{{ $trx['payment_status'] }}</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

            </div>

            <!-- API SETTINGS -->
            <div id="tab-settings" class="tab-content space-y-8 hidden">
                
                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-6 sm:p-8 shadow-sm">
                    <h3 class="text-lg font-bold text-slate-800 dark:text-slate-100 mb-6">Konfigurasi API MyXL & CIAM</h3>
                    
                    <form action="{{ route('admin.tools.xl.settings.update') }}" method="POST" class="space-y-6">
                        @csrf
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="myxl_api_base_url" class="block text-xs font-bold text-slate-500 uppercase mb-2">MyXL API Base URL</label>
                                <input type="url" id="myxl_api_base_url" name="myxl_api_base_url" value="{{ $baseUrl }}" required class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 focus:border-blue-500 focus:bg-white focus:outline-none rounded-2xl text-sm font-medium text-slate-850 dark:text-slate-100 transition-all duration-200">
                            </div>

                            <div>
                                <label for="myxl_simulation_mode" class="block text-xs font-bold text-slate-500 uppercase mb-2">Mode Simulasi (Bypass real API)</label>
                                <select id="myxl_simulation_mode" name="myxl_simulation_mode" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 focus:border-blue-500 focus:bg-white focus:outline-none rounded-2xl text-sm font-medium text-slate-850 dark:text-slate-100 transition-all duration-200">
                                    <option value="0" {{ !$isSimMode ? 'selected' : '' }}>Real API Mode (Menggunakan Kunci API Asli)</option>
                                    <option value="1" {{ $isSimMode ? 'selected' : '' }}>Simulation Mode (Mode Uji Coba Simulasi)</option>
                                </select>
                            </div>
                        </div>

                        <div class="border-t border-slate-100 dark:border-slate-800 my-6 pt-6">
                            <h4 class="text-sm font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-4">Parameter Kredensial & Kunci Kriptografi</h4>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-xs font-bold text-slate-400 dark:text-slate-500 uppercase mb-2">CIAM ForgeRock Base URL</label>
                                    <input type="url" name="myxl_base_ciam_url" value="{{ $credentials['base_ciam_url'] ?? '' }}" placeholder="https://gede.ciam.xlaxiata.co.id" class="w-full px-3 py-2.5 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 focus:border-blue-500 focus:outline-none rounded-xl text-sm font-mono text-slate-850 dark:text-slate-100 transition-all duration-200">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-slate-400 dark:text-slate-500 uppercase mb-2">CIAM BASIC_AUTH</label>
                                    <input type="text" name="myxl_basic_auth" value="{{ $credentials['basic_auth'] ?? '' }}" class="w-full px-3 py-2.5 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 focus:border-blue-500 focus:outline-none rounded-xl text-sm font-mono text-slate-850 dark:text-slate-100 transition-all duration-200">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-slate-400 dark:text-slate-500 uppercase mb-2">API_KEY</label>
                                    <input type="text" name="myxl_api_key" value="{{ $credentials['api_key'] ?? '' }}" class="w-full px-3 py-2.5 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 focus:border-blue-500 focus:outline-none rounded-xl text-sm font-mono text-slate-850 dark:text-slate-100 transition-all duration-200">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-slate-400 dark:text-slate-500 uppercase mb-2">AX_FP_KEY (Fingeprint Secret Key)</label>
                                    <input type="text" name="myxl_ax_fp_key" value="{{ $credentials['ax_fp_key'] ?? '' }}" class="w-full px-3 py-2.5 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 focus:border-blue-500 focus:outline-none rounded-xl text-sm font-mono text-slate-850 dark:text-slate-100 transition-all duration-200">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-slate-400 dark:text-slate-500 uppercase mb-2">XDATA_KEY (AES Secret Key)</label>
                                    <input type="text" name="myxl_xdata_key" value="{{ $credentials['xdata_key'] ?? '' }}" class="w-full px-3 py-2.5 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 focus:border-blue-500 focus:outline-none rounded-xl text-sm font-mono text-slate-850 dark:text-slate-100 transition-all duration-200">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-slate-400 dark:text-slate-500 uppercase mb-2">AX_API_SIG_KEY (Hmac Api Sig Key)</label>
                                    <input type="text" name="myxl_ax_api_sig_key" value="{{ $credentials['ax_api_sig_key'] ?? '' }}" class="w-full px-3 py-2.5 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 focus:border-blue-500 focus:outline-none rounded-xl text-sm font-mono text-slate-850 dark:text-slate-100 transition-all duration-200">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-slate-400 dark:text-slate-500 uppercase mb-2">X_API_BASE_SECRET</label>
                                    <input type="text" name="myxl_x_api_base_secret" value="{{ $credentials['x_api_base_secret'] ?? '' }}" class="w-full px-3 py-2.5 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 focus:border-blue-500 focus:outline-none rounded-xl text-sm font-mono text-slate-850 dark:text-slate-100 transition-all duration-200">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-slate-400 dark:text-slate-500 uppercase mb-2">ENCRYPTED_FIELD_KEY</label>
                                    <input type="text" name="myxl_encrypted_field_key" value="{{ $credentials['encrypted_field_key'] ?? '' }}" class="w-full px-3 py-2.5 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 focus:border-blue-500 focus:outline-none rounded-xl text-sm font-mono text-slate-850 dark:text-slate-100 transition-all duration-200">
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-end">
                            <button type="submit" class="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-2xl text-sm font-bold shadow-md shadow-blue-500/10 active:scale-95 transition-all duration-200">
                                Simpan Konfigurasi
                            </button>
                        </div>
                    </form>
                </div>

            </div>

        </div>

    </div>

</div>

<!-- ADD ACCOUNT MODAL (OTP POPUP) -->
<div id="add-account-modal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 dark:bg-slate-950/70 p-4 transition-all duration-300 hidden">
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 w-full max-w-md rounded-3xl p-6 sm:p-8 space-y-6 shadow-xl relative animate-in fade-in zoom-in-95 duration-200">
        
        <button onclick="toggleModal('add-account-modal')" class="absolute top-4 right-4 text-slate-400 hover:text-slate-650 dark:hover:text-slate-200">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
        </button>

        <div class="space-y-2">
            <h3 class="text-lg font-bold text-slate-850 dark:text-slate-100">Tambah Akun XL Baru</h3>
            <p class="text-xs text-slate-400 dark:text-slate-500">Gunakan OTP SMS untuk menghubungkan token otentikasi XL.</p>
        </div>

        <div class="space-y-4">
            <div>
                <label for="otp_phone" class="block text-xs font-bold text-slate-500 uppercase mb-2">Nomor HP XL</label>
                <input type="text" id="otp_phone" placeholder="Contoh: 087860356425" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 focus:border-blue-500 focus:outline-none rounded-2xl text-sm font-medium text-slate-800 dark:text-slate-100 transition-all duration-200">
            </div>

            <div>
                <label for="otp_label" class="block text-xs font-bold text-slate-500 uppercase mb-2">Label Akun (Opsional)</label>
                <input type="text" id="otp_label" placeholder="Contoh: XL Utama" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 focus:border-blue-500 focus:outline-none rounded-2xl text-sm font-medium text-slate-800 dark:text-slate-100 transition-all duration-200">
            </div>

            <!-- OTP Input field (Initially Hidden) -->
            <div id="otp-code-container" class="hidden">
                <label for="otp_code" class="block text-xs font-bold text-slate-500 uppercase mb-2">Kode OTP SMS</label>
                <input type="text" id="otp_code" placeholder="6 Digit Kode" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 focus:border-blue-500 focus:outline-none rounded-2xl text-sm font-medium text-slate-800 dark:text-slate-100 transition-all duration-200">
            </div>

            <div id="otp-error" class="hidden text-xs font-bold text-rose-500"></div>
            <div id="otp-success" class="hidden text-xs font-bold text-emerald-500"></div>

            <div class="pt-2">
                <button id="otp-request-btn" onclick="sendOtpRequest()" class="w-full py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-2xl text-xs font-bold shadow-md shadow-blue-500/10 transition-all duration-200 active:scale-98">
                    Kirim OTP SMS
                </button>
                <button id="otp-verify-btn" onclick="verifyOtpRequest()" class="w-full py-3 bg-emerald-600 hover:bg-emerald-700 text-white rounded-2xl text-xs font-bold shadow-md shadow-emerald-500/10 transition-all duration-200 active:scale-98 hidden">
                    Verifikasi OTP
                </button>
            </div>
        </div>

    </div>
</div>

<!-- ADD MEMBER MODAL (FAMILY PLAN GROUP) -->
<div id="add-member-modal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 dark:bg-slate-950/70 p-4 transition-all duration-300 hidden">
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 w-full max-w-md rounded-3xl p-6 sm:p-8 space-y-6 shadow-xl relative animate-in fade-in zoom-in-95 duration-200">
        
        <button onclick="toggleModal('add-member-modal')" class="absolute top-4 right-4 text-slate-400 hover:text-slate-650 dark:hover:text-slate-200">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
        </button>

        <div class="space-y-2">
            <h3 class="text-lg font-bold text-slate-850 dark:text-slate-100">Undang Anggota Akrab</h3>
            <p class="text-xs text-slate-400 dark:text-slate-500">Tambahkan nomor HP baru untuk mengisi slot kuota bersama.</p>
        </div>

        <form action="{{ route('admin.tools.xl.family.member') }}" method="POST" class="space-y-4">
            @csrf
            <input type="hidden" id="family_slot_id" name="slot_id">
            <input type="hidden" id="family_member_id" name="family_member_id">

            <div>
                <label for="parent_alias" class="block text-xs font-bold text-slate-500 uppercase mb-2">Alias Anda (Penyelenggara)</label>
                <input type="text" id="parent_alias" name="parent_alias" value="Organizer" required class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 focus:border-blue-500 focus:outline-none rounded-2xl text-sm font-medium text-slate-800 dark:text-slate-100 transition-all duration-200">
            </div>

            <div>
                <label for="child_alias" class="block text-xs font-bold text-slate-500 uppercase mb-2">Alias Anggota Baru</label>
                <input type="text" id="child_alias" name="child_alias" placeholder="Contoh: Anak / Istri" required class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 focus:border-blue-500 focus:outline-none rounded-2xl text-sm font-medium text-slate-800 dark:text-slate-100 transition-all duration-200">
            </div>

            <div>
                <label for="target_msisdn" class="block text-xs font-bold text-slate-500 uppercase mb-2">Nomor HP Anggota Baru (Format 62)</label>
                <input type="text" id="target_msisdn" name="target_msisdn" placeholder="Contoh: 62878..." required class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 focus:border-blue-500 focus:outline-none rounded-2xl text-sm font-medium text-slate-800 dark:text-slate-100 transition-all duration-200">
            </div>

            <div class="pt-2">
                <button type="submit" class="w-full py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-2xl text-xs font-bold shadow-md shadow-blue-500/10 transition-all duration-200 active:scale-98">
                    Kirim Undangan Akrab
                </button>
            </div>
        </form>

    </div>
</div>

<!-- SET QUOTA LIMIT MODAL (FAMILY PLAN LIMITS) -->
<div id="set-quota-modal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 dark:bg-slate-950/70 p-4 transition-all duration-300 hidden">
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 w-full max-w-md rounded-3xl p-6 sm:p-8 space-y-6 shadow-xl relative animate-in fade-in zoom-in-95 duration-200">
        
        <button onclick="toggleModal('set-quota-modal')" class="absolute top-4 right-4 text-slate-400 hover:text-slate-650 dark:hover:text-slate-200">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
        </button>

        <div class="space-y-2">
            <h3 class="text-lg font-bold text-slate-850 dark:text-slate-100">Batasi Limit Kuota Bersama</h3>
            <p class="text-xs text-slate-400 dark:text-slate-500">Tentukan batas kuota maksimum (limit) untuk anggota grup.</p>
        </div>

        <form action="{{ route('admin.tools.xl.family.quota') }}" method="POST" class="space-y-4">
            @csrf
            <input type="hidden" id="quota_member_id" name="family_member_id">
            <input type="hidden" id="original_allocation" name="original_allocation">

            <div>
                <label for="quota_limit_mb" class="block text-xs font-bold text-slate-500 uppercase mb-2">Limit Kuota (dalam MB)</label>
                <input type="number" id="quota_limit_mb" name="quota_limit_mb" placeholder="Contoh: 5120 (5 GB)" required class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 focus:border-blue-500 focus:outline-none rounded-2xl text-sm font-medium text-slate-800 dark:text-slate-100 transition-all duration-200">
            </div>

            <div class="pt-2">
                <button type="submit" class="w-full py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-2xl text-xs font-bold shadow-md shadow-blue-500/10 transition-all duration-200 active:scale-98">
                    Perbarui Batasan Kuota
                </button>
            </div>
        </form>

    </div>
</div>

<script>
    // Tab switching logic
    function switchTab(tabId) {
        document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));
        document.getElementById(tabId).classList.remove('hidden');

        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.classList.remove('bg-blue-50', 'dark:bg-blue-950/30', 'text-blue-600', 'dark:text-blue-400');
            btn.classList.add('text-slate-600', 'dark:text-slate-400', 'hover:bg-slate-50', 'dark:hover:bg-slate-800/80');
        });

        const activeBtn = document.getElementById('btn-' + tabId);
        activeBtn.classList.remove('text-slate-600', 'dark:text-slate-400', 'hover:bg-slate-50', 'dark:hover:bg-slate-800/80');
        activeBtn.classList.add('bg-blue-50', 'dark:bg-blue-950/30', 'text-blue-600', 'dark:text-blue-400');
    }

    // Modal display toggle
    function toggleModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal.classList.contains('hidden')) {
            modal.classList.remove('hidden');
        } else {
            modal.classList.add('hidden');
        }
    }

    // Toggle Option vs Family inputs inside store tab
    function togglePurchaseInputs(type) {
        if (type === 'option_code') {
            document.getElementById('input-option-code-container').classList.remove('hidden');
            document.getElementById('input-family-code-container').classList.add('hidden');
        } else {
            document.getElementById('input-option-code-container').classList.add('hidden');
            document.getElementById('input-family-code-container').classList.remove('hidden');
        }
    }

    // Autofill option code from shortcut promos
    function fillOptionCode(code) {
        document.getElementById('option_code').value = code;
        switchTab('tab-store');
        togglePurchaseInputs('option_code');
        document.querySelector('input[name="purchase_type"][value="option_code"]').checked = true;
    }

    // Open Add Member modal (Akrab organizer)
    function openAddMemberModal(slotId, familyMemberId) {
        document.getElementById('family_slot_id').value = slotId;
        document.getElementById('family_member_id').value = familyMemberId;
        toggleModal('add-member-modal');
    }

    // Open Quota limit modal (Akrab organizer)
    function openQuotaLimitModal(familyMemberId, originalAllocation) {
        document.getElementById('quota_member_id').value = familyMemberId;
        document.getElementById('original_allocation').value = originalAllocation;
        
        // Populate current allocation value converted to MB
        const currentMb = Math.round(originalAllocation / (1024 * 1024));
        document.getElementById('quota_limit_mb').value = currentMb > 0 ? currentMb : '';
        
        toggleModal('set-quota-modal');
    }

    // JS fetch function to request OTP SMS
    function sendOtpRequest() {
        const phone = document.getElementById('otp_phone').value;
        const errDiv = document.getElementById('otp-error');
        const successDiv = document.getElementById('otp-success');
        
        errDiv.classList.add('hidden');
        successDiv.classList.add('hidden');

        if (!phone) {
            errDiv.innerText = 'Nomor HP tidak boleh kosong.';
            errDiv.classList.remove('hidden');
            return;
        }

        const btn = document.getElementById('otp-request-btn');
        btn.disabled = true;
        btn.innerText = 'Mengirim...';

        fetch("{{ route('admin.tools.xl.otp.request') }}", {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({ phone: phone })
        })
        .then(response => response.json())
        .then(data => {
            btn.disabled = false;
            btn.innerText = 'Kirim Ulang OTP';
            if (data.success) {
                successDiv.innerText = data.message;
                successDiv.classList.remove('hidden');
                document.getElementById('otp-code-container').classList.remove('hidden');
                document.getElementById('otp-verify-btn').classList.remove('hidden');
                btn.classList.add('hidden');
            } else {
                errDiv.innerText = data.message;
                errDiv.classList.remove('hidden');
            }
        })
        .catch(err => {
            btn.disabled = false;
            btn.innerText = 'Kirim OTP SMS';
            errDiv.innerText = 'Koneksi error. Silakan coba kembali.';
            errDiv.classList.remove('hidden');
        });
    }

    // JS fetch function to verify OTP
    function verifyOtpRequest() {
        const phone = document.getElementById('otp_phone').value;
        const otp = document.getElementById('otp_code').value;
        const label = document.getElementById('otp_label').value;
        const errDiv = document.getElementById('otp-error');
        const successDiv = document.getElementById('otp-success');

        errDiv.classList.add('hidden');
        successDiv.classList.add('hidden');

        if (!otp) {
            errDiv.innerText = 'OTP tidak boleh kosong.';
            errDiv.classList.remove('hidden');
            return;
        }

        const btn = document.getElementById('otp-verify-btn');
        btn.disabled = true;
        btn.innerText = 'Memverifikasi...';

        fetch("{{ route('admin.tools.xl.otp.verify') }}", {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({ phone: phone, otp: otp, label: label })
        })
        .then(response => response.json())
        .then(data => {
            btn.disabled = false;
            btn.innerText = 'Verifikasi OTP';
            if (data.success) {
                toggleModal('add-account-modal');
                location.reload();
            } else {
                errDiv.innerText = data.message;
                errDiv.classList.remove('hidden');
            }
        })
        .catch(err => {
            btn.disabled = false;
            btn.innerText = 'Verifikasi OTP';
            errDiv.innerText = 'Koneksi error. Silakan coba kembali.';
            errDiv.classList.remove('hidden');
        });
    }
</script>
@endsection
