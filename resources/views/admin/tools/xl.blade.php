@extends('layouts.app', ['title' => 'Cek & Reset Kuota XL'])

@section('content')
<div class="space-y-6 sm:space-y-8 max-w-6xl mx-auto">
    
    <!-- Header -->
    <div class="border-b border-slate-200 dark:border-slate-800 pb-5">
        <h2 class="text-2xl sm:text-3xl font-extrabold text-slate-800 dark:text-slate-100 tracking-tight">Cek & Reset Kuota XL</h2>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Kelola sesi otentikasi MyXL pelanggan dan reset kuota yang bermasalah</p>
    </div>

    <!-- Main Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 lg:gap-8">
        
        <!-- Left Side: Session Manager & Register Form -->
        <div class="space-y-6 lg:col-span-1">
            
            <!-- Register / Login OTP Card -->
            <div class="bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800/80 rounded-3xl p-5 sm:p-6 shadow-sm">
                <h3 class="text-lg font-bold text-slate-800 dark:text-slate-100 mb-4 flex items-center">
                    <svg class="w-5 h-5 text-blue-500 mr-2" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zM4 19.235v-.11a6.375 6.375 0 0112.75 0v.109A12.318 12.318 0 0110.374 21c-2.331 0-4.512-.645-6.374-1.766z"></path></svg>
                    Daftarkan Nomor Baru
                </h3>
                
                <form id="otpForm" class="space-y-4">
                    <div>
                        <label for="xl_phone" class="block text-xs font-bold text-slate-400 dark:text-slate-500 uppercase mb-2">Nomor XL</label>
                        <input type="text" id="xl_phone" placeholder="Contoh: 087860356425" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 focus:border-blue-500 focus:bg-white focus:outline-none rounded-2xl text-sm font-medium text-slate-800 dark:text-slate-100 transition-all duration-200">
                    </div>

                    <div>
                        <label for="xl_label" class="block text-xs font-bold text-slate-400 dark:text-slate-500 uppercase mb-2">Nama Pelanggan (Label)</label>
                        <input type="text" id="xl_label" placeholder="Contoh: Budi Santoso" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 focus:border-blue-500 focus:bg-white focus:outline-none rounded-2xl text-sm font-medium text-slate-800 dark:text-slate-100 transition-all duration-200">
                    </div>

                    <!-- OTP Code field (hidden initially) -->
                    <div id="otpCodeContainer" class="hidden">
                        <label for="xl_otp" class="block text-xs font-bold text-slate-400 dark:text-slate-500 uppercase mb-2">Kode OTP (6-Digit SMS)</label>
                        <input type="text" id="xl_otp" maxlength="6" placeholder="Masukkan 6-digit angka..." class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 focus:border-blue-500 focus:bg-white focus:outline-none rounded-2xl text-sm font-medium text-slate-800 dark:text-slate-100 text-center tracking-widest font-mono transition-all duration-200">
                    </div>

                    <div id="otpMessage" class="hidden text-xs font-semibold p-3 rounded-xl"></div>

                    <!-- Action buttons -->
                    <div class="pt-2">
                        <button type="button" id="btnRequestOtp" onclick="sendOtpRequest()" class="w-full py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-2xl text-sm font-bold shadow-lg shadow-blue-500/20 active:scale-95 transition-all duration-200">
                            Kirim OTP
                        </button>
                        <button type="button" id="btnVerifyOtp" onclick="verifyOtpCode()" class="hidden w-full py-3 bg-emerald-600 hover:bg-emerald-700 text-white rounded-2xl text-sm font-bold shadow-lg shadow-emerald-500/20 active:scale-95 transition-all duration-200">
                            Verifikasi & Simpan
                        </button>
                    </div>
                </form>
            </div>

            <!-- Active Sessions List -->
            <div class="bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800/80 rounded-3xl p-5 sm:p-6 shadow-sm">
                <h3 class="text-lg font-bold text-slate-800 dark:text-slate-100 mb-4">
                    Sesi Nomor Terdaftar
                </h3>
                
                <div class="space-y-3 max-h-[350px] overflow-y-auto pr-1">
                    @forelse($sessions as $session)
                        <div id="sessionCard_{{ $session->id }}" class="group border border-slate-100 dark:border-slate-800/80 rounded-2xl p-4 bg-slate-50/50 dark:bg-slate-950/20 hover:border-blue-200 dark:hover:border-blue-900/50 hover:bg-white dark:hover:bg-slate-900 transition-all duration-200">
                            <div class="flex items-start justify-between">
                                <div class="min-w-0">
                                    <span class="block font-bold text-slate-850 dark:text-slate-200 truncate">{{ $session->label }}</span>
                                    <span class="block text-xs text-slate-400 font-mono mt-0.5">+{{ $session->msisdn }}</span>
                                </div>
                                <div class="flex items-center space-x-1 opacity-80 group-hover:opacity-100 transition-all">
                                    <!-- Check Quota Button -->
                                    <button onclick="checkQuota({{ $session->id }})" class="p-2 bg-blue-50 hover:bg-blue-100 dark:bg-blue-950/50 dark:hover:bg-blue-900/50 text-blue-600 dark:text-blue-400 rounded-xl transition-all" title="Cek Kuota">
                                        <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"></path><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                    </button>
                                    
                                    <!-- Delete Session -->
                                    <form action="{{ route('admin.tools.xl.delete', $session->id) }}" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin menghapus sesi untuk nomor ini?')" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="p-2 bg-rose-50 hover:bg-rose-100 dark:bg-rose-950/50 dark:hover:bg-rose-900/50 text-rose-600 dark:text-rose-400 rounded-xl transition-all" title="Hapus Sesi">
                                            <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @empty
                        <p class="text-xs text-slate-400 dark:text-slate-500 text-center py-6">Belum ada nomor XL terdaftar.</p>
                    @endforelse
                </div>
            </div>
            
        </div>

        <!-- Right Side: Quota Inspector / Details Panel -->
        <div class="lg:col-span-2">
            <div id="quotaDetailsPanel" class="bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800/80 rounded-3xl p-6 shadow-sm min-h-[450px] flex flex-col justify-center items-center text-center">
                
                <!-- Placeholder / Initial state -->
                <div id="quotaPlaceholder" class="space-y-4 max-w-sm">
                    <div class="w-16 h-16 bg-blue-50 dark:bg-blue-950/30 text-blue-600 dark:text-blue-400 rounded-2xl flex items-center justify-center mx-auto shadow-sm">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 11-3 0m3 0a1.5 1.5 0 10-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-9.75 0h9.75"></path></svg>
                    </div>
                    <div>
                        <h4 class="font-extrabold text-slate-800 dark:text-slate-100 text-lg">Inspektur Kuota XL</h4>
                        <p class="text-xs text-slate-400 dark:text-slate-500 mt-1.5 leading-relaxed">Pilih salah satu nomor XL terdaftar di sebelah kiri dan klik tombol "Cek Kuota" untuk memuat info sisa kuota dan daftar paket aktif.</p>
                    </div>
                </div>

                <!-- Loading State (hidden) -->
                <div id="quotaLoading" class="hidden space-y-4">
                    <div class="w-12 h-12 border-4 border-blue-600 border-t-transparent rounded-full animate-spin mx-auto"></div>
                    <p class="text-xs font-semibold text-slate-500 dark:text-slate-400">Menghubungi server MyXL...</p>
                </div>

                <!-- Content Panel (hidden initially) -->
                <div id="quotaContent" class="hidden w-full text-left space-y-6">
                    
                    <!-- Profile header inside panel -->
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between bg-blue-50/50 dark:bg-blue-950/20 border border-blue-100/30 dark:border-blue-900/20 p-5 rounded-2xl gap-4">
                        <div class="min-w-0">
                            <span id="qInfoName" class="text-lg font-black text-slate-800 dark:text-slate-100 block truncate">Budi Santoso</span>
                            <span id="qInfoPhone" class="text-xs font-bold text-slate-400 dark:text-slate-500 mt-0.5 block font-mono">+6287860356425</span>
                        </div>
                        <div class="flex items-center gap-6">
                            <div>
                                <span class="text-[10px] text-slate-400 dark:text-slate-500 font-bold uppercase tracking-wider block leading-none">Sisa Pulsa</span>
                                <span id="qInfoBalance" class="text-lg font-black text-blue-600 dark:text-blue-400 mt-1 block">Rp 50.000</span>
                            </div>
                            <div class="border-l border-slate-200 dark:border-slate-800 h-10"></div>
                            <div>
                                <span class="text-[10px] text-slate-400 dark:text-slate-500 font-bold uppercase tracking-wider block leading-none">Masa Aktif</span>
                                <span id="qInfoActive" class="text-xs font-bold text-slate-700 dark:text-slate-350 mt-1 block">30 Des 2026</span>
                            </div>
                        </div>
                    </div>

                    <!-- Package lists -->
                    <div class="space-y-3">
                        <h4 class="text-xs font-extrabold text-slate-400 dark:text-slate-500 uppercase tracking-wider block">Daftar Paket Aktif</h4>
                        <div id="packagesListContainer" class="space-y-3">
                            <!-- JS populated -->
                        </div>
                    </div>
                </div>

            </div>
        </div>

    </div>

    <!-- API Configurations & Simulation Toggle (Admin Only) -->
    <div class="bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800/80 rounded-3xl p-5 sm:p-6 shadow-sm">
        <h3 class="text-lg font-bold text-slate-800 dark:text-slate-100 mb-4 flex items-center">
            <svg class="w-5.5 h-5.5 text-slate-500 mr-2" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
            Konfigurasi & Parameter API MyXL
        </h3>
        
        <form action="{{ route('admin.tools.xl.settings.update') }}" method="POST" class="space-y-4">
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="md:col-span-2">
                    <label for="myxl_api_base_url" class="block text-xs font-bold text-slate-400 dark:text-slate-500 uppercase mb-2">MyXL API Base URL</label>
                    <input type="url" id="myxl_api_base_url" name="myxl_api_base_url" value="{{ $baseUrl }}" required class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 focus:border-blue-500 focus:bg-white focus:outline-none rounded-2xl text-sm font-medium text-slate-800 dark:text-slate-100 transition-all duration-200">
                </div>
                <div>
                    <label for="myxl_simulation_mode" class="block text-xs font-bold text-slate-400 dark:text-slate-500 uppercase mb-2">Mode Simulasi (Bypass)</label>
                    <select id="myxl_simulation_mode" name="myxl_simulation_mode" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 focus:border-blue-500 focus:bg-white focus:outline-none rounded-2xl text-sm font-medium text-slate-800 dark:text-slate-100 transition-all duration-200">
                        <option value="1" {{ $isSimMode ? 'selected' : '' }}>🟢 Simulasi Aktif (Demo)</option>
                        <option value="0" {{ !$isSimMode ? 'selected' : '' }}>🔴 API Riil (Tembak Langsung)</option>
                    </select>
                </div>
            </div>

            <div>
                <label for="myxl_custom_headers" class="block text-xs font-bold text-slate-400 dark:text-slate-500 uppercase mb-2">Custom Headers (Format JSON)</label>
                <textarea id="myxl_custom_headers" name="myxl_custom_headers" rows="3" placeholder='{"X-Channel": "MYXL", "User-Agent": "Custom-Agent-Value"}' class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 focus:border-blue-500 focus:bg-white focus:outline-none rounded-2xl text-sm font-medium text-slate-800 dark:text-slate-100 font-mono transition-all duration-200">{{ $customHeaders }}</textarea>
                <p class="text-xs text-slate-400 mt-1.5 leading-relaxed">Opsional. Gunakan jika Anda butuh menimpa default headers (seperti otentikasi signature tambahan) dari penyedia H2H/bypass.</p>
            </div>

            <div class="flex justify-end pt-2">
                <button type="submit" class="px-6 py-3 bg-slate-800 hover:bg-slate-950 dark:bg-slate-700 dark:hover:bg-slate-600 text-white rounded-2xl text-sm font-bold shadow-md transition-all duration-200">
                    Simpan Konfigurasi
                </button>
            </div>
        </form>
    </div>

</div>

<script>
    let activeSessionId = null;

    // Send request OTP
    function sendOtpRequest() {
        const phone = document.getElementById('xl_phone').value.trim();
        const label = document.getElementById('xl_label').value.trim();
        const msgDiv = document.getElementById('otpMessage');
        const btnReq = document.getElementById('btnRequestOtp');
        
        if (!phone) {
            alert('Silakan masukkan nomor XL.');
            return;
        }

        msgDiv.className = 'text-xs font-semibold p-3 rounded-xl bg-blue-50 dark:bg-blue-950/20 text-blue-700 dark:text-blue-400';
        msgDiv.textContent = 'Meminta OTP dari server XL...';
        msgDiv.classList.remove('hidden');
        btnReq.disabled = true;

        fetch("{{ route('admin.tools.xl.otp.request') }}", {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({ phone: phone })
        })
        .then(res => res.json())
        .then(data => {
            btnReq.disabled = false;
            if (data.success) {
                msgDiv.className = 'text-xs font-semibold p-3 rounded-xl bg-emerald-50 dark:bg-emerald-950/20 text-emerald-700 dark:text-emerald-400';
                msgDiv.textContent = data.message;
                
                // Show OTP input
                document.getElementById('otpCodeContainer').classList.remove('hidden');
                document.getElementById('xl_phone').disabled = true;
                btnReq.classList.add('hidden');
                document.getElementById('btnVerifyOtp').classList.remove('hidden');
                
                setTimeout(() => {
                    document.getElementById('xl_otp').focus();
                }, 50);
            } else {
                msgDiv.className = 'text-xs font-semibold p-3 rounded-xl bg-rose-50 dark:bg-rose-950/20 text-rose-700 dark:text-rose-400';
                msgDiv.textContent = data.message || 'Gagal meminta OTP.';
            }
        })
        .catch(err => {
            btnReq.disabled = false;
            console.error(err);
            msgDiv.className = 'text-xs font-semibold p-3 rounded-xl bg-rose-50 dark:bg-rose-950/20 text-rose-700 dark:text-rose-400';
            msgDiv.textContent = 'Kesalahan jaringan.';
        });
    }

    // Verify OTP
    function verifyOtpCode() {
        const phone = document.getElementById('xl_phone').value.trim();
        const otp = document.getElementById('xl_otp').value.trim();
        const label = document.getElementById('xl_label').value.trim();
        const msgDiv = document.getElementById('otpMessage');
        const btnVerify = document.getElementById('btnVerifyOtp');

        if (!otp || otp.length < 4) {
            alert('Silakan masukkan 6-digit OTP.');
            return;
        }

        msgDiv.className = 'text-xs font-semibold p-3 rounded-xl bg-blue-50 dark:bg-blue-950/20 text-blue-700 dark:text-blue-400';
        msgDiv.textContent = 'Memverifikasi kode OTP...';
        btnVerify.disabled = true;

        fetch("{{ route('admin.tools.xl.otp.verify') }}", {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({ phone: phone, otp: otp, label: label })
        })
        .then(res => res.json())
        .then(data => {
            btnVerify.disabled = false;
            if (data.success) {
                msgDiv.className = 'text-xs font-semibold p-3 rounded-xl bg-emerald-50 dark:bg-emerald-950/20 text-emerald-700 dark:text-emerald-400';
                msgDiv.textContent = data.message;
                
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            } else {
                msgDiv.className = 'text-xs font-semibold p-3 rounded-xl bg-rose-50 dark:bg-rose-950/20 text-rose-700 dark:text-rose-400';
                msgDiv.textContent = data.message || 'OTP salah.';
            }
        })
        .catch(err => {
            btnVerify.disabled = false;
            console.error(err);
            msgDiv.className = 'text-xs font-semibold p-3 rounded-xl bg-rose-50 dark:bg-rose-950/20 text-rose-700 dark:text-rose-400';
            msgDiv.textContent = 'Kesalahan jaringan.';
        });
    }

    // Check Quota
    function checkQuota(sessionId) {
        activeSessionId = sessionId;
        
        // Highlight active card
        document.querySelectorAll('[id^="sessionCard_"]').forEach(el => {
            el.classList.remove('border-blue-400', 'bg-blue-50/10', 'dark:bg-blue-950/10');
        });
        const card = document.getElementById(`sessionCard_${sessionId}`);
        if (card) {
            card.classList.add('border-blue-400', 'bg-blue-50/10', 'dark:bg-blue-950/10');
        }

        // Toggle panel views
        document.getElementById('quotaPlaceholder').classList.add('hidden');
        document.getElementById('quotaContent').classList.add('hidden');
        document.getElementById('quotaLoading').classList.remove('hidden');

        fetch(`/admin/tools/xl/${sessionId}/quota`)
        .then(res => res.json())
        .then(data => {
            document.getElementById('quotaLoading').classList.add('hidden');
            if (data.success) {
                document.getElementById('qInfoName').textContent = data.profile?.name || 'Pelanggan XL';
                document.getElementById('qInfoPhone').textContent = `+${data.profile?.phone || card.querySelector('.font-mono').textContent.replace('+', '')}`;
                
                // Format balance
                const balance = parseInt(data.profile?.balance) || 0;
                document.getElementById('qInfoBalance').textContent = 'Rp ' + balance.toLocaleString('id-ID');
                
                document.getElementById('qInfoActive').textContent = data.profile?.active_until || '-';

                // Populate packages list
                const list = document.getElementById('packagesListContainer');
                list.innerHTML = '';

                if (data.packages && data.packages.length > 0) {
                    data.packages.forEach(pkg => {
                        const cardDiv = document.createElement('div');
                        cardDiv.className = 'flex flex-col sm:flex-row sm:items-center justify-between p-4 rounded-2xl bg-slate-50 dark:bg-slate-950 border border-slate-100 dark:border-slate-800/80 gap-3 hover:border-slate-200 transition-all';
                        
                        cardDiv.innerHTML = `
                            <div class="min-w-0 flex-1">
                                <span class="block font-bold text-slate-800 dark:text-slate-250 truncate text-sm" title="${pkg.name}">${pkg.name}</span>
                                <div class="flex items-center gap-4 mt-1.5 text-xs text-slate-400 font-semibold">
                                    <span>Kuota: <strong class="text-slate-600 dark:text-slate-300 font-bold">${pkg.quota_remaining}</strong> / ${pkg.quota_total}</span>
                                    <span>Masa Aktif: <strong class="text-slate-600 dark:text-slate-300 font-bold">${pkg.expired_at ? pkg.expired_at.split(' ')[0] : '-'}</strong></span>
                                </div>
                            </div>
                            <button type="button" onclick="unsubscribe('${pkg.id}', '${pkg.name}')" class="px-3.5 py-2 hover:bg-rose-50 dark:hover:bg-rose-950/20 text-rose-600 hover:text-rose-700 dark:text-rose-400 dark:hover:text-rose-300 border border-rose-100 dark:border-rose-900/50 rounded-xl text-xs font-bold transition-all active:scale-95 whitespace-nowrap self-start sm:self-center" title="Hentikan Paket">
                                Hapus Paket
                            </button>
                        `;
                        list.appendChild(cardDiv);
                    });
                } else {
                    list.innerHTML = '<p class="text-xs text-slate-400 py-4 text-center">Tidak ada paket aktif terdaftar pada nomor ini.</p>';
                }

                document.getElementById('quotaContent').classList.remove('hidden');
            } else {
                document.getElementById('quotaPlaceholder').classList.remove('hidden');
                alert(data.message || 'Gagal memuat data kuota.');
            }
        })
        .catch(err => {
            console.error(err);
            document.getElementById('quotaLoading').classList.add('hidden');
            document.getElementById('quotaPlaceholder').classList.remove('hidden');
            alert('Terjadi kesalahan jaringan.');
        });
    }

    // Unsubscribe / Delete active package
    function unsubscribe(packageId, packageName) {
        if (!activeSessionId) return;

        if (!confirm(`Apakah Anda yakin ingin menonaktifkan paket "${packageName}"? Paket ini akan dihapus permanen dari nomor pelanggan.`)) {
            return;
        }

        // Show inline loading or overlay
        document.getElementById('quotaContent').classList.add('hidden');
        document.getElementById('quotaLoading').classList.remove('hidden');

        fetch("{{ route('admin.tools.xl.unsubscribe') }}", {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({ session_id: activeSessionId, package_id: packageId })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert('Paket berhasil dinonaktifkan.');
                checkQuota(activeSessionId); // reload quota details
            } else {
                document.getElementById('quotaLoading').classList.add('hidden');
                document.getElementById('quotaContent').classList.remove('hidden');
                alert(data.message || 'Gagal menonaktifkan paket.');
            }
        })
        .catch(err => {
            console.error(err);
            document.getElementById('quotaLoading').classList.add('hidden');
            document.getElementById('quotaContent').classList.remove('hidden');
            alert('Terjadi kesalahan jaringan.');
        });
    }
</script>
@endsection
