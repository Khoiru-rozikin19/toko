@extends('layouts.app', ['title' => 'Manajemen Pengguna'])

@section('content')
<div class="space-y-6 sm:space-y-8">
    
    <!-- Header -->
    <div class="border-b border-slate-200 dark:border-slate-800 pb-5">
        <p class="text-sm text-slate-500 dark:text-slate-400">Kelola persetujuan verifikasi akun baru, permohonan upgrade Seller, serta kelola peran dan status akun seluruh pengguna.</p>
    </div>

    <!-- Tab Buttons -->
    <div class="flex border-b border-slate-200 dark:border-slate-800 space-x-6 overflow-x-auto pb-px">
        <button onclick="switchTab('tab-new-accounts')" id="btn-new-accounts" class="tab-btn pb-4 text-sm font-bold border-b-2 border-blue-600 text-blue-600 dark:text-blue-400 transition-all duration-200 focus:outline-none whitespace-nowrap">
            Persetujuan Akun Baru ({{ $newAccounts->count() }})
        </button>
        <button onclick="switchTab('tab-seller-requests')" id="btn-seller-requests" class="tab-btn pb-4 text-sm font-semibold border-b-2 border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-300 transition-all duration-200 focus:outline-none whitespace-nowrap">
            Permintaan Upgrade Seller ({{ $sellerRequests->count() }})
        </button>
        <button onclick="switchTab('tab-all-users')" id="btn-all-users" class="tab-btn pb-4 text-sm font-semibold border-b-2 border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-300 transition-all duration-200 focus:outline-none whitespace-nowrap">
            Semua Pengguna ({{ $allUsers->count() }})
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
                    <p class="text-sm text-slate-400 dark:text-slate-600 mt-1">Seluruh akun pendaftar baru saat ini telah selesai ditinjau.</p>
                </div>
            @else
                <div class="bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 rounded-3xl overflow-hidden shadow-sm">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-slate-50 dark:bg-slate-800/50 border-b border-slate-100 dark:border-slate-800">
                                    <th class="px-6 py-4 text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Nama Lengkap</th>
                                    <th class="px-6 py-4 text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Email</th>
                                    <th class="px-6 py-4 text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Nomor HP</th>
                                    <th class="px-6 py-4 text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Tanggal Daftar</th>
                                    <th class="px-6 py-4 text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                @foreach($newAccounts as $user)
                                    <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition-colors">
                                        <td class="px-6 py-4">
                                            <div class="font-bold text-slate-800 dark:text-slate-100">{{ $user->name }}</div>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-300">{{ $user->email }}</td>
                                        <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-300 font-mono">{{ $user->phone ?? '-' }}</td>
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
                                                    <button type="submit" class="px-4 py-2 bg-rose-50 dark:bg-rose-950/20 hover:bg-rose-100 text-rose-600 dark:text-rose-400 border border-rose-100 dark:border-rose-900/30 rounded-xl text-xs font-bold active:scale-95 transition-all duration-200">
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
                    <p class="text-sm text-slate-400 dark:text-slate-600 mt-1">Tidak ada permohonan upgrade Seller yang masuk saat ini.</p>
                </div>
            @else
                <div class="bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 rounded-3xl overflow-hidden shadow-sm">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-slate-50 dark:bg-slate-800/50 border-b border-slate-100 dark:border-slate-800">
                                    <th class="px-6 py-4 text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Nama Lengkap</th>
                                    <th class="px-6 py-4 text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Email</th>
                                    <th class="px-6 py-4 text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Nomor HP</th>
                                    <th class="px-6 py-4 text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Tanggal Pengajuan</th>
                                    <th class="px-6 py-4 text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                @foreach($sellerRequests as $user)
                                    <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition-colors">
                                        <td class="px-6 py-4">
                                            <div class="font-bold text-slate-800 dark:text-slate-100">{{ $user->name }}</div>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-300">{{ $user->email }}</td>
                                        <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-300 font-mono">{{ $user->phone ?? '-' }}</td>
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
                                                    <button type="submit" class="px-4 py-2 bg-rose-50 dark:bg-rose-950/20 hover:bg-rose-100 text-rose-600 dark:text-rose-400 border border-rose-100 dark:border-rose-900/30 rounded-xl text-xs font-bold active:scale-95 transition-all duration-200">
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

        <!-- TAB 3: SEMUA PENGGUNA -->
        <div id="tab-all-users" class="tab-content hidden transition-all duration-300">
            @if($allUsers->isEmpty())
                <div class="bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 rounded-3xl p-12 text-center shadow-sm">
                    <div class="w-16 h-16 bg-slate-50 dark:bg-slate-800/50 rounded-full flex items-center justify-center text-slate-400 dark:text-slate-500 mx-auto mb-4">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                    </div>
                    <h3 class="text-lg font-bold text-slate-800 dark:text-slate-200">Tidak Ada Pengguna Lain</h3>
                    <p class="text-sm text-slate-400 dark:text-slate-600 mt-1">Belum ada akun pengguna lain terdaftar di sistem.</p>
                </div>
            @else
                <div class="bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 rounded-3xl overflow-hidden shadow-sm">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-slate-50 dark:bg-slate-800/50 border-b border-slate-100 dark:border-slate-800">
                                    <th class="px-6 py-4 text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Pengguna</th>
                                    <th class="px-6 py-4 text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Peran (Role)</th>
                                    <th class="px-6 py-4 text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-4 text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider text-right">Tindakan</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                @foreach($allUsers as $user)
                                    <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition-colors">
                                        <!-- Nama, Email & No HP -->
                                        <td class="px-6 py-4">
                                            <div class="font-bold text-slate-800 dark:text-slate-100">{{ $user->name }}</div>
                                            <div class="text-xs text-slate-600 dark:text-slate-400">{{ $user->email }}</div>
                                            <div class="text-[11px] text-slate-400 dark:text-slate-500 font-mono mt-0.5">{{ $user->phone ?? '-' }}</div>
                                        </td>
                                        
                                        <!-- Perubahan Peran (Role Dropdown) -->
                                        <td class="px-6 py-4">
                                            <form action="{{ url('/admin/users/'.$user->id.'/update-role') }}" method="POST" class="flex items-center space-x-2">
                                                @csrf
                                                <select name="role" class="px-3 py-1.5 text-xs bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-800 dark:text-slate-100 focus:outline-none focus:border-blue-500 transition-all duration-200">
                                                    <option value="buyer" {{ $user->role === 'buyer' ? 'selected' : '' }}>Buyer</option>
                                                    <option value="seller" {{ $user->role === 'seller' ? 'selected' : '' }}>Seller</option>
                                                    <option value="admin" {{ $user->role === 'admin' ? 'selected' : '' }}>Admin</option>
                                                </select>
                                                <button type="submit" class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-[10px] font-bold active:scale-95 transition-all duration-200">
                                                    Ubah
                                                </button>
                                            </form>
                                        </td>
                                        
                                        <!-- Badge Status -->
                                        <td class="px-6 py-4">
                                            @if($user->is_verified)
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 dark:bg-emerald-950/30 text-emerald-800 dark:text-emerald-400 border border-emerald-200/10">
                                                    Aktif
                                                </span>
                                            @else
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-rose-100 dark:bg-rose-950/30 text-rose-800 dark:text-rose-400 border border-rose-200/10">
                                                    Suspended
                                                </span>
                                            @endif
                                        </td>
                                        
                                        <!-- Aksi Cepat (Toggle Status & Hapus) -->
                                        <td class="px-6 py-4 text-right">
                                            <div class="flex justify-end space-x-2">
                                                <form action="{{ url('/admin/users/'.$user->id.'/toggle-status') }}" method="POST">
                                                    @csrf
                                                    @if($user->is_verified)
                                                        <button type="submit" class="px-3 py-1.5 bg-amber-50 dark:bg-amber-950/20 border border-amber-200/20 text-amber-700 dark:text-amber-400 rounded-xl text-xs font-bold active:scale-95 transition-all duration-200">
                                                            Suspend
                                                        </button>
                                                    @else
                                                        <button type="submit" class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold active:scale-95 transition-all duration-200">
                                                            Aktifkan
                                                        </button>
                                                    @endif
                                                </form>
                                                
                                                <form action="{{ url('/admin/users/'.$user->id.'/delete') }}" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin menghapus akun {{ $user->name }} secara permanen?')">
                                                    @csrf
                                                    <button type="submit" class="px-3 py-1.5 bg-rose-50 dark:bg-rose-950/20 border border-rose-100 dark:border-rose-900/30 text-rose-600 dark:text-rose-400 rounded-xl text-xs font-bold active:scale-95 transition-all duration-200">
                                                        Hapus
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
        let btnId;
        if (tabId === 'tab-new-accounts') {
            btnId = 'btn-new-accounts';
        } else if (tabId === 'tab-seller-requests') {
            btnId = 'btn-seller-requests';
        } else {
            btnId = 'btn-all-users';
        }
        let activeBtn = document.getElementById(btnId);
        activeBtn.classList.remove('border-transparent', 'text-slate-500', 'dark:text-slate-400', 'font-semibold');
        activeBtn.classList.add('border-blue-600', 'text-blue-600', 'dark:text-blue-400', 'font-bold');
    }
</script>
@endsection
