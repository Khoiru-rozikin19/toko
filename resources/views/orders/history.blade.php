@extends('layouts.app', ['title' => 'Riwayat Pesanan Saya'])

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
<div class="space-y-6 sm:space-y-10">
    
    <!-- Header -->
    <div class="border-b border-slate-200 dark:border-slate-800 pb-5">
        <h2 class="text-2xl sm:text-3xl font-extrabold text-slate-800 dark:text-slate-100 tracking-tight">Riwayat Pesanan Saya</h2>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Daftar transaksi pembelian produk digital Anda</p>
    </div>

    <!-- Orders Table -->
    <div class="bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 rounded-3xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 dark:bg-slate-900/60 border-b border-slate-100 dark:border-slate-800 text-xs font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">
                        <th class="py-4.5 px-6">ID Order</th>
                        <th class="py-4.5 px-6">Produk</th>
                        <th class="py-4.5 px-6">Total Bayar</th>
                        <th class="py-4.5 px-6">Status</th>
                        <th class="py-4.5 px-6">Tanggal</th>
                        <th class="py-4.5 px-6 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-sm font-medium text-slate-700 dark:text-slate-300">
                    @forelse($orders as $order)
                        <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/20 transition-all duration-150">
                            <td class="py-4.5 px-6 font-mono text-xs text-blue-600 dark:text-blue-400 font-bold">
                                {{ $order->id }}
                            </td>
                            <td class="py-4.5 px-6">
                                <div class="font-bold text-slate-800 dark:text-slate-200">
                                    {{ $order->product->name ?? 'Produk Dihapus' }}
                                </div>
                                @if($order->target_phone)
                                    <div class="text-[10px] text-slate-400 font-normal mt-0.5">Tujuan: {{ $order->target_phone }}</div>
                                @endif
                            </td>
                            <td class="py-4.5 px-6 font-extrabold text-slate-800 dark:text-slate-100">
                                Rp {{ number_format($order->total_amount, 0, ',', '.') }}
                            </td>
                            <td class="py-4.5 px-6">
                                @if(in_array($order->status, ['success', 'paid']))
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-50 dark:bg-emerald-950/30 text-emerald-700 dark:text-emerald-400">
                                        Sukses
                                    </span>
                                @elseif($order->status === 'proses')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-blue-50 dark:bg-blue-950/30 text-blue-700 dark:text-blue-400">
                                        Proses
                                    </span>
                                @elseif($order->status === 'gagal')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-rose-50 dark:bg-rose-950/30 text-rose-700 dark:text-rose-400">
                                        Gagal
                                    </span>
                                @elseif(in_array($order->status, ['pending', 'pending_manual']))
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-blue-50 dark:bg-blue-950/30 text-blue-700 dark:text-blue-400 animate-pulse">
                                        Pending
                                    </span>
                                @elseif($order->status === 'rejected')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-rose-50 dark:bg-rose-950/30 text-rose-700 dark:text-rose-400">
                                        Ditolak
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400">
                                        Expired
                                    </span>
                                @endif
                            </td>
                            <td class="py-4.5 px-6 text-xs text-slate-400">
                                {{ $order->created_at->format('d/m/Y H:i') }}
                            </td>
                            <td class="py-4.5 px-6 text-center">
                                @if(in_array($order->status, ['success', 'paid', 'proses']))
                                    @php
                                        $complaint = $order->complaints->first();
                                    @endphp
                                    <div class="flex items-center justify-center space-x-2">
                                        @if(!empty($order->vpn_config))
                                            @php
                                                $vpnProtocols = ['vmess://', 'vless://', 'trojan://', 'ss://', 'shadowsocks://'];
                                                $isNodeLink = false;
                                                foreach ($vpnProtocols as $proto) {
                                                    if (stripos($order->vpn_config, $proto) !== false) {
                                                        $isNodeLink = true;
                                                        break;
                                                    }
                                                }
                                                $isSingleLine = !str_contains($order->vpn_config, "\n") && !str_contains($order->vpn_config, "\r");
                                            @endphp
                                            
                                            @if($isNodeLink || $isSingleLine)
                                                <button data-config="{{ $order->vpn_config }}" data-instruction="{{ $order->product->success_instruction ?? '' }}" onclick="openAccountModal('{{ $order->id }}', this.getAttribute('data-config'), this.getAttribute('data-instruction'))" class="inline-flex items-center space-x-1.5 px-3.5 py-1.5 bg-blue-600 hover:bg-blue-750 text-white rounded-xl text-xs font-bold transition-all duration-200">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                                    <span>Lihat Akun</span>
                                                </button>
                                            @else
                                                <a href="{{ route('order.download', $order->id) }}" class="inline-flex items-center space-x-1.5 px-3.5 py-1.5 bg-blue-600 hover:bg-blue-750 text-white rounded-xl text-xs font-bold transition-all duration-200">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"></path></svg>
                                                    <span>Unduh Config</span>
                                                </a>
                                            @endif
                                        @else
                                            @if($order->sn)
                                                <span class="text-xs text-slate-500 font-mono select-all bg-slate-50 dark:bg-slate-850 px-2.5 py-1.5 rounded-lg border border-slate-100 dark:border-slate-800" title="Serial Number / Keterangan pengisian">SN: {{ $order->sn }}</span>
                                            @else
                                                <span class="text-xs text-slate-400">Selesai</span>
                                            @endif
                                        @endif

                                        @if($complaint)
                                            @if($complaint->status === 'pending')
                                                <span class="inline-flex items-center px-2.5 py-1 bg-amber-50 dark:bg-amber-950/20 text-amber-600 dark:text-amber-400 rounded-xl text-xs font-bold border border-amber-200/20" title="Komplain sedang ditinjau">Komplain Pending</span>
                                            @elseif($complaint->status === 'resolved')
                                                <span class="inline-flex items-center px-2.5 py-1 bg-emerald-50 dark:bg-emerald-950/20 text-emerald-600 dark:text-emerald-400 rounded-xl text-xs font-bold border border-emerald-200/20" title="Komplain selesai (Refund)">Refunded</span>
                                            @elseif($complaint->status === 'rejected')
                                                <span class="inline-flex items-center px-2.5 py-1 bg-rose-50 dark:bg-rose-950/20 text-rose-600 dark:text-rose-400 rounded-xl text-xs font-bold border border-rose-200/20" title="Komplain ditolak oleh seller">Komplain Ditolak</span>
                                            @endif
                                        @else
                                            <button onclick="openComplaintModal('{{ $order->id }}')" class="inline-flex items-center space-x-1 px-3 py-1.5 bg-rose-600 hover:bg-rose-700 text-white rounded-xl text-xs font-bold transition-all duration-200">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                                                <span>Komplain</span>
                                            </button>
                                        @endif
                                    </div>
                                @elseif(in_array($order->status, ['pending', 'pending_manual']))
                                    <button onclick="openPaymentModal('{{ $order->id }}', '{{ $order->total_amount }}', '{{ $order->qris_payload }}', '{{ $order->expired_at ? $order->expired_at->toIso8601String() : '' }}')" class="inline-flex items-center space-x-1.5 px-3.5 py-1.5 bg-amber-500 hover:bg-amber-600 text-white rounded-xl text-xs font-bold transition-all duration-200">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z"></path></svg>
                                        <span>Bayar / Detail</span>
                                    </button>
                                @else
                                    <span class="text-xs text-slate-400">-</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-12 text-center text-slate-400">
                                Belum ada riwayat transaksi pembelian.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        @if($orders->count() > 0)
            <div class="p-6 border-t border-slate-100 dark:border-slate-800">
                {{ $orders->links() }}
            </div>
        @endif
    </div>
</div>

<!-- INLINE QRIS PAYMENT DETAIL MODAL -->
<div id="paymentDetailModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm hidden transition-all duration-300">
    <div class="bg-white dark:bg-slate-950 rounded-3xl w-full max-w-md border border-slate-100 dark:border-slate-800 p-6 sm:p-8 shadow-2xl relative">
        <!-- Close Button -->
        <button onclick="closePaymentModal()" class="absolute top-5 right-5 p-2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-900 transition-all duration-200">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path></svg>
        </button>

        <div class="text-center space-y-6">
            <div>
                <span class="px-3 py-1 bg-amber-50 dark:bg-amber-950/20 border border-amber-200 dark:border-amber-900/50 rounded-full text-[11px] font-bold text-amber-700 dark:text-amber-400 uppercase tracking-wider">Menunggu Pembayaran</span>
                <h3 class="text-2xl font-extrabold text-slate-800 dark:text-slate-100 leading-tight mt-3">Scan QRIS Untuk Bayar</h3>
                <p id="modalOrderId" class="text-xs text-slate-400 dark:text-slate-500 mt-1">Order ID: ORD-XXXXXX</p>
            </div>

            <!-- QR Code Image -->
            <div class="relative w-60 h-60 mx-auto bg-white p-3 border border-slate-100 dark:border-slate-800 rounded-3xl flex items-center justify-center shadow-lg">
                <img id="modalQrisImage" src="" alt="QRIS QR Code" class="w-full h-full object-contain">
            </div>

            <!-- Total Amount Info -->
            <div class="bg-slate-50 dark:bg-slate-900/50 rounded-2xl p-4 border border-slate-100 dark:border-slate-800 flex flex-col items-center">
                <span class="text-xs text-slate-400 dark:text-slate-500">Total Tagihan (Wajib Sesuai):</span>
                <span id="modalTotalAmount" class="text-xl font-black text-slate-800 dark:text-slate-100 mt-1">Rp 0</span>
            </div>

            <!-- Expiry Countdown -->
            <div class="flex items-center justify-center space-x-2 text-xs text-slate-500 dark:text-slate-400">
                <svg class="w-4.5 h-4.5 text-blue-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <span>Sisa waktu pembayaran: <strong id="modalCountdown" class="text-blue-600 dark:text-blue-400 font-mono">00:00</strong></span>
            </div>
        </div>
    </div>
</div>

<script>
    let countdownInterval = null;
    let pollInterval = null;
    let activeOrderId = null;

    function openPaymentModal(orderId, amount, qrisPayload, expiryStr) {
        activeOrderId = orderId;
        document.getElementById('modalOrderId').innerText = 'Order ID: ' + orderId;
        document.getElementById('modalTotalAmount').innerText = 'Rp ' + parseInt(amount).toLocaleString('id-ID');
        
        // Load QR Code
        const qrisPayloadUrl = encodeURIComponent(qrisPayload);
        document.getElementById('modalQrisImage').src = `https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=${qrisPayloadUrl}`;
        
        // Open Modal
        document.getElementById('paymentDetailModal').classList.remove('hidden');

        // Start countdown
        if (expiryStr) {
            startCountdown(expiryStr);
        }

        // Start polling
        startPolling(orderId);
    }

    function closePaymentModal() {
        document.getElementById('paymentDetailModal').classList.add('hidden');
        if (countdownInterval) clearInterval(countdownInterval);
        if (pollInterval) clearInterval(pollInterval);
        activeOrderId = null;
    }

    function startCountdown(expiryStr) {
        const expiry = new Date(expiryStr).getTime();
        const countdownElement = document.getElementById('modalCountdown');
        
        if (countdownInterval) clearInterval(countdownInterval);

        countdownInterval = setInterval(() => {
            const now = new Date().getTime();
            const distance = expiry - now;

            if (distance < 0) {
                clearInterval(countdownInterval);
                countdownElement.innerText = 'EXPIRED';
                setTimeout(() => {
                    window.location.reload();
                }, 2000);
                return;
            }

            const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((distance % (1000 * 60)) / 1000);

            countdownElement.innerText = 
                (minutes < 10 ? '0' : '') + minutes + ':' + 
                (seconds < 10 ? '0' : '') + seconds;
        }, 1000);
    }

    function startPolling(orderId) {
        if (pollInterval) clearInterval(pollInterval);
        
        pollInterval = setInterval(() => {
            if (!activeOrderId) return;
            
            fetch(`/orders/${activeOrderId}/status`)
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success' || data.status === 'paid') {
                    clearInterval(pollInterval);
                    alert("Pembayaran sukses terverifikasi!");
                    window.location.reload();
                } else if (data.status === 'expired') {
                    clearInterval(pollInterval);
                    window.location.reload();
                }
            })
            .catch(err => console.error("Error polling order status:", err));
        }, 4000);
    }
</script>

<!-- ACCOUNT DETAIL MODAL (FOR COPIABLE ACCOUNT & QR CODE DISPLAY) -->
<div id="accountDetailModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm hidden transition-all duration-300">
    <div class="bg-white dark:bg-slate-950 rounded-3xl w-full max-w-lg border border-slate-100 dark:border-slate-800 p-6 sm:p-8 shadow-2xl relative">
        <!-- Close Button -->
        <button onclick="closeAccountModal()" class="absolute top-5 right-5 p-2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-900 transition-all duration-200">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
        </button>

        <div class="space-y-6">
            <div class="text-center">
                <h3 class="text-2xl font-extrabold text-slate-800 dark:text-slate-100 leading-tight">Detail Akun VPN Anda</h3>
                <p id="accountModalOrderId" class="text-xs text-slate-400 dark:text-slate-500 mt-1">Order ID: ORD-XXXXXX</p>
            </div>

            <div id="accountModalDetailsContainer" class="bg-blue-50 dark:bg-blue-950/20 p-5 border border-blue-100 dark:border-blue-900/50 rounded-3xl text-slate-800 dark:text-slate-200">
                <!-- Dynamic Content -->
            </div>
        </div>
    </div>
</div>

<script>
    function openAccountModal(orderId, config, successInstruction) {
        document.getElementById('accountModalOrderId').innerText = 'Order ID: ' + orderId;
        const container = document.getElementById('accountModalDetailsContainer');
        
        const trimmedConfig = config ? config.trim() : '';
        let nodeLink = '';
        const match = trimmedConfig.match(/(vmess|vless|trojan|ss|shadowsocks):\/\/[^\s"]+/i);
        if (match) {
            nodeLink = match[0];
        }

        if (nodeLink) {
            let html = `
                <div class="space-y-4">
                    <span class="text-xs font-bold text-blue-600 dark:text-blue-400 block uppercase tracking-wider text-left">DETAIL AKUN VPN:</span>
                    <div class="flex flex-col md:flex-row gap-5 items-stretch justify-between text-left">
                        <div class="flex-1 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-5 rounded-3xl flex flex-col justify-between w-full">
                            <textarea readonly class="w-full bg-transparent text-xs font-mono text-slate-800 dark:text-slate-200 border-none outline-none focus:ring-0 resize-none h-28 pr-1 custom-scrollbar" id="historyConfigText">${escapeHtml(nodeLink)}</textarea>
                            <div class="flex justify-center mt-4">
                                <button onclick="copyHistoryConfig()" class="inline-flex items-center space-x-2 px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-full text-xs font-bold transition-all duration-200 shadow-md shadow-blue-500/10">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2"></path></svg>
                                    <span id="copyHistoryBtnText">Salin Akun</span>
                                </button>
                            </div>
                        </div>
                        <div class="w-full md:w-44 bg-white dark:bg-slate-900 p-4 border border-slate-200 dark:border-slate-800 rounded-3xl flex items-center justify-center flex-shrink-0 shadow-sm aspect-square md:aspect-auto">
                            <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=${encodeURIComponent(nodeLink)}" alt="QR Code" class="w-full h-full object-contain rounded-xl">
                        </div>
                    </div>
                </div>
            `;

            if (successInstruction && successInstruction.trim() !== '') {
                html += `
                    <div class="mt-4 p-4 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl text-left">
                        <span class="text-xs text-blue-600 dark:text-blue-400 font-bold block uppercase tracking-wider mb-2">Petunjuk Tambahan:</span>
                        <div class="text-xs text-slate-600 dark:text-slate-400 leading-relaxed whitespace-pre-wrap">${escapeHtml(successInstruction)}</div>
                    </div>
                `;
            }

            container.innerHTML = html;
        } else {
            let html = `
                <div class="text-left space-y-3 p-1">
                    <span class="text-xs text-blue-600 dark:text-blue-400 font-bold block uppercase tracking-wider">Konfigurasi VPN Anda:</span>
                    <div class="relative bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-4 rounded-2xl flex items-center justify-between gap-3">
                        <span class="text-sm font-mono text-slate-800 dark:text-slate-200 select-all break-all" id="historyConfigTextPlain">${escapeHtml(config)}</span>
                        <button onclick="copyHistoryConfigPlain()" class="flex-shrink-0 p-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-bold transition-all duration-200" title="Salin Akun">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2"></path></svg>
                        </button>
                    </div>
                </div>
            `;

            if (successInstruction && successInstruction.trim() !== '') {
                html += `
                    <div class="mt-4 p-4 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl text-left">
                        <span class="text-xs text-blue-600 dark:text-blue-400 font-bold block uppercase tracking-wider mb-2">Petunjuk Tambahan:</span>
                        <div class="text-xs text-slate-600 dark:text-slate-400 leading-relaxed whitespace-pre-wrap">${escapeHtml(successInstruction)}</div>
                    </div>
                `;
            }

            container.innerHTML = html;
        }

        document.getElementById('accountDetailModal').classList.remove('hidden');
    }

    function closeAccountModal() {
        document.getElementById('accountDetailModal').classList.add('hidden');
    }

    function copyHistoryConfig() {
        const copyText = document.getElementById("historyConfigText");
        copyText.select();
        copyText.setSelectionRange(0, 99999);
        navigator.clipboard.writeText(copyText.value);
        
        const btnText = document.getElementById("copyHistoryBtnText");
        btnText.innerText = "Tersalin!";
        setTimeout(() => {
            btnText.innerText = "Salin Akun";
        }, 2000);
    }

    function copyHistoryConfigPlain() {
        const copyText = document.getElementById("historyConfigTextPlain").innerText;
        navigator.clipboard.writeText(copyText);
        alert("Konfigurasi VPN berhasil disalin ke clipboard!");
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
</script>

<!-- COMPLAINT MODAL -->
<div id="complaintModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm hidden transition-all duration-300">
    <div class="bg-white dark:bg-slate-950 rounded-3xl w-full max-w-md border border-slate-100 dark:border-slate-800 p-6 sm:p-8 shadow-2xl relative">
        <!-- Close Button -->
        <button onclick="closeComplaintModal()" class="absolute top-5 right-5 p-2 text-slate-400 hover:text-slate-650 dark:hover:text-slate-250 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-900 transition-all duration-200">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path></svg>
        </button>

        <form id="complaintForm" action="" method="POST" class="space-y-6">
            @csrf
            <div>
                <h3 class="text-2xl font-extrabold text-slate-800 dark:text-slate-100 leading-tight">Ajukan Komplain</h3>
                <p id="complaintModalOrderId" class="text-xs text-slate-400 dark:text-slate-500 mt-1">Order ID: ORD-XXXXXX</p>
            </div>

            <div class="space-y-2">
                <label for="complaintReason" class="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Alasan Komplain</label>
                <textarea id="complaintReason" name="reason" rows="4" required placeholder="Jelaskan alasan komplain Anda secara detail (misal: akun tidak bisa digunakan, config error, dll)..." class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 focus:border-blue-500 focus:bg-white focus:outline-none rounded-2xl text-sm text-slate-800 dark:text-slate-100 transition-all duration-200 custom-scrollbar resize-none"></textarea>
            </div>

            <div class="text-[11px] text-amber-700 dark:text-amber-400 bg-amber-50 dark:bg-amber-950/20 p-3.5 rounded-xl border border-amber-200/30">
                Pemberitahuan: Komplain Anda akan langsung diteruskan ke Seller untuk ditindaklanjuti.
            </div>

            <div class="pt-4 border-t border-slate-100 dark:border-slate-800 flex justify-end space-x-3">
                <button type="button" onclick="closeComplaintModal()" class="px-5 py-3 text-slate-500 hover:text-slate-650 dark:text-slate-450 dark:hover:text-slate-350 text-xs font-bold rounded-2xl transition">
                    Batal
                </button>
                <button type="submit" class="px-6 py-3 bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold rounded-2xl transition shadow-lg shadow-rose-500/10 flex items-center space-x-2">
                    <span>Kirim Komplain</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function openComplaintModal(orderId) {
        document.getElementById('complaintModalOrderId').innerText = 'Order ID: ' + orderId;
        document.getElementById('complaintForm').action = `/orders/${orderId}/complain`;
        document.getElementById('complaintModal').classList.remove('hidden');
    }

    function closeComplaintModal() {
        document.getElementById('complaintModal').classList.add('hidden');
        document.getElementById('complaintReason').value = '';
    }
</script>
@endsection
