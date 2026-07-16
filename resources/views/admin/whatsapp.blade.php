@extends('layouts.app', ['title' => 'Manajemen Bot WhatsApp'])

@section('content')
<div class="space-y-6 sm:space-y-8 max-w-4xl">
    
    <!-- Header -->
    <div class="border-b border-slate-200 dark:border-slate-800 pb-5">
        <p class="text-sm text-slate-550 dark:text-slate-400">Hubungkan nomor WhatsApp khusus Anda dan atur alur OTP & notifikasi turnamen.</p>
    </div>

    @if(session('success'))
        <div class="p-4 bg-emerald-50 dark:bg-emerald-955/20 border border-emerald-250 text-emerald-600 rounded-2xl text-xs font-bold shadow-xs">
            🎉 {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="p-4 bg-red-50 dark:bg-red-955/20 border border-red-200/30 text-red-600 rounded-2xl text-xs font-bold shadow-xs">
            ❌ {{ session('error') }}
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 sm:gap-8">
        
        <!-- Status & QR Code (Left Column) -->
        <div class="lg:col-span-1 space-y-6">
            <div class="bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 rounded-3xl p-6 shadow-sm flex flex-col items-center justify-center text-center relative overflow-hidden">
                <!-- Header Icon -->
                <div class="w-14 h-14 bg-emerald-500/10 text-emerald-500 rounded-full flex items-center justify-center mb-4">
                    <svg class="w-7 h-7" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path></svg>
                </div>
                
                <h4 class="font-extrabold text-sm text-slate-850 dark:text-slate-100">Status Koneksi</h4>
                
                <!-- Status Badges -->
                <div class="mt-2.5 mb-6" id="status-badge-container">
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-[10px] font-extrabold uppercase bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400">
                        🔄 Mengecek...
                    </span>
                </div>

                <!-- QR Code Container -->
                <div id="qr-container" class="hidden flex flex-col items-center justify-center p-4 bg-slate-50 dark:bg-slate-950/40 rounded-2xl border border-slate-100 dark:border-slate-900 w-full">
                    <canvas id="qr-canvas" class="max-w-full rounded shadow-sm bg-white p-2"></canvas>
                    <p class="text-[10px] text-slate-500 dark:text-slate-400 mt-3 font-bold">Pindai QR Code ini menggunakan menu Perangkat Tertaut pada aplikasi WhatsApp Anda.</p>
                </div>

                <!-- Ready Container -->
                <div id="ready-container" class="hidden py-4 text-emerald-600 dark:text-emerald-400 font-extrabold text-xs">
                    🟢 WhatsApp Terhubung & Siap Digunakan!
                </div>

                <!-- Disconnect Button -->
                <div id="disconnect-container" class="hidden mt-6 w-full pt-4 border-t border-slate-100 dark:border-slate-800">
                    <form action="{{ route('admin.whatsapp.disconnect') }}" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin mengeluarkan sesi bot WhatsApp ini?');">
                        @csrf
                        <button type="submit" class="w-full py-3 bg-red-50 hover:bg-red-100 text-red-650 font-bold rounded-2xl text-xs transition duration-150">
                            🚪 Putuskan Perangkat
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Settings Form & Testing (Right Column) -->
        <div class="lg:col-span-2 space-y-6 sm:space-y-8">
            <!-- Configuration Card -->
            <div class="bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 rounded-3xl p-6 sm:p-8 shadow-sm">
                <h4 class="font-extrabold text-sm text-slate-850 dark:text-slate-100 mb-5 border-b border-slate-100 dark:border-slate-850 pb-3 flex items-center space-x-2">
                    <span>⚙️</span> <span>Konfigurasi Bot WhatsApp</span>
                </h4>

                <form action="{{ route('admin.whatsapp.settings') }}" method="POST" class="space-y-5">
                    @csrf

                    <!-- Enabled Toggle -->
                    <div class="space-y-1.5">
                        <label for="whatsapp_bot_enabled" class="block text-xs font-bold text-slate-550 dark:text-slate-400 uppercase tracking-wider">Aktifkan Bot WhatsApp</label>
                        <select id="whatsapp_bot_enabled" name="whatsapp_bot_enabled" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-950/20 border border-slate-200 dark:border-slate-800 focus:border-blue-500 focus:bg-white focus:outline-none rounded-2xl text-sm font-semibold transition-all duration-200">
                            <option value="1" {{ $botEnabled === '1' ? 'selected' : '' }}>✅ Aktif</option>
                            <option value="0" {{ $botEnabled === '0' ? 'selected' : '' }}>❌ Nonaktif</option>
                        </select>
                    </div>

                    <!-- OTP Enabled Toggle -->
                    <div class="space-y-1.5">
                        <label for="whatsapp_bot_otp_enabled" class="block text-xs font-bold text-slate-550 dark:text-slate-400 uppercase tracking-wider">Aktifkan Verifikasi OTP Registrasi</label>
                        <select id="whatsapp_bot_otp_enabled" name="whatsapp_bot_otp_enabled" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-950/20 border border-slate-200 dark:border-slate-800 focus:border-blue-500 focus:bg-white focus:outline-none rounded-2xl text-sm font-semibold transition-all duration-200">
                            <option value="1" {{ $otpEnabled === '1' ? 'selected' : '' }}>✅ Aktif</option>
                            <option value="0" {{ $otpEnabled === '0' ? 'selected' : '' }}>❌ Nonaktif (Gunakan Persetujuan Manual Admin)</option>
                        </select>
                    </div>

                    <!-- Group ID / JID -->
                    <div class="space-y-1.5">
                        <label for="whatsapp_bot_group_id" class="block text-xs font-bold text-slate-550 dark:text-slate-400 uppercase tracking-wider">ID Grup WhatsApp (Tujuan Broadcast)</label>
                        <input type="text" id="whatsapp_bot_group_id" name="whatsapp_bot_group_id" value="{{ $groupId }}" placeholder="Contoh: 120363025555555555@g.us atau ID grup Anda" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-950/20 border border-slate-200 dark:border-slate-800 focus:border-blue-500 focus:bg-white focus:outline-none rounded-2xl text-sm font-semibold transition-all duration-200">
                        <p class="text-[10px] text-slate-450 dark:text-slate-500 font-medium">Gunakan ID grup lengkap berakhiran <code>@g.us</code>. Bot akan mem-broadcast update pendaftaran & status turnamen ke grup ini.</p>
                    </div>

                    <!-- API URL Gateway -->
                    <div class="space-y-1.5">
                        <label for="whatsapp_bot_api_url" class="block text-xs font-bold text-slate-550 dark:text-slate-400 uppercase tracking-wider">URL API Gateway Lokal (Port 3000)</label>
                        <input type="url" id="whatsapp_bot_api_url" name="whatsapp_bot_api_url" value="{{ $apiUrl }}" placeholder="http://127.0.0.1:3000" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-950/20 border border-slate-200 dark:border-slate-800 focus:border-blue-500 focus:bg-white focus:outline-none rounded-2xl text-sm font-semibold transition-all duration-200" required>
                    </div>

                    <!-- Save Button -->
                    <div class="pt-4 border-t border-slate-100 dark:border-slate-800 flex justify-end">
                        <button type="submit" class="px-6 py-3.5 bg-blue-600 hover:bg-blue-700 text-white rounded-2xl text-xs font-extrabold shadow-lg shadow-blue-500/20 active:scale-95 transition-all duration-200">
                            💾 Simpan Konfigurasi
                        </button>
                    </div>
                </form>
            </div>

            <!-- Testing Card -->
            <div class="bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 rounded-3xl p-6 sm:p-8 shadow-sm">
                <h4 class="font-extrabold text-sm text-slate-850 dark:text-slate-100 mb-5 border-b border-slate-100 dark:border-slate-850 pb-3 flex items-center space-x-2">
                    <span>✉️</span> <span>Uji Coba Kirim Pesan</span>
                </h4>

                <div class="space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="space-y-1.5">
                            <label for="test_phone" class="block text-xs font-bold text-slate-550 dark:text-slate-400 uppercase tracking-wider">Nomor WA Tujuan</label>
                            <input type="text" id="test_phone" placeholder="Contoh: 08123456789" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-950/20 border border-slate-200 dark:border-slate-800 focus:border-blue-500 focus:bg-white focus:outline-none rounded-2xl text-sm font-semibold transition-all duration-200">
                        </div>
                        <div class="space-y-1.5">
                            <label for="test_message" class="block text-xs font-bold text-slate-550 dark:text-slate-400 uppercase tracking-wider">Isi Pesan</label>
                            <input type="text" id="test_message" placeholder="Halo! Ini pesan tes." class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-950/20 border border-slate-200 dark:border-slate-800 focus:border-blue-500 focus:bg-white focus:outline-none rounded-2xl text-sm font-semibold transition-all duration-200">
                        </div>
                    </div>
                    <div class="flex justify-end">
                        <button type="button" onclick="sendTestMessage()" class="px-5 py-3 bg-emerald-650 hover:bg-emerald-700 text-white font-extrabold rounded-2xl text-xs transition duration-200 shadow-md shadow-emerald-500/10">
                            🚀 Kirim Pesan Uji Coba
                        </button>
                    </div>
                    <div id="test-result" class="hidden p-3 rounded-xl text-xs font-bold"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Load QRCode rendering helper -->
<script src="https://cdn.jsdelivr.net/npm/qrcode@1.4.4/build/qrcode.min.js"></script>
<script>
    const qrContainer = document.getElementById('qr-container');
    const readyContainer = document.getElementById('ready-container');
    const disconnectContainer = document.getElementById('disconnect-container');
    const badgeContainer = document.getElementById('status-badge-container');
    const qrCanvas = document.getElementById('qr-canvas');

    let currentStatus = '';

    function checkStatus() {
        fetch('{{ route("admin.whatsapp.status_ajax") }}')
            .then(res => res.json())
            .then(data => {
                // Update Badge UI
                let badgeHTML = '';
                
                if (data.status === 'ready') {
                    badgeHTML = `<span class="inline-flex items-center px-3 py-1 rounded-full text-[10px] font-extrabold uppercase bg-emerald-100 dark:bg-emerald-950/30 text-emerald-700 dark:text-emerald-400">🟢 Terhubung</span>`;
                    qrContainer.classList.add('hidden');
                    readyContainer.classList.remove('hidden');
                    disconnectContainer.classList.remove('hidden');
                } else if (data.status === 'qr' && data.qr) {
                    badgeHTML = `<span class="inline-flex items-center px-3 py-1 rounded-full text-[10px] font-extrabold uppercase bg-yellow-100 dark:bg-yellow-950/30 text-yellow-700 dark:text-yellow-400">🟡 Perlu Scan</span>`;
                    readyContainer.classList.add('hidden');
                    disconnectContainer.classList.add('hidden');
                    qrContainer.classList.remove('hidden');
                    
                    // Render QR Code to Canvas
                    QRCode.toCanvas(qrCanvas, data.qr, { width: 200, margin: 1 }, function (error) {
                        if (error) console.error(error);
                    });
                } else if (data.status === 'offline') {
                    badgeHTML = `<span class="inline-flex items-center px-3 py-1 rounded-full text-[10px] font-extrabold uppercase bg-red-100 dark:bg-red-950/30 text-red-700 dark:text-red-400">🔴 Gateway Offline</span>`;
                    qrContainer.classList.add('hidden');
                    readyContainer.classList.add('hidden');
                    disconnectContainer.classList.add('hidden');
                } else {
                    badgeHTML = `<span class="inline-flex items-center px-3 py-1 rounded-full text-[10px] font-extrabold uppercase bg-blue-100 dark:bg-blue-950/30 text-blue-700 dark:text-blue-400">🔵 Menghubungkan...</span>`;
                    qrContainer.classList.add('hidden');
                    readyContainer.classList.add('hidden');
                    disconnectContainer.classList.add('hidden');
                }
                
                badgeContainer.innerHTML = badgeHTML;
                currentStatus = data.status;
            })
            .catch(err => {
                badgeContainer.innerHTML = `<span class="inline-flex items-center px-3 py-1 rounded-full text-[10px] font-extrabold uppercase bg-red-100 dark:bg-red-950/30 text-red-700 dark:text-red-400">🔴 Server Offline</span>`;
                qrContainer.classList.add('hidden');
                readyContainer.classList.add('hidden');
                disconnectContainer.classList.add('hidden');
            });
    }

    // Poll status every 4 seconds
    setInterval(checkStatus, 4000);
    // Initial check
    checkStatus();

    function sendTestMessage() {
        const phone = document.getElementById('test_phone').value;
        const message = document.getElementById('test_message').value;
        const resultDiv = document.getElementById('test-result');

        if (!phone || !message) {
            alert('Silakan lengkapi nomor WA tujuan dan isi pesan.');
            return;
        }

        resultDiv.className = 'p-3 rounded-xl text-xs font-bold bg-slate-100 text-slate-650';
        resultDiv.textContent = 'Sedang mengirim...';
        resultDiv.classList.remove('hidden');

        fetch('{{ $apiUrl }}/send-message', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ phone, message })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                resultDiv.className = 'p-3 rounded-xl text-xs font-bold bg-emerald-50 text-emerald-600 border border-emerald-200/50';
                resultDiv.textContent = '🚀 Pesan berhasil dikirim ke gateway!';
            } else {
                resultDiv.className = 'p-3 rounded-xl text-xs font-bold bg-red-50 text-red-650 border border-red-200/30';
                resultDiv.textContent = '❌ Gagal mengirim: ' + (data.error || 'Gateway offline');
            }
        })
        .catch(err => {
            resultDiv.className = 'p-3 rounded-xl text-xs font-bold bg-red-50 text-red-650 border border-red-200/30';
            resultDiv.textContent = '❌ Terjadi kesalahan jaringan. Pastikan API gateway sudah dinyalakan.';
        });
    }
</script>
@endsection
