<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Admin - RZK</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;850&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #f4f6fc;
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4">

    <div class="w-full max-w-md bg-white border border-slate-100 rounded-3xl p-8 shadow-2xl space-y-6 relative overflow-hidden">
        
        <!-- Background Gradient Decor -->
        <div class="absolute -top-10 -right-10 w-40 h-40 bg-blue-500/10 rounded-full blur-2xl"></div>
        <div class="absolute -bottom-10 -left-10 w-40 h-40 bg-indigo-500/10 rounded-full blur-2xl"></div>

        <div class="text-center relative">
            <div class="bg-blue-600 w-12 h-12 rounded-2xl flex items-center justify-center text-white mx-auto shadow-lg shadow-blue-500/20 mb-4">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                </svg>
            </div>
            <h2 class="text-2xl font-black text-slate-800 tracking-tight">Portal Seller Admin</h2>
            <p class="text-xs text-slate-400 mt-1">Masukkan kredensial Anda untuk masuk ke sistem manajemen.</p>
        </div>

        @if(session('success'))
            <div class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-2xl text-xs flex items-center space-x-2">
                <svg class="w-4 h-4 text-emerald-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <p class="font-medium">{{ session('success') }}</p>
            </div>
        @endif
        
        @if(session('error'))
            <div class="p-4 bg-rose-50 border border-rose-200 text-rose-800 rounded-2xl text-xs flex items-center space-x-2">
                <svg class="w-4 h-4 text-rose-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                <p class="font-medium">{{ session('error') }}</p>
            </div>
        @endif

        @if($errors->any())
            <div class="p-4 bg-rose-50 border border-rose-200 text-rose-800 rounded-2xl text-xs space-y-1">
                @foreach($errors->all() as $error)
                    <p class="font-medium">• {{ $error }}</p>
                @endforeach
            </div>
        @endif

        <form action="{{ route('login') }}" method="POST" class="space-y-4 relative">
            @csrf
            
            <div>
                <label for="email" class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Alamat Email</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" required placeholder="admin@vpn.com" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 focus:border-blue-500 focus:bg-white focus:outline-none rounded-2xl text-sm font-medium text-slate-800 transition-all duration-200">
            </div>

            <div>
                <label for="password" class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Password</label>
                <input type="password" id="password" name="password" required placeholder="••••••••" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 focus:border-blue-500 focus:bg-white focus:outline-none rounded-2xl text-sm font-medium text-slate-800 transition-all duration-200">
            </div>

            <div class="flex items-center justify-between pt-2">
                <label class="flex items-center space-x-2 text-xs text-slate-500 cursor-pointer">
                    <input type="checkbox" name="remember" class="w-4 h-4 text-blue-600 bg-slate-100 border-slate-300 rounded focus:ring-blue-500">
                    <span>Ingat saya</span>
                </label>
                
                <a href="{{ route('register') }}" class="text-xs text-blue-600 hover:underline font-semibold">
                    Daftar Akun Baru
                </a>
            </div>

            <button type="submit" class="w-full py-3.5 bg-blue-600 hover:bg-blue-700 text-white rounded-2xl text-sm font-bold shadow-lg shadow-blue-500/25 active:scale-95 transition-all duration-200">
                Masuk ke Dashboard
            </button>
        </form>
        
    </div>

</body>
</html>
