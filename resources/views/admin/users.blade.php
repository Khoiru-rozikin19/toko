@extends('layouts.app', ['title' => 'Manajemen Pengguna'])

@section('content')
<div class="space-y-8">
    
    <!-- Header -->
    <div class="border-b border-slate-200 dark:border-slate-800 pb-5">
        <h2 class="text-3xl font-extrabold text-slate-850 dark:text-slate-100 tracking-tight">Manajemen Pengguna</h2>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Kelola persetujuan verifikasi akun baru dan pengajuan peran Seller Anda</p>
    </div>

    <!-- Tab Buttons -->
    <div class="flex border-b border-slate-200 dark:border-slate-800 space-x-6">
        <button onclick="switchTab('tab-new-accounts')" id="btn-new-accounts" class="tab-btn pb-4 text-sm font-bold border-b-2 border-blue-600 text-blue-600 dark:text-blue-400 transition-all duration-200 focus:outline-none">
            Persetujuan Akun Baru ({{ $newAccounts->count() }})
        </button>
        <button onclick="switchTab('tab-seller-requests')" id="btn-seller-requests" class="tab-btn pb-4 text-sm font-semibold border-b-2 border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-300 transition-all duration-200 focus:outline-none">
            Permintaan Upgrade Seller ({{ $sellerRequests->count() }})
        </button>
    </div>

    <!-- Tab Contents -->
    <div class="space-y-6">
        
        <!-- TAB 1: PERSETUJUAN AKUN BARU -->
        <div id="tab-new-accounts" class="tab-content transition-all duration-300">
            @if($newAccounts->isEmpty())
                <div class="bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 rounded-3xl p-12 text-center shadow-sm">
                    <div class="w-16 h-16 bg-slate-50 dark:bg-slate-800/50 rounded-full flex items-center justify-center text-slate-400 dark:text-slate-500 mx-auto mb-4">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </div>
                    <h3 class="text-lg font-bold text-slate-800 dark:text-slate-200">Tidak Ada Pendaftar Baru</h3>
                    <p class="text-sm text-slate-400 dark:text-slate-550 mt-1">Seluruh akun pendaftar baru saat ini telah selesai ditinjau.</p>
                </div>
            @else
                <div class="bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 rounded-3xl overflow-hidden shadow-sm">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-slate-50 dark:bg-slate-850/50 border-b border-slate-100 dark:border-slate-800">
                                    <th class="px-6 py-4 text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Nama Lengkap</th>
                                    <th class="px-6 py-4 text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Email</th>
                                    <th class="px-6 py-4 text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Nomor HP</th>
                                    <th class="px-6 py-4 text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Tanggal Daftar</th>
                                    <th class="px-6 py-4 text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                @foreach($newAccounts as $user)
                                    <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-850/30 transition-colors">
                                        <td class="px-6 py-4">
                                            <div class="font-bold text-slate-800 dark:text-slate-100">{{ $user->name }}</div>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-slate-650 dark:text-slate-300">{{ $user->email }}</td>
                                        <td class="px-6 py-4 text-sm text-slate-650 dark:text-slate-300 font-mono">{{ $user->phone ?? '-' }}</td>
                                        <td class="px-6 py-4 text-xs text-slate-400 dark:text-slate-500">{{ $user->created_at->isoFormat('D MMMM YYYY, H:mm') }}</td>
                                        <td class="px-6 py-4 text-right">
                                            <div class="flex justify-end space-x-2">
                                                <form action="{{ url('/admin/users/'.$user->id.'/approve-account') }}" method="POST">
                                                    @csrf
                                                    <button type="submit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold shadow-md shadow-emerald-500/10 active:scale-95 transition-all duration-200">
                                                        Setujui
                                                    </button>
                                                </form>
                                                <form action="{{ url('/admin/users/'.$user->id.'/reject-account') }}" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin menolak dan menghapus pendaftaran ini?')">
                                                    @csrf
                                                    <button type="submit" class="px-4 py-2 bg-rose-50 dark:bg-rose-950/20 hover:bg-rose-100 text-rose-600 dark:text-rose-450 border border-rose-100 dark:border-rose-900/30 rounded-xl text-xs font-bold active:scale-95 transition-all duration-200">
                                                        Tolak
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>

        <!-- TAB 2: PERMINTAAN UPGRADE SELLER -->
        <div id="tab-seller-requests" class="tab-content hidden transition-all duration-300">
            @if($sellerRequests->isEmpty())
                <div class="bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 rounded-3xl p-12 text-center shadow-sm">
                    <div class="w-16 h-16 bg-slate-50 dark:bg-slate-800/50 rounded-full flex items-center justify-center text-slate-400 dark:text-slate-500 mx-auto mb-4">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                    </div>
                    <h3 class="text-lg font-bold text-slate-800 dark:text-slate-200">Tidak Ada Permintaan Upgrade</h3>
                    <p class="text-sm text-slate-400 dark:text-slate-550 mt-1">Tidak ada permohonan upgrade Seller yang masuk saat ini.</p>
                </div>
            @else
                <div class="bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 rounded-3xl overflow-hidden shadow-sm">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-slate-50 dark:bg-slate-850/50 border-b border-slate-100 dark:border-slate-800">
                                    <th class="px-6 py-4 text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Nama Lengkap</th>
                                    <th class="px-6 py-4 text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Email</th>
                                    <th class="px-6 py-4 text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Nomor HP</th>
                                    <th class="px-6 py-4 text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Tanggal Pengajuan</th>
                                    <th class="px-6 py-4 text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                @foreach($sellerRequests as $user)
                                    <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-850/30 transition-colors">
                                        <td class="px-6 py-4">
                                            <div class="font-bold text-slate-800 dark:text-slate-100">{{ $user->name }}</div>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-slate-650 dark:text-slate-300">{{ $user->email }}</td>
                                        <td class="px-6 py-4 text-sm text-slate-650 dark:text-slate-300 font-mono">{{ $user->phone ?? '-' }}</td>
                                        <td class="px-6 py-4 text-xs text-slate-400 dark:text-slate-500">{{ $user->updated_at->isoFormat('D MMMM YYYY, H:mm') }}</td>
                                        <td class="px-6 py-4 text-right">
                                            <div class="flex justify-end space-x-2">
                                                <form action="{{ url('/admin/users/'.$user->id.'/approve-seller') }}" method="POST">
                                                    @csrf
                                                    <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold shadow-md shadow-blue-500/10 active:scale-95 transition-all duration-200">
                                                        Setujui
                                                    </button>
                                                </form>
                                                <form action="{{ url('/admin/users/'.$user->id.'/reject-seller') }}" method="POST">
                                                    @csrf
                                                    <button type="submit" class="px-4 py-2 bg-rose-50 dark:bg-rose-950/20 hover:bg-rose-100 text-rose-600 dark:text-rose-450 border border-rose-100 dark:border-rose-900/30 rounded-xl text-xs font-bold active:scale-95 transition-all duration-200">
                                                        Tolak
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>

    </div>
</div>

<script>
    function switchTab(tabId) {
        // Hide all tab contents
        document.querySelectorAll('.tab-content').forEach(function(el) {
            el.classList.add('hidden');
        });

        // Remove active button styles
        document.querySelectorAll('.tab-btn').forEach(function(el) {
            el.classList.remove('border-blue-600', 'text-blue-600', 'dark:text-blue-400', 'font-bold');
            el.classList.add('border-transparent', 'text-slate-500', 'dark:text-slate-400', 'font-semibold');
        });

        // Show target tab content
        document.getElementById(tabId).classList.remove('hidden');

        // Set active button styles
        let btnId = tabId === 'tab-new-accounts' ? 'btn-new-accounts' : 'btn-seller-requests';
        let activeBtn = document.getElementById(btnId);
        activeBtn.classList.remove('border-transparent', 'text-slate-500', 'dark:text-slate-400', 'font-semibold');
        activeBtn.classList.add('border-blue-600', 'text-blue-600', 'dark:text-blue-400', 'font-bold');
    }
</script>
@endsection
