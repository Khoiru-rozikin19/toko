@extends('layouts.app', ['title' => 'VPN Panel - Manajemen Server'])

@section('content')
<div class="space-y-6 sm:space-y-8">
    
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-5">
        <div>
            <h2 class="text-2xl sm:text-3xl font-extrabold text-slate-800 dark:text-slate-100 tracking-tight">VPN Panel Server</h2>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Kelola koneksi VPS Anda untuk otomatisasi pembuatan akun VPN</p>
        </div>
        <button onclick="toggleCreateModal(true)" class="mt-4 sm:mt-0 flex items-center space-x-2 px-5 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-2xl text-sm font-bold shadow-lg shadow-blue-500/20 active:scale-95 transition-all duration-200">
            <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"></path></svg>
            <span>Tambah Server</span>
        </button>
    </div>

    <!-- Alert placeholder -->
    <div id="statusAlertContainer" class="hidden mb-6 p-4 rounded-2xl flex items-start space-x-3 transition-all duration-300">
        <div id="statusAlertIconContainer" class="flex-shrink-0 mt-0.5"></div>
        <div class="flex-1 text-sm font-medium" id="statusAlertMessage"></div>
        <button onclick="hideStatusAlert()" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
            <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
        </button>
    </div>

    <!-- Servers Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @forelse($servers as $server)
            <div class="bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 rounded-3xl p-6 shadow-sm hover:shadow-xl hover:-translate-y-1 transition-all duration-300 flex flex-col justify-between" id="server-card-{{ $server->id }}">
                <div>
                    <!-- Status & Icon -->
                    <div class="flex items-center justify-between mb-4">
                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400">
                            <span class="w-1.5 h-1.5 rounded-full bg-slate-400 mr-1.5"></span>
                            <span>Status: Belum Dites</span>
                        </span>
                        <div class="bg-blue-550/10 p-2.5 rounded-xl text-blue-600 dark:text-blue-400">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5.25 14.25h13.5m-13.5 0a3 3 0 01-3-3m3 3a3 3 0 100 6h13.5a3 3 0 100-6m-13.5-3a3 3 0 013-3h13.5a3 3 0 013 3m-16.5 0a3 3 0 013-3h13.5a3 3 0 013 3m-19.5 0a3 3 0 013-3h13.5a3 3 0 013 3M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75"></path></svg>
                        </div>
                    </div>

                    <!-- Server Details -->
                    <h3 class="text-lg font-bold text-slate-800 dark:text-slate-100 tracking-tight leading-tight">{{ $server->name }}</h3>
                    <div class="mt-3 space-y-2 text-xs text-slate-500 dark:text-slate-400 font-mono">
                        <div class="flex items-center space-x-1.5">
                            <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path></svg>
                            <span>{{ $server->ip_address }}:{{ $server->ssh_port }}</span>
                        </div>
                        <div class="flex items-center space-x-1.5">
                            <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                            <span>Username: {{ $server->ssh_username }}</span>
                        </div>
                        <div class="flex items-center space-x-1.5">
                            <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m-5-4v1a3 3 0 003 3h4m0 0l-4 4m4-4H3m12 0v7a2 2 0 01-2 2H3a2 2 0 01-2-2v-7a2 2 0 012-2h10a2 2 0 012 2z"></path></svg>
                            <span>Auth: {{ !empty($server->ssh_private_key) ? 'Private Key SSH' : 'Password Root' }}</span>
                        </div>
                    </div>
                </div>

                <!-- Footer Card Actions -->
                <div class="flex items-center justify-between mt-6 pt-4 border-t border-slate-100 dark:border-slate-800">
                    <button onclick="testConnection({{ json_encode($server) }}, this)" class="inline-flex items-center space-x-1.5 px-3.5 py-2 bg-emerald-50 dark:bg-emerald-950/20 hover:bg-emerald-100 dark:hover:bg-emerald-950/40 text-emerald-700 dark:text-emerald-400 rounded-xl text-xs font-bold transition-all duration-200">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                        <span>Test Koneksi</span>
                    </button>
                    
                    <div class="flex space-x-1">
                        <button onclick="openEditModal({{ json_encode($server) }})" class="p-2 bg-slate-50 dark:bg-slate-850 hover:bg-blue-50 dark:hover:bg-blue-950/30 text-slate-500 hover:text-blue-600 dark:text-slate-400 dark:hover:text-blue-400 rounded-xl transition-all duration-200" title="Ubah Server">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                        </button>
                        
                        <form action="{{ route('admin.vpn_panel.delete', $server->id) }}" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin menghapus server VPS ini? Hubungan dengan produk akan dilepas.')" class="inline">
                            @csrf
                            <button type="submit" class="p-2 bg-slate-50 dark:bg-slate-850 hover:bg-rose-50 dark:hover:bg-rose-950/30 text-slate-500 hover:text-rose-600 dark:text-slate-400 dark:hover:text-rose-400 rounded-xl transition-all duration-200" title="Hapus Server">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-span-full py-16 text-center bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 rounded-3xl p-8 shadow-sm">
                <svg class="w-16 h-16 text-slate-300 dark:text-slate-700 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5.25 14.25h13.5m-13.5 0a3 3 0 01-3-3m3 3a3 3 0 100 6h13.5a3 3 0 100-6m-13.5-3a3 3 0 013-3h13.5a3 3 0 013 3m-16.5 0a3 3 0 013-3h13.5a3 3 0 013 3m-19.5 0a3 3 0 013-3h13.5a3 3 0 013 3"></path></svg>
                <h3 class="text-lg font-bold text-slate-700 dark:text-slate-300">Belum ada VPS Terhubung</h3>
                <p class="text-sm text-slate-400 dark:text-slate-500 mt-1 max-w-sm mx-auto">Tambahkan pengaturan server VPS Anda untuk mengotomatiskan pembuatan akun VPN SSH/Vmess/Vless/Trojan.</p>
                <button onclick="toggleCreateModal(true)" class="mt-6 inline-flex items-center space-x-2 px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold shadow-md shadow-blue-500/10 active:scale-95 transition-all duration-200">
                    <span>Mulai Tambah Server</span>
                </button>
            </div>
        @endforelse
    </div>
</div>

<!-- CREATE SERVER MODAL -->
<div id="createServerModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm hidden transition-all duration-300">
    <div class="bg-white dark:bg-slate-950 rounded-3xl w-full max-w-lg border border-slate-100 dark:border-slate-800 p-5 sm:p-8 shadow-2xl relative max-h-[calc(100vh-2rem)] overflow-y-auto">
        <button onclick="toggleCreateModal(false)" class="absolute top-5 right-5 p-2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-900 transition-all duration-200">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
        </button>

        <h3 class="text-2xl font-extrabold text-slate-800 dark:text-slate-100 tracking-tight mb-6">Tambah Server VPS Baru</h3>

        <form action="{{ route('admin.vpn_panel.store') }}" method="POST" class="space-y-4">
            @csrf
            <div>
                <label for="create_name" class="block text-xs font-bold text-slate-500 uppercase mb-2">Nama Server</label>
                <input type="text" id="create_name" name="name" required placeholder="Contoh: VPS SG-Premium" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 focus:border-blue-500 focus:bg-white focus:outline-none rounded-2xl text-sm font-medium text-slate-800 dark:text-slate-100 transition-all duration-200">
            </div>

            <div class="grid grid-cols-3 gap-4">
                <div class="col-span-2">
                    <label for="create_ip" class="block text-xs font-bold text-slate-500 uppercase mb-2">IP Address / Hostname</label>
                    <input type="text" id="create_ip" name="ip_address" required placeholder="Contoh: 128.199.123.45" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 focus:border-blue-500 focus:bg-white focus:outline-none rounded-2xl text-sm font-medium text-slate-800 dark:text-slate-100 transition-all duration-200">
                </div>
                <div>
                    <label for="create_port" class="block text-xs font-bold text-slate-500 uppercase mb-2">Port SSH</label>
                    <input type="number" id="create_port" name="ssh_port" required value="22" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 focus:border-blue-500 focus:bg-white focus:outline-none rounded-2xl text-sm font-medium text-slate-800 dark:text-slate-100 transition-all duration-200">
                </div>
            </div>

            <div class="grid grid-cols-3 gap-4">
                <div class="col-span-1">
                    <label for="create_username" class="block text-xs font-bold text-slate-500 uppercase mb-2">SSH Username</label>
                    <input type="text" id="create_username" name="ssh_username" required value="root" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 focus:border-blue-500 focus:bg-white focus:outline-none rounded-2xl text-sm font-medium text-slate-800 dark:text-slate-100 transition-all duration-200">
                </div>
                <div class="col-span-2">
                    <label for="create_password" class="block text-xs font-bold text-slate-500 uppercase mb-2">SSH Password / Key Passphrase</label>
                    <input type="password" id="create_password" name="ssh_password" placeholder="Password akun root Anda" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 focus:border-blue-500 focus:bg-white focus:outline-none rounded-2xl text-sm font-medium text-slate-800 dark:text-slate-100 transition-all duration-200">
                </div>
            </div>

            <div>
                <label for="create_private_key" class="block text-xs font-bold text-slate-500 uppercase mb-2">SSH Private Key (Opsional - Paste isi file id_rsa)</label>
                <textarea id="create_private_key" name="ssh_private_key" rows="6" placeholder="-----BEGIN OPENSSH PRIVATE KEY-----\n..." class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 focus:border-blue-500 focus:bg-white focus:outline-none rounded-2xl text-xs font-mono text-slate-800 dark:text-slate-100 transition-all duration-200"></textarea>
                <p class="text-[10px] text-slate-400 dark:text-slate-500 mt-1">Gunakan private key jika Anda menonaktifkan autentikasi password root VPS.</p>
            </div>

            <div class="flex justify-between items-center pt-4">
                <button type="button" onclick="testConnectionFromInputs('createServerModal', this)" class="inline-flex items-center space-x-1.5 px-4 py-3 border border-emerald-200 hover:bg-emerald-50 dark:border-emerald-900/50 dark:hover:bg-emerald-950/20 text-emerald-600 dark:text-emerald-400 rounded-2xl text-xs font-bold transition-all duration-200">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                    <span>Test Hubungan</span>
                </button>
                
                <div class="flex space-x-2">
                    <button type="button" onclick="toggleCreateModal(false)" class="px-5 py-3 bg-slate-100 hover:bg-slate-200 dark:bg-slate-850 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-300 rounded-2xl text-sm font-semibold transition-all duration-200">
                        Batal
                    </button>
                    <button type="submit" class="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-2xl text-sm font-bold shadow-lg shadow-blue-500/20 transition-all duration-200">
                        Simpan Server
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- EDIT SERVER MODAL -->
<div id="editServerModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm hidden transition-all duration-300">
    <div class="bg-white dark:bg-slate-950 rounded-3xl w-full max-w-lg border border-slate-100 dark:border-slate-800 p-5 sm:p-8 shadow-2xl relative max-h-[calc(100vh-2rem)] overflow-y-auto">
        <button onclick="toggleEditModal(false)" class="absolute top-5 right-5 p-2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-900 transition-all duration-200">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
        </button>

        <h3 class="text-2xl font-extrabold text-slate-800 dark:text-slate-100 tracking-tight mb-6">Ubah Pengaturan Server VPS</h3>

        <form id="editServerForm" action="" method="POST" class="space-y-4">
            @csrf
            <div>
                <label for="edit_name" class="block text-xs font-bold text-slate-500 uppercase mb-2">Nama Server</label>
                <input type="text" id="edit_name" name="name" required class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 focus:border-blue-500 focus:bg-white focus:outline-none rounded-2xl text-sm font-medium text-slate-800 dark:text-slate-100 transition-all duration-200">
            </div>

            <div class="grid grid-cols-3 gap-4">
                <div class="col-span-2">
                    <label for="edit_ip" class="block text-xs font-bold text-slate-500 uppercase mb-2">IP Address / Hostname</label>
                    <input type="text" id="edit_ip" name="ip_address" required class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 focus:border-blue-500 focus:bg-white focus:outline-none rounded-2xl text-sm font-medium text-slate-800 dark:text-slate-100 transition-all duration-200">
                </div>
                <div>
                    <label for="edit_port" class="block text-xs font-bold text-slate-500 uppercase mb-2">Port SSH</label>
                    <input type="number" id="edit_port" name="ssh_port" required class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 focus:border-blue-500 focus:bg-white focus:outline-none rounded-2xl text-sm font-medium text-slate-800 dark:text-slate-100 transition-all duration-200">
                </div>
            </div>

            <div class="grid grid-cols-3 gap-4">
                <div class="col-span-1">
                    <label for="edit_username" class="block text-xs font-bold text-slate-500 uppercase mb-2">SSH Username</label>
                    <input type="text" id="edit_username" name="ssh_username" required class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 focus:border-blue-500 focus:bg-white focus:outline-none rounded-2xl text-sm font-medium text-slate-800 dark:text-slate-100 transition-all duration-200">
                </div>
                <div class="col-span-2">
                    <label for="edit_password" class="block text-xs font-bold text-slate-500 uppercase mb-2">SSH Password / Key Passphrase</label>
                    <input type="password" id="edit_password" name="ssh_password" placeholder="Biarkan kosong jika tidak diubah" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 focus:border-blue-500 focus:bg-white focus:outline-none rounded-2xl text-sm font-medium text-slate-800 dark:text-slate-100 transition-all duration-200">
                </div>
            </div>

            <div>
                <label for="edit_private_key" class="block text-xs font-bold text-slate-500 uppercase mb-2">SSH Private Key (Paste isi file id_rsa)</label>
                <textarea id="edit_private_key" name="ssh_private_key" rows="6" placeholder="-----BEGIN OPENSSH PRIVATE KEY-----\n..." class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 focus:border-blue-500 focus:bg-white focus:outline-none rounded-2xl text-xs font-mono text-slate-800 dark:text-slate-100 transition-all duration-200"></textarea>
            </div>

            <div class="flex justify-between items-center pt-4">
                <button type="button" onclick="testConnectionFromInputs('editServerModal', this)" class="inline-flex items-center space-x-1.5 px-4 py-3 border border-emerald-200 hover:bg-emerald-50 dark:border-emerald-900/50 dark:hover:bg-emerald-950/20 text-emerald-600 dark:text-emerald-400 rounded-2xl text-xs font-bold transition-all duration-200">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                    <span>Test Hubungan</span>
                </button>
                
                <div class="flex space-x-2">
                    <button type="button" onclick="toggleEditModal(false)" class="px-5 py-3 bg-slate-100 hover:bg-slate-200 dark:bg-slate-850 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-300 rounded-2xl text-sm font-semibold transition-all duration-200">
                        Batal
                    </button>
                    <button type="submit" class="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-2xl text-sm font-bold shadow-lg shadow-blue-500/20 transition-all duration-200">
                        Simpan Perubahan
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
    function toggleCreateModal(show) {
        const modal = document.getElementById('createServerModal');
        if (show) {
            modal.classList.remove('hidden');
        } else {
            modal.classList.add('hidden');
        }
    }

    function toggleEditModal(show) {
        const modal = document.getElementById('editServerModal');
        if (show) {
            modal.classList.remove('hidden');
        } else {
            modal.classList.add('hidden');
        }
    }

    function openEditModal(server) {
        document.getElementById('edit_name').value = server.name;
        document.getElementById('edit_ip').value = server.ip_address;
        document.getElementById('edit_port').value = server.ssh_port;
        document.getElementById('edit_username').value = server.ssh_username;
        document.getElementById('edit_password').value = server.ssh_password || '';
        document.getElementById('edit_private_key').value = server.ssh_private_key || '';
        
        document.getElementById('editServerForm').action = `/admin/vpn-panel/${server.id}/update`;
        toggleEditModal(true);
    }

    function hideStatusAlert() {
        const alert = document.getElementById('statusAlertContainer');
        alert.classList.add('hidden');
    }

    function showStatusAlert(isSuccess, message) {
        const alert = document.getElementById('statusAlertContainer');
        const iconContainer = document.getElementById('statusAlertIconContainer');
        const messageContainer = document.getElementById('statusAlertMessage');

        alert.classList.remove('hidden', 'bg-emerald-50', 'text-emerald-800', 'border-emerald-200', 'dark:bg-emerald-950/20', 'dark:text-emerald-400', 'dark:border-emerald-900/50', 'bg-rose-50', 'text-rose-800', 'border-rose-200', 'dark:bg-rose-950/20', 'dark:text-rose-400', 'dark:border-rose-900/50');

        if (isSuccess) {
            alert.classList.add('bg-emerald-50', 'text-emerald-800', 'border', 'border-emerald-200', 'dark:bg-emerald-950/20', 'dark:text-emerald-400', 'dark:border-emerald-900/50');
            iconContainer.innerHTML = `<svg class="w-5 h-5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>`;
        } else {
            alert.classList.add('bg-rose-50', 'text-rose-800', 'border', 'border-rose-200', 'dark:bg-rose-950/20', 'dark:text-rose-400', 'dark:border-rose-900/50');
            iconContainer.innerHTML = `<svg class="w-5 h-5 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>`;
        }

        messageContainer.innerText = message;
        alert.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    // Test connection from inputs inside Modals
    function testConnectionFromInputs(modalId, btn) {
        const modal = document.getElementById(modalId);
        const prefix = modalId === 'createServerModal' ? 'create' : 'edit';
        
        const ip = modal.querySelector(`#${prefix}_ip`).value.trim();
        const port = modal.querySelector(`#${prefix}_port`).value;
        const username = modal.querySelector(`#${prefix}_username`).value.trim();
        const password = modal.querySelector(`#${prefix}_password`).value;
        const privateKey = modal.querySelector(`#${prefix}_private_key`).value;

        if (!ip || !port || !username) {
            alert('IP Address, Port SSH, dan Username wajib diisi untuk melakukan test koneksi!');
            return;
        }

        const originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = `<svg class="animate-spin -ml-1 mr-1.5 h-3.5 w-3.5 text-emerald-600 inline" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Connecting...`;

        fetch("{{ route('admin.vpn_panel.test') }}", {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({
                ip_address: ip,
                ssh_port: port,
                ssh_username: username,
                ssh_password: password,
                ssh_private_key: privateKey
            })
        })
        .then(res => res.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = originalText;
            if (data.success) {
                showStatusAlert(true, data.message);
                if (modalId === 'createServerModal') toggleCreateModal(false);
                else toggleEditModal(false);
            } else {
                showStatusAlert(false, 'Gagal Hubungan: ' + data.message);
            }
        })
        .catch(err => {
            btn.disabled = false;
            btn.innerHTML = originalText;
            console.error(err);
            showStatusAlert(false, 'Terjadi kesalahan koneksi server.');
        });
    }

    // Test connection of an already saved server
    function testConnection(server, btn) {
        const originalHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = `<svg class="animate-spin -ml-1 mr-1.5 h-3.5 w-3.5 text-emerald-700 inline" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Menghubungkan...`;

        const card = document.getElementById(`server-card-${server.id}`);
        const badge = card.querySelector('.rounded-full');

        fetch("{{ route('admin.vpn_panel.test') }}", {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({
                ip_address: server.ip_address,
                ssh_port: server.ssh_port,
                ssh_username: server.ssh_username,
                ssh_password: server.ssh_password,
                ssh_private_key: server.ssh_private_key
            })
        })
        .then(res => res.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = originalHtml;
            if (data.success) {
                showStatusAlert(true, `Koneksi SSH ke ${server.name} (${server.ip_address}) sukses!`);
                badge.className = 'inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-50 dark:bg-emerald-950/30 text-emerald-700 dark:text-emerald-400';
                badge.innerHTML = `<span class="w-1.5 h-1.5 rounded-full bg-emerald-550 mr-1.5"></span><span>Status: Sukses Terhubung</span>`;
            } else {
                showStatusAlert(false, `Gagal terhubung ke ${server.name} (${server.ip_address}): ` + data.message);
                badge.className = 'inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-rose-50 dark:bg-rose-950/30 text-rose-700 dark:text-rose-400';
                badge.innerHTML = `<span class="w-1.5 h-1.5 rounded-full bg-rose-550 mr-1.5"></span><span>Status: Gangguan Koneksi</span>`;
            }
        })
        .catch(err => {
            btn.disabled = false;
            btn.innerHTML = originalHtml;
            console.error(err);
            showStatusAlert(false, 'Terjadi kesalahan koneksi server.');
        });
    }
</script>
@endsection
