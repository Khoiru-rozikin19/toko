@extends('layouts.app')

@section('content')
<div class="space-y-8">
    
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-5">
        <div>
            <h2 class="text-2xl sm:text-3xl font-extrabold text-slate-800 dark:text-slate-100 tracking-tight">Saldo Akun</h2>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Kelola dan isi ulang saldo untuk berbelanja produk</p>
        </div>
    </div>

    <!-- Alert Success/Error -->
    @if(session('success'))
        <div class="p-4 bg-emerald-50 dark:bg-emerald-950/20 border border-emerald-200 dark:border-emerald-900/50 rounded-2xl text-emerald-800 dark:text-emerald-400 text-sm flex items-center space-x-2">
            <svg class="w-5 h-5 text-emerald-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            <span>{{ session('success') }}</span>
        </div>
    @endif
    @if(session('error'))
        <div class="p-4 bg-rose-50 dark:bg-rose-950/20 border border-rose-200 dark:border-rose-900/50 rounded-2xl text-rose-800 dark:text-rose-400 text-sm flex items-center space-x-2">
            <svg class="w-5 h-5 text-rose-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
            <span>{{ session('error') }}</span>
        </div>
    @endif

    <!-- Balance Grid Info -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <!-- Current Balance Card -->
        <div class="bg-gradient-to-br from-blue-600 to-indigo-600 rounded-3xl p-6 text-white shadow-xl shadow-blue-500/10 flex flex-col justify-between relative overflow-hidden h-52 lg:col-span-2">
            <!-- Background Decoration -->
            <div class="absolute -right-10 -top-10 w-40 h-40 bg-white/10 rounded-full blur-2xl"></div>
            <div class="absolute -left-10 -bottom-10 w-36 h-36 bg-blue-500/30 rounded-full blur-2xl"></div>
            
            <div>
                <span class="text-xs uppercase tracking-widest text-blue-200/80 font-semibold">Total Saldo Saat Ini</span>
                <h1 class="text-4xl sm:text-5xl font-extrabold tracking-tight mt-2">Rp {{ number_format($currentBalance, 0, ',', '.') }}</h1>
            </div>
            
            <div class="flex items-center justify-between border-t border-white/10 pt-4 mt-4">
                <span class="text-xs text-blue-100/70">Akun: {{ auth()->user()->name }}</span>
                <button type="button" onclick="openTopupModal()" class="px-5 py-2.5 bg-white text-blue-600 hover:bg-blue-50 transition font-bold rounded-2xl text-xs flex items-center space-x-2 shadow-lg shadow-black/10">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"></path></svg>
                    <span>Isi Saldo</span>
                </button>
            </div>
        </div>

        <!-- QRIS Info / Static instructions -->
        <div class="bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 rounded-3xl p-6 flex flex-col justify-between shadow-sm">
            <div>
                <h3 class="font-bold text-slate-800 dark:text-slate-100 text-lg mb-2">Metode Pembayaran QRIS</h3>
                <p class="text-xs text-slate-500 dark:text-slate-405 leading-relaxed">
                    Sistem topup menggunakan QRIS otomatis. Masukkan nominal topup, lakukan pembayaran sesuai nominal total (termasuk kode unik) agar saldo otomatis masuk ke akun Anda dalam hitungan detik.
                </p>
            </div>
            <div class="mt-4 pt-4 border-t border-slate-100 dark:border-slate-800 flex items-center space-x-3 text-xs text-blue-600 dark:text-blue-400 font-semibold">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                <span>Diproses Secara Otomatis & Aman</span>
            </div>
        </div>
    </div>

    <!-- Active Pending Topup (If any) -->
    @php
        $activeTopup = null;
        foreach ($pendingTopups as $tx) {
            $order = \App\Models\Order::find($tx->reference_id);
            if ($order && !$order->isExpired()) {
                $activeTopup = $order;
                break;
            }
        }
    @endphp

    @if($activeTopup)
        <div class="bg-amber-50 dark:bg-amber-950/20 border border-amber-200 dark:border-amber-900/50 rounded-3xl p-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="flex items-start space-x-3.5">
                <div class="p-3 bg-amber-100 dark:bg-amber-900/40 rounded-2xl text-amber-600 dark:text-amber-400">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
                <div>
                    <h4 class="font-bold text-slate-800 dark:text-slate-100 text-sm">Menunggu Pembayaran Top Up</h4>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                        Anda memiliki permintaan topup sebesar <strong>Rp {{ number_format($activeTopup->base_amount, 0, ',', '.') }}</strong> dengan total bayar <strong>Rp {{ number_format($activeTopup->total_amount, 0, ',', '.') }}</strong> yang masih aktif.
                    </p>
                </div>
            </div>
            <div>
                <button type="button" onclick="resumeTopup('{{ $activeTopup->id }}', {{ $activeTopup->total_amount }}, '{{ rawurlencode($activeTopup->qris_payload) }}', '{{ $activeTopup->expired_at->toIso8601String() }}', '{{ \Carbon\Carbon::now()->toIso8601String() }}')" class="px-5 py-2.5 bg-amber-500 hover:bg-amber-600 text-white font-bold rounded-2xl text-xs transition shadow-md shadow-amber-500/10">
                    Bayar Sekarang
                </button>
            </div>
        </div>
    @endif

    <!-- Transaction History -->
    <div class="bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 rounded-3xl shadow-sm overflow-hidden">
        <div class="p-6 border-b border-slate-100 dark:border-slate-800 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <h3 class="font-bold text-slate-850 dark:text-slate-100 text-lg">Riwayat Saldo</h3>
            
            <!-- Tabs filters -->
            <div class="flex items-center space-x-1.5 bg-slate-100 dark:bg-slate-800/80 p-1 rounded-2xl overflow-x-auto">
                <a href="{{ route('balance.index', ['tab' => 'all']) }}" class="px-4 py-1.5 rounded-xl text-xs font-bold transition {{ $tab === 'all' ? 'bg-white dark:bg-slate-900 text-blue-600 dark:text-blue-400 shadow-sm' : 'text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-300' }}">
                    Semua
                </a>
                <a href="{{ route('balance.index', ['tab' => 'topup']) }}" class="px-4 py-1.5 rounded-xl text-xs font-bold transition {{ $tab === 'topup' ? 'bg-white dark:bg-slate-900 text-blue-600 dark:text-blue-400 shadow-sm' : 'text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-300' }}">
                    Top Up
                </a>
                <a href="{{ route('balance.index', ['tab' => 'purchase']) }}" class="px-4 py-1.5 rounded-xl text-xs font-bold transition {{ $tab === 'purchase' ? 'bg-white dark:bg-slate-900 text-blue-600 dark:text-blue-400 shadow-sm' : 'text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-300' }}">
                    Pembelian
                </a>
                <a href="{{ route('balance.index', ['tab' => 'transfer']) }}" class="px-4 py-1.5 rounded-xl text-xs font-bold transition {{ $tab === 'transfer' ? 'bg-white dark:bg-slate-900 text-blue-600 dark:text-blue-400 shadow-sm' : 'text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-300' }}">
                    Transfer
                </a>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 dark:bg-slate-950/40 text-xs font-bold text-slate-400 dark:text-slate-500 border-b border-slate-100 dark:border-slate-800">
                        <th class="p-6">Waktu</th>
                        <th class="p-6">Deskripsi</th>
                        <th class="p-6">Tipe</th>
                        <th class="p-6 text-right">Nominal</th>
                        <th class="p-6 text-right">Saldo Akhir</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-sm">
                    @forelse($transactions as $tx)
                        <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-950/20 transition-colors">
                            <td class="p-6 text-slate-500 dark:text-slate-400 whitespace-nowrap">
                                {{ $tx->created_at->isoFormat('D MMM YYYY, HH:mm') }}
                            </td>
                            <td class="p-6 font-medium text-slate-800 dark:text-slate-200">
                                {{ $tx->description }}
                                @if($tx->reference_id)
                                    <span class="block text-xs text-slate-400 mt-0.5">Ref ID: {{ $tx->reference_id }}</span>
                                @endif
                            </td>
                            <td class="p-6 whitespace-nowrap">
                                @if($tx->type === 'topup')
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-50 dark:bg-emerald-950/30 text-emerald-700 dark:text-emerald-400">
                                        Top Up
                                    </span>
                                @elseif($tx->type === 'purchase')
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-blue-50 dark:bg-blue-950/30 text-blue-700 dark:text-blue-400">
                                        Beli Produk
                                    </span>
                                @elseif($tx->type === 'transfer_in')
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-indigo-50 dark:bg-indigo-950/30 text-indigo-700 dark:text-indigo-400">
                                        Transfer Masuk
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-rose-50 dark:bg-rose-950/30 text-rose-700 dark:text-rose-400">
                                        Transfer Keluar
                                    </span>
                                @endif
                            </td>
                            <td class="p-6 text-right font-bold whitespace-nowrap {{ in_array($tx->type, ['topup', 'transfer_in']) ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-700 dark:text-slate-300' }}">
                                {{ in_array($tx->type, ['topup', 'transfer_in']) ? '+' : '-' }}Rp {{ number_format($tx->amount, 0, ',', '.') }}
                            </td>
                            <td class="p-6 text-right text-slate-500 dark:text-slate-400 whitespace-nowrap font-medium">
                                Rp {{ number_format($tx->balance_after, 0, ',', '.') }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="p-12 text-center text-slate-400 dark:text-slate-500">
                                <svg class="w-12 h-12 mx-auto text-slate-300 dark:text-slate-700 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
                                <p class="text-sm">Belum ada riwayat transaksi saldo</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        @if($transactions->hasPages())
            <div class="p-6 border-t border-slate-100 dark:border-slate-800">
                {{ $transactions->links() }}
            </div>
        @endif
    </div>
</div>

<!-- TOPUP MODAL (Overlay) -->
<div id="topupModal" class="fixed inset-0 z-50 overflow-y-auto hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        
        <!-- Backdrop -->
        <div class="fixed inset-0 bg-slate-900/60 dark:bg-slate-950/80 transition-opacity" aria-hidden="true" onclick="closeTopupModal()"></div>

        <!-- Trick to center the modal contents -->
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

        <!-- Modal Container -->
        <div class="inline-block align-bottom bg-white dark:bg-slate-900 rounded-3xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full border border-slate-100 dark:border-slate-800">
            
            <!-- Step 1: Input Amount -->
            <div id="topupStepInput" class="p-6 space-y-6">
                <div class="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-800">
                    <h3 class="text-lg font-bold text-slate-800 dark:text-slate-100">Isi Saldo Akun</h3>
                    <button type="button" onclick="closeTopupModal()" class="text-slate-400 hover:text-slate-500 dark:hover:text-slate-300">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>

                <div class="space-y-4">
                    <div>
                        <label for="topupAmountInput" class="block text-xs font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider mb-2">Nominal Top Up (Min. Rp 10.000)</label>
                        <div class="relative rounded-2xl shadow-sm">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                <span class="text-slate-400 font-bold text-sm">Rp</span>
                            </div>
                            <input type="number" id="topupAmountInput" class="block w-full pl-10 pr-4 py-3.5 border border-slate-200 dark:border-slate-800 rounded-2xl bg-slate-50 dark:bg-slate-950/20 text-slate-800 dark:text-slate-150 focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition font-bold" placeholder="50.000" min="10000" max="10000000" value="50000">
                        </div>
                    </div>

                    <!-- Preset Options Grid -->
                    <div class="grid grid-cols-2 gap-3">
                        <button type="button" onclick="setPresetAmount(10000)" class="py-2.5 px-4 bg-slate-50 hover:bg-slate-100 dark:bg-slate-800/50 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-300 rounded-xl text-xs font-bold transition">
                            Rp 10.000
                        </button>
                        <button type="button" onclick="setPresetAmount(20000)" class="py-2.5 px-4 bg-slate-50 hover:bg-slate-100 dark:bg-slate-800/50 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-300 rounded-xl text-xs font-bold transition">
                            Rp 20.000
                        </button>
                        <button type="button" onclick="setPresetAmount(50000)" class="py-2.5 px-4 bg-slate-50 hover:bg-slate-100 dark:bg-slate-800/50 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-300 rounded-xl text-xs font-bold transition">
                            Rp 50.000
                        </button>
                        <button type="button" onclick="setPresetAmount(100000)" class="py-2.5 px-4 bg-slate-50 hover:bg-slate-100 dark:bg-slate-800/50 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-300 rounded-xl text-xs font-bold transition">
                            Rp 100.000
                        </button>
                    </div>
                </div>

                <div class="pt-4 border-t border-slate-100 dark:border-slate-800 flex justify-end space-x-3">
                    <button type="button" onclick="closeTopupModal()" class="px-5 py-3 text-slate-500 hover:text-slate-600 dark:text-slate-400 dark:hover:text-slate-350 text-xs font-bold rounded-2xl transition">
                        Batal
                    </button>
                    <button type="button" id="topupSubmitBtn" onclick="submitTopup()" class="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-2xl transition shadow-lg shadow-blue-500/10 flex items-center space-x-2">
                        <span>Lanjut ke Pembayaran</span>
                    </button>
                </div>
            </div>

            <!-- Step 2: Payment (QRIS Display) -->
            <div id="topupStepPayment" class="p-6 space-y-6 hidden">
                <div class="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-800">
                    <div>
                        <h3 class="text-lg font-bold text-slate-800 dark:text-slate-100">Pembayaran Top Up</h3>
                        <span id="topupOrderCode" class="text-xs text-slate-400 mt-0.5">TOP-XXXXXXXX</span>
                    </div>
                    <button type="button" onclick="closeTopupModal()" class="text-slate-400 hover:text-slate-500 dark:hover:text-slate-300">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>

                <!-- QRIS Box -->
                <div class="flex flex-col items-center justify-center p-6 bg-slate-50 dark:bg-slate-950/30 rounded-2xl border border-slate-100 dark:border-slate-800 relative">
                    <!-- Loading skeleton -->
                    <div id="topupQrLoading" class="absolute inset-0 bg-white/95 dark:bg-slate-900/95 flex flex-col items-center justify-center rounded-2xl transition z-10">
                        <svg class="animate-spin h-8 w-8 text-blue-600 mb-2" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span class="text-xs text-slate-400 font-medium">Membuat Kode QRIS...</span>
                    </div>

                    <img id="topupQrisImage" src="" alt="QRIS Code" class="w-60 h-60 object-contain rounded-lg shadow-sm">
                    
                    <div class="mt-4 flex items-center space-x-2 text-xs font-semibold text-slate-400 dark:text-slate-500">
                        <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                        <span>QRIS Dinamis - Expired dalam 15 menit</span>
                    </div>
                </div>

                <!-- Info Details -->
                <div class="space-y-4">
                    <div class="p-4 bg-blue-50/50 dark:bg-blue-950/10 rounded-2xl border border-blue-100/30 dark:border-blue-900/20 text-center">
                        <span class="text-xs text-slate-450 dark:text-slate-400 font-semibold block uppercase tracking-wider">Total Harus Dibayar</span>
                        <h2 id="topupTotalAmount" class="text-2xl sm:text-3xl font-extrabold text-blue-600 dark:text-blue-400 tracking-tight mt-1">Rp 0</h2>
                        <span class="text-[10px] text-amber-600 dark:text-amber-400 font-semibold mt-1 block">Wajib bayar nominal pas sampai 2 digit terakhir demi verifikasi otomatis!</span>
                    </div>

                    <div class="flex items-center justify-between p-3.5 bg-slate-50 dark:bg-slate-950/20 rounded-xl text-xs font-semibold">
                        <span class="text-slate-500">Batas Waktu Bayar</span>
                        <span id="topupCountdown" class="font-bold text-rose-500 text-sm">15:00</span>
                    </div>

                    <div class="text-xs text-slate-500 dark:text-slate-400 bg-slate-50 dark:bg-slate-950/20 p-4 rounded-xl space-y-1.5 leading-relaxed">
                        <strong class="text-slate-700 dark:text-slate-350 block mb-1">Petunjuk Pembayaran:</strong>
                        <p>1. Simpan/Screenshot QR Code di atas.</p>
                        <p>2. Buka aplikasi e-wallet Anda (Dana, OVO, GoPay, ShopeePay, LinkAja) atau Mobile Banking.</p>
                        <p>3. Pilih opsi scan/bayar lalu upload QR Code yang telah disimpan.</p>
                        <p>4. Masukkan nominal total di atas. Halaman ini akan otomatis sukses dalam beberapa detik setelah pembayaran masuk.</p>
                    </div>
                </div>

                <div class="pt-4 border-t border-slate-100 dark:border-slate-800 flex justify-end">
                    <button type="button" onclick="closeTopupModal()" class="px-5 py-3 bg-slate-150 hover:bg-slate-200 dark:bg-slate-850 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-300 text-xs font-bold rounded-2xl transition">
                        Tutup & Cek Nanti
                    </button>
                </div>
            </div>

            <!-- Step 3: Success Animation -->
            <div id="topupStepSuccess" class="p-8 text-center space-y-6 hidden">
                <div class="w-16 h-16 bg-emerald-100 dark:bg-emerald-950/30 text-emerald-600 dark:text-emerald-400 rounded-full flex items-center justify-center mx-auto shadow-md">
                    <svg class="w-10 h-10 animate-bounce" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
                    </svg>
                </div>
                
                <div class="space-y-2">
                    <h3 class="text-xl font-bold text-slate-800 dark:text-slate-100">Top Up Sukses!</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400 max-w-sm mx-auto leading-relaxed">
                        Dana telah didepositkan ke akun Anda secara otomatis. Saldo Anda sekarang bertambah!
                    </p>
                </div>

                <div class="pt-4 border-t border-slate-100 dark:border-slate-800">
                    <button type="button" onclick="reloadBalancePage()" class="w-full py-3 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-2xl transition shadow-lg shadow-emerald-500/10">
                        Selesai & Muat Ulang Halaman
                    </button>
                </div>
            </div>
            
        </div>
    </div>
</div>

<script>
    let topupPollInterval = null;
    let topupCountdownInterval = null;
    let topupActiveOrderId = null;

    function openTopupModal() {
        // Reset steps view
        document.getElementById('topupStepInput').classList.remove('hidden');
        document.getElementById('topupStepPayment').classList.add('hidden');
        document.getElementById('topupStepSuccess').classList.add('hidden');
        
        const modal = document.getElementById('topupModal');
        modal.classList.remove('hidden');
    }

    function closeTopupModal() {
        const modal = document.getElementById('topupModal');
        modal.classList.add('hidden');
        
        // Clear timers if they were running
        if (topupPollInterval) clearInterval(topupPollInterval);
        if (topupCountdownInterval) clearInterval(topupCountdownInterval);
    }

    function setPresetAmount(amount) {
        document.getElementById('topupAmountInput').value = amount;
    }

    function submitTopup() {
        const amount = document.getElementById('topupAmountInput').value;
        const submitBtn = document.getElementById('topupSubmitBtn');

        if (!amount || amount < 10000) {
            alert('Nominal minimal pengisian saldo adalah Rp 10.000.');
            return;
        }

        submitBtn.disabled = true;
        submitBtn.innerHTML = `
            <svg class="animate-spin -ml-1 mr-3 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <span>Membuat Tagihan...</span>
        `;

        fetch('/balance/topup', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({ amount: amount })
        })
        .then(res => res.json())
        .then(data => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<span>Lanjut ke Pembayaran</span>';

            if (data.success) {
                renderTopupPaymentInstructions(data.order);
            } else {
                alert(data.message || 'Gagal memproses pengisian saldo.');
            }
        })
        .catch(err => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<span>Lanjut ke Pembayaran</span>';
            alert('Terjadi kesalahan koneksi server.');
            console.error(err);
        });
    }

    function renderTopupPaymentInstructions(order) {
        topupActiveOrderId = order.id;
        
        // Show payment state, hide input state
        document.getElementById('topupStepInput').classList.add('hidden');
        document.getElementById('topupStepPayment').classList.remove('hidden');
        
        // Populate instructions
        document.getElementById('topupOrderCode').innerText = 'Tagihan ID: ' + order.id;
        document.getElementById('topupTotalAmount').innerText = 'Rp ' + parseInt(order.total_amount).toLocaleString('id-ID');
        
        // Load QR Code Image
        const qrLoading = document.getElementById('topupQrLoading');
        const qrisImage = document.getElementById('topupQrisImage');
        qrLoading.classList.remove('hidden');
        
        const qrisPayloadUrl = encodeURIComponent(order.qris_payload);
        qrisImage.src = `https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=${qrisPayloadUrl}`;
        
        qrisImage.onload = () => {
            qrLoading.classList.add('hidden');
        };

        // Start countdown timer
        startTopupCountdown(order.expired_at, order.server_time);
        
        // Start polling for payment verification
        startTopupPolling();
    }

    function resumeTopup(orderId, totalAmount, encodedPayload, expiredAt, serverTime) {
        openTopupModal();
        
        const order = {
            id: orderId,
            total_amount: totalAmount,
            qris_payload: decodeURIComponent(encodedPayload),
            expired_at: expiredAt,
            server_time: serverTime
        };
        
        renderTopupPaymentInstructions(order);
    }

    function startTopupCountdown(expiryStr, serverTimeStr) {
        const expiry = new Date(expiryStr).getTime();
        const serverTime = new Date(serverTimeStr).getTime();
        const localStartTime = new Date().getTime();
        const serverDiff = serverTime - localStartTime;

        const countdownElement = document.getElementById('topupCountdown');
        
        if (topupCountdownInterval) clearInterval(topupCountdownInterval);

        topupCountdownInterval = setInterval(() => {
            const now = new Date().getTime() + serverDiff;
            const distance = expiry - now;

            if (distance < 0) {
                clearInterval(topupCountdownInterval);
                countdownElement.innerText = 'EXPIRED';
                return;
            }

            const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((distance % (1000 * 60)) / 1000);

            countdownElement.innerText = 
                (minutes < 10 ? '0' : '') + minutes + ':' + 
                (seconds < 10 ? '0' : '') + seconds;
        }, 1000);
    }

    function startTopupPolling() {
        if (topupPollInterval) clearInterval(topupPollInterval);
        
        topupPollInterval = setInterval(() => {
            if (!topupActiveOrderId) return;
            
            fetch(`/balance/topup/${topupActiveOrderId}/status`)
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success' || data.status === 'paid') {
                    clearInterval(topupPollInterval);
                    clearInterval(topupCountdownInterval);
                    
                    document.getElementById('topupStepPayment').classList.add('hidden');
                    document.getElementById('topupStepSuccess').classList.remove('hidden');
                } else if (data.status === 'expired') {
                    clearInterval(topupPollInterval);
                    clearInterval(topupCountdownInterval);
                    alert('Batas waktu pembayaran telah habis. Silakan buat permintaan top up baru.');
                    closeTopupModal();
                }
            })
            .catch(err => console.error("Error polling topup status:", err));
        }, 4000); // Poll every 4 seconds
    }

    function reloadBalancePage() {
        window.location.reload();
    }
</script>
@endsection
