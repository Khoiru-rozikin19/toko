@extends('layouts.app', ['title' => 'Dashboard Seller'])

@section('content')
<div class="space-y-6 sm:space-y-10">
    
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

    <!-- Welcome Banner -->
    <div class="border-b border-slate-200 dark:border-slate-800 pb-5">
        <h2 class="text-2xl sm:text-3xl font-extrabold text-slate-800 dark:text-slate-100 tracking-tight">
            Selamat Datang di Portal Seller, {{ Auth::user()->name ?? 'Admin Utama' }}!
        </h2>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
            Kelola produk Anda, kelola stok akun, dan pantau penghasilan penjualan Anda secara langsung.
        </p>
    </div>

    <!-- Top Cards Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-{{ auth()->user()->role === 'admin' ? '4' : '3' }} gap-6">
        
        @if(auth()->user()->role === 'admin')
            <!-- Card 1: Saldo orderkuota (Deep Blue) -->
            <div class="bg-blue-600 text-white rounded-3xl p-6 flex flex-col justify-between shadow-xl shadow-blue-600/10 min-h-44">
                <div>
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-blue-200 uppercase tracking-wider block">Saldo orderkuota</span>
                        <button id="refreshOrderkuotaBtn" class="text-blue-200 hover:text-white transition-colors duration-150 focus:outline-none" title="Refresh Saldo Real-time">
                            <svg id="refreshIcon" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"></path>
                            </svg>
                        </button>
                    </div>
                    <span id="orderkuotaBalanceText" class="text-2xl font-black mt-2 block tracking-tight">
                        @if(is_numeric($orderkuotaBalance))
                            Rp {{ number_format($orderkuotaBalance, 0, ',', '.') }}
                        @else
                            {{ $orderkuotaBalance }}
                        @endif
                    </span>
                </div>
                <div class="flex items-center justify-between mt-4">
                    <span class="text-xs text-blue-100">H2H Supplier</span>
                </div>
            </div>

            <!-- Card 2: Saldo dompet saya (Orange/Yellow) -->
            <div class="bg-amber-500 text-white rounded-3xl p-6 flex flex-col justify-between shadow-xl shadow-amber-500/10 min-h-44">
                <div>
                    <span class="text-xs font-semibold text-amber-100 uppercase tracking-wider block">Saldo dompet saya</span>
                    <span class="text-2xl font-black mt-2 block tracking-tight">Rp {{ number_format($walletBalance, 0, ',', '.') }}</span>
                </div>
                <div class="flex items-center justify-between mt-4">
                    <span class="text-xs text-amber-100">Bersih (Setelah modal H2H)</span>
                    <button onclick="openTransferModal({{ $walletBalance }})" class="flex items-center space-x-1.5 px-4 py-2 bg-white text-amber-600 rounded-xl text-xs font-bold shadow-md hover:bg-amber-50 transition-all duration-200">
                        <span>Pindahkan Saldo</span>
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                    </button>
                </div>
            </div>
        @else
            <!-- Card 1: Saldo dompet saya for Seller (Deep Blue) -->
            <div class="bg-blue-600 text-white rounded-3xl p-6 flex flex-col justify-between shadow-xl shadow-blue-600/10 min-h-44">
                <div>
                    <span class="text-xs font-semibold text-blue-200 uppercase tracking-wider block">Saldo dompet saya</span>
                    <span class="text-2xl font-black mt-2 block tracking-tight">Rp {{ number_format($walletBalance, 0, ',', '.') }}</span>
                </div>
                <div class="flex items-center justify-between mt-4">
                    <span class="text-xs text-blue-100">Komisi: 0%</span>
                    <button onclick="openTransferModal({{ $walletBalance }})" class="flex items-center space-x-1.5 px-4 py-2 bg-white text-blue-600 rounded-xl text-xs font-bold shadow-md hover:bg-blue-50 transition-all duration-200">
                        <span>Pindahkan Saldo</span>
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                    </button>
                </div>
            </div>
        @endif

        <!-- Card 3: Pendapatan Kotor (White / Green Text) -->
        <div class="bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 rounded-3xl p-6 flex flex-col justify-between shadow-sm min-h-44">
            <div>
                <span class="text-xs font-semibold text-slate-400 dark:text-slate-500 uppercase tracking-wider block">Pendapatan Kotor</span>
                <span class="text-2xl font-black mt-2 block tracking-tight text-emerald-600 dark:text-emerald-400">Rp {{ number_format($totalRevenue, 0, ',', '.') }}</span>
            </div>
            <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-4 leading-normal">
                <span class="flex items-center space-x-1">
                    <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <span>Akumulasi pendapatan kotor produk Anda.</span>
                </span>
            </div>
        </div>

        <!-- Card 4: Status Stok Penjualan (White / Multi Text) -->
        <div class="bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 rounded-3xl p-6 flex flex-col justify-between shadow-sm min-h-44">
            <div>
                <span class="text-xs font-semibold text-slate-400 dark:text-slate-500 uppercase tracking-wider block">Status Stok Penjualan</span>
                <div class="grid grid-cols-3 gap-2 mt-4 text-center">
                    <div class="bg-blue-50 dark:bg-blue-950/20 py-2 rounded-xl border border-blue-100/50 dark:border-blue-950/50">
                        <span class="text-sm font-extrabold text-blue-600 dark:text-blue-400 block">{{ $readyStockCount }}</span>
                        <span class="text-[9px] text-slate-400 dark:text-slate-500 uppercase font-bold">Ready</span>
                    </div>
                    <div class="bg-amber-50 dark:bg-amber-950/20 py-2 rounded-xl border border-amber-100/50 dark:border-amber-950/50">
                        <span class="text-sm font-extrabold text-amber-600 dark:text-amber-400 block">0</span>
                        <span class="text-[9px] text-slate-400 dark:text-slate-500 uppercase font-bold">Karantina</span>
                    </div>
                    <div class="bg-slate-50 dark:bg-slate-900 py-2 rounded-xl border border-slate-150 dark:border-slate-800/80">
                        <span class="text-sm font-extrabold text-slate-600 dark:text-slate-400 block">{{ $totalSalesCount }}</span>
                        <span class="text-[9px] text-slate-400 dark:text-slate-500 uppercase font-bold">Terjual</span>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- Charts Section (2 Columns: 70% and 30%) -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <!-- Daily Earnings Line Chart -->
        <div class="lg:col-span-2 bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 rounded-3xl p-6 shadow-sm">
            <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-4 mb-6">
                <div>
                    <h3 class="text-lg font-bold text-slate-800 dark:text-slate-100 flex items-center space-x-2">
                        <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                        <span>Tren Pendapatan Harian</span>
                    </h3>
                    <p class="text-xs text-slate-400 dark:text-slate-500 mt-0.5">Statistik omset penjualan dalam 7 hari terakhir</p>
                </div>
                
                <span class="text-xs font-semibold px-3 py-1 bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 rounded-full">7 Hari Terakhir</span>
            </div>
            
            <div class="relative h-72">
                <canvas id="earningsChart"></canvas>
            </div>
        </div>

        <!-- Order Ratios Donut Chart -->
        <div class="bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 rounded-3xl p-6 shadow-sm flex flex-col justify-between">
            <div class="border-b border-slate-100 dark:border-slate-800 pb-4 mb-6">
                <h3 class="text-lg font-bold text-slate-800 dark:text-slate-100 flex items-center space-x-2">
                    <svg class="w-5 h-5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 3.055A9.003 9.003 0 1020.945 13H11V3.055z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z"></path></svg>
                    <span>Rasio Status Order</span>
                </h3>
                <p class="text-xs text-slate-400 dark:text-slate-500 mt-0.5">Persentase sukses vs batal/expired</p>
            </div>

            <div class="relative h-56 flex items-center justify-center">
                <canvas id="ratioChart" class="max-h-full"></canvas>
                <!-- Central Total Overlay -->
                <div class="absolute flex flex-col items-center justify-center">
                    <span class="text-2xl font-black text-slate-800 dark:text-slate-100">{{ $totalOrdersCount }}</span>
                    <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Total Order</span>
                </div>
            </div>

            <div class="mt-6 flex justify-around text-xs border-t border-slate-100 dark:border-slate-800 pt-4">
                <div class="flex items-center space-x-1.5">
                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 block"></span>
                    <span class="text-slate-600 dark:text-slate-400">Sukses ({{ $donutData[0] }})</span>
                </div>
                <div class="flex items-center space-x-1.5">
                    <span class="w-2.5 h-2.5 rounded-full bg-blue-500 block"></span>
                    <span class="text-slate-600 dark:text-slate-400">Pending ({{ $donutData[1] }})</span>
                </div>
                <div class="flex items-center space-x-1.5">
                    <span class="w-2.5 h-2.5 rounded-full bg-rose-500 block"></span>
                    <span class="text-slate-600 dark:text-slate-400">Expired ({{ $donutData[2] }})</span>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- Load Chart.js from CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    document.addEventListener("DOMContentLoaded", function () {
        // Line Chart Settings
        const earningsCtx = document.getElementById('earningsChart').getContext('2d');
        const isDark = document.documentElement.classList.contains('dark');
        
        const gridColor = isDark ? '#1e293b' : '#f1f5f9';
        const textColor = isDark ? '#94a3b8' : '#64748b';

        // Prepare line chart
        const earningsChart = new Chart(earningsCtx, {
            type: 'line',
            data: {
                labels: {!! json_encode($chartLabels) !!},
                datasets: [{
                    label: 'Pendapatan (Rp)',
                    data: {!! json_encode($chartData) !!},
                    borderColor: '#2563eb',
                    backgroundColor: 'rgba(37, 99, 235, 0.08)',
                    borderWidth: 3.5,
                    fill: true,
                    tension: 0.35,
                    pointBackgroundColor: '#2563eb',
                    pointHoverRadius: 7
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        grid: { color: gridColor },
                        ticks: {
                            color: textColor,
                            font: { family: 'Plus Jakarta Sans', size: 10 },
                            callback: function(value) {
                                return 'Rp ' + (value >= 1000 ? (value/1000) + 'k' : value);
                            }
                        }
                    },
                    x: {
                        grid: { display: false },
                        ticks: {
                            color: textColor,
                            font: { family: 'Plus Jakarta Sans', size: 10 }
                        }
                    }
                }
            }
        });

        // Donut Chart Settings
        const ratioCtx = document.getElementById('ratioChart').getContext('2d');
        const successVal = {{ $donutData[0] }};
        const pendingVal = {{ $donutData[1] }};
        const expiredVal = {{ $donutData[2] }};

        const ratioChart = new Chart(ratioCtx, {
            type: 'doughnut',
            data: {
                labels: ['Sukses', 'Pending', 'Expired'],
                datasets: [{
                    data: [successVal, pendingVal, expiredVal],
                    backgroundColor: [
                        '#10b981', // green
                        '#3b82f6', // blue
                        '#f43f5e'  // rose
                    ],
                    borderWidth: 0,
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '76%',
                plugins: {
                    legend: { display: false }
                }
            }
        });

        // Dynamic theme updates for charts
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.attributeName === "class") {
                    const isDarkNow = document.documentElement.classList.contains('dark');
                    const newGridColor = isDarkNow ? '#1e293b' : '#f1f5f9';
                    const newTextColor = isDarkNow ? '#94a3b8' : '#64748b';

                    // Update config
                    earningsChart.options.scales.y.grid.color = newGridColor;
                    earningsChart.options.scales.y.ticks.color = newTextColor;
                    earningsChart.options.scales.x.ticks.color = newTextColor;
                    earningsChart.update();
                }
            });
        });

        // Real-time Orderkuota Balance Fetcher (Admin Only)
        const refreshBtn = document.getElementById('refreshOrderkuotaBtn');
        const refreshIcon = document.getElementById('refreshIcon');
        const balanceText = document.getElementById('orderkuotaBalanceText');

        function fetchRealtimeBalance() {
            if (!refreshIcon || !refreshBtn || !balanceText) return;
            
            refreshIcon.classList.add('animate-spin');
            refreshBtn.disabled = true;

            fetch("{{ route('admin.orderkuota_balance') }}")
                .then(res => res.json())
                .then(data => {
                    refreshIcon.classList.remove('animate-spin');
                    refreshBtn.disabled = false;
                    if (data.success) {
                        balanceText.innerText = data.formatted_balance;
                    } else {
                        balanceText.innerText = data.balance;
                    }
                })
                .catch(err => {
                    refreshIcon.classList.remove('animate-spin');
                    refreshBtn.disabled = false;
                    console.error('Failed to fetch balance:', err);
                });
        }

        if (refreshBtn) {
            refreshBtn.addEventListener('click', fetchRealtimeBalance);
            // Auto-fetch on page load for real-time update
            fetchRealtimeBalance();
        }

        observer.observe(document.documentElement, { attributes: true });
    });
</script>

<!-- TRANSFER MODAL -->
<div id="transferModal" class="fixed inset-0 z-50 overflow-y-auto hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <!-- Backdrop -->
        <div class="fixed inset-0 bg-slate-900/60 dark:bg-slate-950/80 transition-opacity" aria-hidden="true" onclick="closeTransferModal()"></div>

        <!-- Trick to center the modal contents -->
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

        <!-- Modal Container -->
        <div class="relative z-10 inline-block align-bottom bg-white dark:bg-slate-900 rounded-3xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full border border-slate-100 dark:border-slate-800">
            <form action="{{ route('admin.transfer_to_balance') }}" method="POST" class="p-6 space-y-6">
                @csrf
                <div class="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-800">
                    <h3 class="text-lg font-bold text-slate-850 dark:text-slate-100">Pindahkan Saldo ke Akun</h3>
                    <button type="button" onclick="closeTransferModal()" class="text-slate-400 hover:text-slate-500 dark:hover:text-slate-300">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>

                <div class="space-y-4">
                    <div class="p-4 bg-blue-50/50 dark:bg-blue-950/10 rounded-2xl border border-blue-100/30 dark:border-blue-900/20 text-sm">
                        <div class="flex justify-between items-center text-slate-650 dark:text-slate-350">
                            <span>Maksimal Transfer:</span>
                            <span class="font-bold text-blue-600 dark:text-blue-400" id="maxTransferText">Rp 0</span>
                        </div>
                    </div>

                    <div>
                        <label for="transferAmountInput" class="block text-xs font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider mb-2">Nominal Transfer (Min. Rp 1.000)</label>
                        <div class="relative rounded-2xl shadow-sm">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                <span class="text-slate-400 font-bold text-sm">Rp</span>
                            </div>
                            <input type="number" id="transferAmountInput" name="amount" class="block w-full pl-10 pr-4 py-3.5 border border-slate-200 dark:border-slate-800 rounded-2xl bg-slate-50 dark:bg-slate-950/20 text-slate-800 dark:text-slate-150 focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition font-bold" min="1000" placeholder="10000" required>
                        </div>
                    </div>

                    <div class="text-[11px] text-slate-550 dark:text-slate-450 leading-relaxed bg-slate-50 dark:bg-slate-950/10 p-3.5 rounded-xl border border-slate-100 dark:border-slate-800/80">
                        Pindahkan komisi penjualan dari dompet seller Anda langsung ke saldo akun buyer agar bisa digunakan membeli produk lain di toko ini.
                    </div>
                </div>

                <div class="pt-4 border-t border-slate-100 dark:border-slate-800 flex justify-end space-x-3">
                    <button type="button" onclick="closeTransferModal()" class="px-5 py-3 text-slate-500 hover:text-slate-600 dark:text-slate-400 dark:hover:text-slate-350 text-xs font-bold rounded-2xl transition">
                        Batal
                    </button>
                    <button type="submit" class="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-2xl transition shadow-lg shadow-blue-500/10 flex items-center space-x-2">
                        <span>Transfer Sekarang</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function openTransferModal(maxAmount) {
        document.getElementById('transferModal').classList.remove('hidden');
        document.getElementById('maxTransferText').innerText = 'Rp ' + maxAmount.toLocaleString('id-ID');
        document.getElementById('transferAmountInput').max = maxAmount;
        document.getElementById('transferAmountInput').value = maxAmount;
    }

    function closeTransferModal() {
        document.getElementById('transferModal').classList.add('hidden');
    }
</script>
@endsection
