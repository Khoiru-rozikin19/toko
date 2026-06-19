@extends('layouts.app', ['title' => $title])

@section('content')
<div class="space-y-6 sm:space-y-10">
    
    <!-- Header -->
    <div class="border-b border-slate-200 dark:border-slate-800 pb-5">
        <h2 class="text-2xl sm:text-3xl font-extrabold text-slate-800 dark:text-slate-100 tracking-tight">{{ $title }}</h2>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Kelola aduan dan sengketa transaksi dari pembeli untuk produk Anda</p>
    </div>

    <!-- Complaints Card & Table -->
    <div class="bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 rounded-3xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 dark:bg-slate-900/60 border-b border-slate-100 dark:border-slate-800 text-xs font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">
                        <th class="py-4.5 px-6">ID Order / Pembeli</th>
                        <th class="py-4.5 px-6">Produk</th>
                        <th class="py-4.5 px-6">Aduan / Masalah</th>
                        <th class="py-4.5 px-6">Status</th>
                        <th class="py-4.5 px-6">Tanggal</th>
                        <th class="py-4.5 px-6 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-sm font-medium text-slate-700 dark:text-slate-300">
                    @forelse($complaints as $complaint)
                        @php
                            $order = $complaint->order;
                        @endphp
                        <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/20 transition-all duration-150">
                            <!-- Buyer & Order Info -->
                            <td class="py-4.5 px-6">
                                <div class="font-mono text-xs text-blue-600 dark:text-blue-400 font-bold">
                                    {{ $complaint->order_id }}
                                </div>
                                <div class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                                    {{ $complaint->user->name ?? 'User Dihapus' }} ({{ $complaint->user->email ?? '-' }})
                                </div>
                            </td>

                            <!-- Product Info -->
                            <td class="py-4.5 px-6">
                                <div class="font-bold text-slate-800 dark:text-slate-200">
                                    {{ $order->product->name ?? 'Produk Dihapus' }}
                                </div>
                                <div class="text-[10px] text-slate-400 font-normal mt-0.5">
                                    Total bayar: Rp {{ number_format($order->total_amount ?? 0, 0, ',', '.') }}
                                </div>
                            </td>

                            <!-- Complaint Reason -->
                            <td class="py-4.5 px-6 max-w-xs sm:max-w-md">
                                <div class="text-xs text-slate-600 dark:text-slate-400 whitespace-pre-wrap leading-relaxed">
                                    {{ $complaint->reason }}
                                </div>
                            </td>

                            <!-- Status Badge -->
                            <td class="py-4.5 px-6">
                                @if($complaint->status === 'pending')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-50 dark:bg-amber-950/20 text-amber-700 dark:text-amber-400">
                                        Pending
                                    </span>
                                @elseif($complaint->status === 'resolved')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-50 dark:bg-emerald-950/20 text-emerald-700 dark:text-emerald-400">
                                        Selesai (Refund)
                                    </span>
                                @elseif($complaint->status === 'rejected')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-rose-50 dark:bg-rose-950/30 text-rose-700 dark:text-rose-400">
                                        Ditolak
                                    </span>
                                @endif
                            </td>

                            <!-- Created At -->
                            <td class="py-4.5 px-6 text-xs text-slate-400">
                                {{ $complaint->created_at->format('d/m/Y H:i') }}
                            </td>

                            <!-- Actions -->
                            <td class="py-4.5 px-6 text-center">
                                @if($complaint->status === 'pending')
                                    <div class="flex items-center justify-center space-x-2">
                                        <!-- Resolve Form -->
                                        <form action="{{ route('admin.complaints.resolve', $complaint->id) }}" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin menyetujui komplain ini? Dana akan langsung dikembalikan ke pembeli.')">
                                            @csrf
                                            <button type="submit" class="inline-flex items-center space-x-1 px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold transition-all duration-200">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"></path></svg>
                                                <span>Setujui</span>
                                            </button>
                                        </form>

                                        <!-- Reject Form -->
                                        <form action="{{ route('admin.complaints.reject', $complaint->id) }}" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin menolak komplain ini? Saldo penjualan akan dilepaskan ke dompet Anda.')">
                                            @csrf
                                            <button type="submit" class="inline-flex items-center space-x-1 px-3 py-1.5 bg-rose-600 hover:bg-rose-700 text-white rounded-xl text-xs font-bold transition-all duration-200">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path></svg>
                                                <span>Tolak</span>
                                            </button>
                                        </form>
                                    </div>
                                @else
                                    <span class="text-xs text-slate-400">-</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-12 text-center text-slate-400">
                                Belum ada aduan komplain dari pembeli.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        @if($complaints->count() > 0)
            <div class="p-6 border-t border-slate-100 dark:border-slate-800">
                {{ $complaints->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
