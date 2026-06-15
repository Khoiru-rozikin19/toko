@extends('layouts.app', ['title' => 'Profil Saya'])

@section('content')
<div class="space-y-6 sm:space-y-8 max-w-3xl">
    
    <!-- Header -->
    <div class="border-b border-slate-200 dark:border-slate-800 pb-5">
        <h2 class="text-2xl sm:text-3xl font-extrabold text-slate-800 dark:text-slate-100 tracking-tight">Profil Saya</h2>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Perbarui informasi profil Anda dan ganti password akun Anda</p>
    </div>

    <!-- Error Alert -->
    @if($errors->any())
        <div class="p-4 bg-rose-50 dark:bg-rose-950/30 border border-rose-200 dark:border-rose-900/50 text-rose-800 dark:text-rose-400 rounded-2xl text-sm space-y-1">
            @foreach($errors->all() as $error)
                <p class="font-medium">• {{ $error }}</p>
            @endforeach
        </div>
    @endif

    <!-- Profile Form Card -->
    <div class="bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 rounded-3xl p-5 sm:p-8 shadow-sm">
        
        <form action="{{ route('profile.update') }}" method="POST" class="space-y-6">
            @csrf

            <!-- Name -->
            <div class="space-y-2">
                <label for="name" class="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Nama Lengkap</label>
                <input type="text" id="name" name="name" value="{{ old('name', $user->name) }}" required class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 focus:border-blue-500 focus:bg-white focus:outline-none rounded-2xl text-sm text-slate-800 dark:text-slate-100 transition-all duration-200">
            </div>

            <!-- Email & Phone in responsive grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Email -->
                <div class="space-y-2">
                    <label for="email" class="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Alamat Email</label>
                    <input type="email" id="email" name="email" value="{{ old('email', $user->email) }}" required class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 focus:border-blue-500 focus:bg-white focus:outline-none rounded-2xl text-sm text-slate-800 dark:text-slate-100 transition-all duration-200">
                </div>

                <!-- Phone -->
                <div class="space-y-2">
                    <label for="phone" class="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Nomor Telepon</label>
                    <input type="text" id="phone" name="phone" value="{{ old('phone', $user->phone) }}" required class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 focus:border-blue-500 focus:bg-white focus:outline-none rounded-2xl text-sm text-slate-800 dark:text-slate-100 transition-all duration-200">
                </div>
            </div>

            <!-- Divider for Password Section -->
            <div class="border-t border-slate-100 dark:border-slate-800 pt-6">
                <h3 class="text-base font-bold text-slate-800 dark:text-slate-200 mb-1">Ganti Password (Opsional)</h3>
                <p class="text-xs text-slate-400 mb-4">Kosongkan kolom di bawah jika Anda tidak ingin mengganti password</p>
            </div>

            <!-- Current Password -->
            <div class="space-y-2">
                <label for="current_password" class="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Password Lama</label>
                <input type="password" id="current_password" name="current_password" placeholder="Masukkan password lama Anda..." class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 focus:border-blue-500 focus:bg-white focus:outline-none rounded-2xl text-sm text-slate-800 dark:text-slate-100 transition-all duration-200">
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- New Password -->
                <div class="space-y-2">
                    <label for="new_password" class="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Password Baru</label>
                    <input type="password" id="new_password" name="new_password" placeholder="Minimal 6 karakter..." class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 focus:border-blue-500 focus:bg-white focus:outline-none rounded-2xl text-sm text-slate-800 dark:text-slate-100 transition-all duration-200">
                </div>

                <!-- Confirm New Password -->
                <div class="space-y-2">
                    <label for="new_password_confirmation" class="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Konfirmasi Password Baru</label>
                    <input type="password" id="new_password_confirmation" name="new_password_confirmation" placeholder="Ulangi password baru..." class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 focus:border-blue-500 focus:bg-white focus:outline-none rounded-2xl text-sm text-slate-800 dark:text-slate-100 transition-all duration-200">
                </div>
            </div>

            <!-- Save Button -->
            <div class="pt-6 border-t border-slate-100 dark:border-slate-800 flex justify-end">
                <button type="submit" class="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-2xl text-sm font-bold shadow-lg shadow-blue-500/20 active:scale-95 transition-all duration-200">
                    Perbarui Profil
                </button>
            </div>
        </form>

    </div>
</div>
@endsection
