<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifikasi OTP - RZK Store</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;850&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
        }
    </style>
    <script>
        // Inline script to prevent theme flash
        if (localStorage.getItem('color-theme') === 'dark' || (!('color-theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>
</head>
<body class="min-h-screen flex items-center justify-center p-4 bg-slate-50 dark:bg-slate-950 text-slate-800 dark:text-slate-100 transition-colors duration-200">

    <div class="w-full max-w-md bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 rounded-3xl p-8 shadow-2xl space-y-6 relative overflow-hidden">
        
        <!-- Background Gradient Decor -->
        <div class="absolute -top-10 -right-10 w-40 h-40 bg-emerald-500/10 rounded-full blur-2xl"></div>
        <div class="absolute -bottom-10 -left-10 w-40 h-40 bg-blue-500/10 rounded-full blur-2xl"></div>

        <div class="text-center relative">
            <div class="bg-emerald-550 dark:bg-emerald-600 w-12 h-12 rounded-2xl flex items-center justify-center text-white mx-auto shadow-lg shadow-emerald-500/20 mb-4">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.57-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"></path>
                </svg>
            </div>
            <h2 class="text-2xl font-black text-slate-850 dark:text-slate-100 tracking-tight">Verifikasi Akun Anda</h2>
            <p class="text-xs text-slate-400 dark:text-slate-500 mt-1">Kami telah mengirimkan 6-digit kode verifikasi OTP ke nomor WhatsApp Anda: <strong class="text-slate-650 dark:text-slate-400 font-extrabold">+{{ $user->phone }}</strong></p>
        </div>

        @if(session('success'))
            <div class="p-4 bg-emerald-50 dark:bg-emerald-950/20 border border-emerald-250 dark:border-emerald-900/30 text-emerald-800 dark:text-emerald-400 rounded-2xl text-xs flex items-center space-x-2">
                <svg class="w-4 h-4 text-emerald-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <p class="font-medium">{{ session('success') }}</p>
            </div>
        @endif

        @if($errors->any())
            <div class="p-4 bg-rose-50 dark:bg-rose-950/20 border border-rose-250 dark:border-rose-900/30 text-rose-800 dark:text-rose-400 rounded-2xl text-xs space-y-1">
                @foreach($errors->all() as $error)
                    <p class="font-medium">• {{ $error }}</p>
                @endforeach
            </div>
        @endif

        <form action="{{ route('verify_otp') }}" method="POST" class="space-y-6 relative">
            @csrf
            
            <div class="space-y-2 text-center">
                <label for="otp" class="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-2">Kode OTP WhatsApp</label>
                <input type="text" id="otp" name="otp" required autocomplete="one-time-code" maxlength="6" placeholder="******" class="w-full text-center tracking-[1em] text-2xl font-black py-4 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 focus:border-emerald-500 focus:bg-white dark:focus:bg-slate-900 focus:outline-none rounded-2xl text-slate-850 dark:text-slate-100 transition-all duration-200 uppercase">
            </div>

            <button type="submit" class="w-full py-4 bg-emerald-600 hover:bg-emerald-700 text-white rounded-2xl text-xs font-extrabold shadow-lg shadow-emerald-500/20 active:scale-95 transition-all duration-200">
                ✅ Konfirmasi Verifikasi
            </button>
        </form>

        <div class="pt-4 border-t border-slate-100 dark:border-slate-800 text-center relative">
            <p class="text-xs text-slate-450 dark:text-slate-500 font-medium">Tidak menerima kode?</p>
            
            <form action="{{ route('resend_otp') }}" method="POST" class="mt-2 inline-block">
                @csrf
                <button type="submit" id="btn-resend" class="text-xs font-extrabold text-blue-600 hover:text-blue-700 dark:text-blue-400 hover:underline disabled:opacity-50 disabled:no-underline">
                    Kirim Ulang OTP
                </button>
            </form>
            <p id="resend-countdown" class="text-[10px] text-slate-400 dark:text-slate-500 font-bold mt-1 hidden"></p>
        </div>

    </div>

    <script>
        const btnResend = document.getElementById('btn-resend');
        const countdownText = document.getElementById('resend-countdown');

        function startResendTimer() {
            let secondsLeft = 60;
            btnResend.disabled = true;
            countdownText.classList.remove('hidden');
            
            const interval = setInterval(() => {
                secondsLeft--;
                countdownText.textContent = `Mohon tunggu ${secondsLeft} detik untuk mengirim ulang.`;
                
                if (secondsLeft <= 0) {
                    clearInterval(interval);
                    btnResend.disabled = false;
                    countdownText.classList.add('hidden');
                }
            }, 1000);
        }

        // Jalankan timer jika baru mengirim OTP
        @if(session('success'))
            startResendTimer();
        @endif

        // Validasi input hanya angka
        document.getElementById('otp').addEventListener('input', function(e) {
            e.target.value = e.target.value.replace(/[^0-9]/g, '');
        });
    </script>
</body>
</html>
