@extends('layouts.app', ['title' => 'Profil & Detail Akun'])

@section('content')
<div class="max-w-3xl mx-auto space-y-8">
    
    <!-- Profile Header / Initial Avatar -->
    <div class="flex flex-col items-center justify-center space-y-4 pb-6 border-b border-slate-200 dark:border-slate-800">
        <div class="w-28 h-28 rounded-full bg-blue-600 flex items-center justify-center text-white font-black text-5xl shadow-xl shadow-blue-500/20 transform hover:scale-105 transition-transform duration-200 select-none">
            {{ Auth::check() ? strtoupper(substr($user->name, 0, 1)) : 'G' }}
        </div>
        <div class="text-center">
            <h2 class="text-3xl font-extrabold text-slate-850 dark:text-slate-100 tracking-tight">{{ $user->name }}</h2>
            <div class="mt-1 flex items-center justify-center space-x-2">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-blue-50 dark:bg-blue-950/30 text-blue-700 dark:text-blue-400">
                    Role: {{ ucfirst($user->role) }}
                </span>
                @if(Auth::user()->is_verified)
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 dark:bg-emerald-950/30 text-emerald-700 dark:text-emerald-400">
                        Terverifikasi
                    </span>
                @endif
            </div>
        </div>
    </div>

    <!-- Error/Validation Alerts -->
    @if($errors->any())
        <div class="p-4 bg-rose-50 dark:bg-rose-950/30 border border-rose-200 dark:border-rose-900/50 text-rose-800 dark:text-rose-400 rounded-2xl text-sm space-y-1 shadow-sm">
            @foreach($errors->all() as $error)
                <p class="font-medium">• {{ $error }}</p>
            @endforeach
        </div>
    @endif

    <!-- Balance Card -->
    <div class="rounded-3xl p-6 text-white shadow-xl shadow-blue-500/10 flex flex-col sm:flex-row sm:items-center sm:justify-between relative overflow-hidden border border-blue-500/10 dark:border-blue-400/20" style="background: linear-gradient(to bottom right, #2563eb, #4338ca);">
        <!-- Background Decoration Circles -->
        <div class="absolute -right-10 -top-10 w-44 h-44 bg-white/10 rounded-full blur-2xl pointer-events-none"></div>
        <div class="absolute -left-15 -bottom-15 w-40 h-40 bg-blue-500/30 rounded-full blur-2xl pointer-events-none"></div>
        
        <div class="relative z-10">
            <span class="text-xs uppercase tracking-widest text-blue-200/80 font-bold">Total Saldo Saya</span>
            <h2 class="text-xl sm:text-2xl font-bold tracking-tight mt-1">Rp {{ number_format($currentBalance, 0, ',', '.') }}</h2>
        </div>
        
        <div class="relative z-10 mt-5 sm:mt-0">
            <a href="{{ route('balance.index') }}" class="inline-flex items-center space-x-2 px-5 py-3 bg-white text-blue-600 hover:bg-blue-50 transition-all font-bold rounded-2xl text-xs shadow-lg shadow-black/10 active:scale-95 duration-200">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"></path>
                </svg>
                <span>Isi Saldo & Riwayat</span>
            </a>
        </div>
    </div>

    <!-- Account Details Form -->
    <div class="bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 rounded-3xl p-6 sm:p-8 shadow-sm">
        
        <form action="{{ route('profile.update') }}" method="POST" class="space-y-6">
            @csrf

            <h3 class="text-base font-bold text-slate-800 dark:text-slate-205 pb-3 border-b border-slate-50 dark:border-slate-850">Informasi Pribadi</h3>

            <!-- Name -->
            <div class="space-y-2">
                <label for="name" class="block text-xs font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">Nama Lengkap</label>
                <input type="text" id="name" name="name" value="{{ old('name', $user->name) }}" required class="w-full px-4 py-3.5 bg-slate-50 dark:bg-slate-950/20 border border-slate-200 dark:border-slate-800 focus:border-blue-500 focus:bg-white dark:focus:bg-slate-900 focus:outline-none rounded-2xl text-sm text-slate-800 dark:text-slate-100 transition-all duration-200 font-medium">
            </div>

            <!-- Email, Phone, Telegram Grid -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Email -->
                <div class="space-y-2">
                    <label for="email" class="block text-xs font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">Alamat Email</label>
                    <input type="email" id="email" name="email" value="{{ old('email', $user->email) }}" required class="w-full px-4 py-3.5 bg-slate-50 dark:bg-slate-950/20 border border-slate-200 dark:border-slate-800 focus:border-blue-500 focus:bg-white dark:focus:bg-slate-900 focus:outline-none rounded-2xl text-sm text-slate-800 dark:text-slate-100 transition-all duration-200 font-medium">
                </div>

                <!-- Phone -->
                <div class="space-y-2">
                    <label for="phone" class="block text-xs font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">Nomor Telepon / HP</label>
                    <input type="text" id="phone" name="phone" value="{{ old('phone', $user->phone) }}" required class="w-full px-4 py-3.5 bg-slate-50 dark:bg-slate-950/20 border border-slate-200 dark:border-slate-800 focus:border-blue-500 focus:bg-white dark:focus:bg-slate-900 focus:outline-none rounded-2xl text-sm text-slate-800 dark:text-slate-100 transition-all duration-200 font-medium font-mono">
                </div>

                <!-- Telegram Chat ID -->
                <div class="space-y-2">
                    <label for="telegram_chat_id" class="block text-xs font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">Telegram Chat ID (Opsional)</label>
                    <input type="text" id="telegram_chat_id" name="telegram_chat_id" value="{{ old('telegram_chat_id', $user->telegram_chat_id) }}" placeholder="Contoh: 123456789" class="w-full px-4 py-3.5 bg-slate-50 dark:bg-slate-950/20 border border-slate-200 dark:border-slate-800 focus:border-blue-500 focus:bg-white dark:focus:bg-slate-900 focus:outline-none rounded-2xl text-sm text-slate-800 dark:text-slate-100 transition-all duration-200 font-medium font-mono">
                </div>
            </div>

            <!-- Change Password Action and Section -->
            <div class="pt-4 border-t border-slate-150 dark:border-slate-850 space-y-5">
                <div class="flex items-center justify-between">
                    <div>
                        <h4 class="text-sm font-bold text-slate-800 dark:text-slate-250">Keamanan Akun</h4>
                        <p class="text-xs text-slate-450">Kosongkan jika Anda tidak ingin mengganti password</p>
                    </div>
                    <button type="button" id="togglePasswordBtn" class="flex items-center space-x-2 px-4 py-2 border border-slate-200 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-850 text-slate-650 dark:text-slate-350 rounded-xl text-xs font-bold transition active:scale-95 duration-200 shadow-sm">
                        <svg class="w-4 h-4 text-slate-550" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 7a2 2 0 012 2m-5 4a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3zm6 3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <span>Ubah Password</span>
                    </button>
                </div>

                <!-- Hidden password inputs section -->
                <div id="passwordSection" class="hidden p-5 bg-slate-50 dark:bg-slate-950/30 rounded-2xl border border-slate-150 dark:border-slate-800 space-y-4">
                    <!-- Current Password -->
                    <div class="space-y-2">
                        <label for="current_password" class="block text-xs font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">Password Lama</label>
                        <input type="password" id="current_password" name="current_password" placeholder="Masukkan password lama Anda..." class="w-full px-4 py-3 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 focus:border-blue-500 focus:outline-none rounded-xl text-sm text-slate-800 dark:text-slate-105 transition-all">
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- New Password -->
                        <div class="space-y-2">
                            <label for="new_password" class="block text-xs font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">Password Baru</label>
                            <input type="password" id="new_password" name="new_password" placeholder="Minimal 6 karakter..." class="w-full px-4 py-3 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 focus:border-blue-500 focus:outline-none rounded-xl text-sm text-slate-800 dark:text-slate-105 transition-all">
                        </div>

                        <!-- Confirm New Password -->
                        <div class="space-y-2">
                            <label for="new_password_confirmation" class="block text-xs font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">Konfirmasi Password Baru</label>
                            <input type="password" id="new_password_confirmation" name="new_password_confirmation" placeholder="Ulangi password baru..." class="w-full px-4 py-3 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 focus:border-blue-500 focus:outline-none rounded-xl text-sm text-slate-800 dark:text-slate-105 transition-all">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Submit Button -->
            <div class="pt-6 border-t border-slate-100 dark:border-slate-850 flex justify-end">
                <button type="submit" class="px-6 py-3.5 bg-blue-600 hover:bg-blue-700 text-white rounded-2xl text-sm font-bold shadow-lg shadow-blue-500/20 active:scale-95 transition-all duration-200">
                    Simpan Perubahan
                </button>
            </div>
        </form>

    </div>

    <!-- Upgrade Account Portal (If role is buyer) -->
    @if(Auth::user()->role === 'buyer')
        <div class="bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 rounded-3xl p-6 sm:p-8 shadow-sm">
            <h3 class="text-base font-bold text-slate-850 dark:text-slate-205 pb-3 border-b border-slate-50 dark:border-slate-850 flex items-center space-x-2">
                <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25"></path>
                </svg>
                <span>Upgrade Akun Seller</span>
            </h3>
            
            <div class="mt-4 flex flex-col md:flex-row md:items-center justify-between gap-5 bg-slate-50 dark:bg-slate-950/20 p-5 rounded-2xl border border-slate-100/50 dark:border-slate-850">
                <div class="space-y-1">
                    <h4 class="font-bold text-slate-800 dark:text-slate-200 text-sm">Ajukan Sebagai Seller</h4>
                    <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed max-w-xl">
                        Mulai berbisnis dengan meng-upgrade akun Anda untuk dapat menjual produk digital secara langsung di platform kami dan kelola stok Anda sendiri.
                    </p>
                </div>
                
                <div>
                    @if(Auth::user()->seller_request === 'none')
                        <form action="{{ route('buyer.request-seller') }}" method="POST">
                            @csrf
                            <button type="submit" class="w-full md:w-auto px-5 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-2xl text-xs font-bold shadow-md shadow-blue-500/10 active:scale-95 transition-all duration-200 text-center whitespace-nowrap">
                                Ajukan Sekarang
                            </button>
                        </form>
                    @elseif(Auth::user()->seller_request === 'pending')
                        <div class="inline-flex items-center space-x-2.5 px-4 py-3 bg-amber-50 dark:bg-amber-950/20 text-amber-700 dark:text-amber-400 border border-amber-250/20 rounded-2xl text-xs font-bold">
                            <span class="w-2.5 h-2.5 rounded-full bg-amber-500 animate-pulse flex-shrink-0"></span>
                            <span>Menunggu Persetujuan Admin</span>
                        </div>
                    @elseif(Auth::user()->seller_request === 'rejected')
                        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
                            <div class="inline-flex items-center space-x-2.5 px-4 py-3 bg-rose-50 dark:bg-rose-950/20 text-rose-700 dark:text-rose-400 border border-rose-250/20 rounded-2xl text-xs font-bold">
                                <span class="w-2.5 h-2.5 rounded-full bg-rose-500 flex-shrink-0"></span>
                                <span>Pengajuan Terakhir Ditolak</span>
                            </div>
                            <form action="{{ route('buyer.request-seller') }}" method="POST">
                                @csrf
                                <button type="submit" class="w-full px-5 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-2xl text-xs font-bold active:scale-95 transition-all duration-200 text-center whitespace-nowrap">
                                    Ajukan Lagi
                                </button>
                            </form>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif

</div>

<!-- JavaScript to handle password toggle -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const togglePasswordBtn = document.getElementById('togglePasswordBtn');
        const passwordSection = document.getElementById('passwordSection');

        if (togglePasswordBtn && passwordSection) {
            togglePasswordBtn.addEventListener('click', function() {
                if (passwordSection.classList.contains('hidden')) {
                    // Show password fields
                    passwordSection.classList.remove('hidden');
                    
                    // Update button content
                    this.innerHTML = `
                        <svg class="w-4 h-4 text-rose-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                        <span class="text-rose-600 dark:text-rose-450">Batal Ubah</span>
                    `;
                    
                    // Focus current password input
                    document.getElementById('current_password').focus();
                } else {
                    // Hide password fields
                    passwordSection.classList.add('hidden');
                    
                    // Update button content
                    this.innerHTML = `
                        <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 7a2 2 0 012 2m-5 4a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3zm6 3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <span>Ubah Password</span>
                    `;
                    
                    // Clear field inputs
                    document.getElementById('current_password').value = '';
                    document.getElementById('new_password').value = '';
                    document.getElementById('new_password_confirmation').value = '';
                }
            });
        }
    });
</script>
@endsection
