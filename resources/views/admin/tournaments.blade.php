@extends('layouts.app', ['title' => 'Manajemen Turnamen'])

@section('content')
<div class="space-y-6 sm:space-y-8" x-data="{ activeTab: 'pending' }">
    
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-5 gap-4">
        <div>
            <p class="text-sm text-slate-500 dark:text-slate-400">Buat event turnamen baru dan verifikasi pendaftaran tim masuk</p>
        </div>
    </div>

    <!-- Tabs Navigation -->
    <div class="flex border-b border-slate-200 dark:border-slate-800 text-sm font-semibold">
        <button @click="activeTab = 'pending'" :class="activeTab === 'pending' ? 'border-b-2 border-blue-600 text-blue-600 dark:text-blue-400' : 'text-slate-500 dark:text-slate-450 hover:text-slate-700'" class="px-5 py-3 transition duration-150 focus:outline-none flex items-center space-x-2">
            <span>📩 Pendaftaran Masuk</span>
            @if($pendingRegistrations->count() > 0)
                <span class="bg-red-500 text-white text-[10px] px-2 py-0.5 rounded-full font-black animate-pulse">
                    {{ $pendingRegistrations->count() }}
                </span>
            @endif
        </button>
        <button @click="activeTab = 'list'" :class="activeTab === 'list' ? 'border-b-2 border-blue-600 text-blue-600 dark:text-blue-400' : 'text-slate-500 dark:text-slate-450 hover:text-slate-700'" class="px-5 py-3 transition duration-150 focus:outline-none">
            🏆 Daftar Event
        </button>
        <button @click="activeTab = 'create'" :class="activeTab === 'create' ? 'border-b-2 border-blue-600 text-blue-600 dark:text-blue-400' : 'text-slate-500 dark:text-slate-450 hover:text-slate-700'" class="px-5 py-3 transition duration-150 focus:outline-none">
            ✨ Buat Event Baru
        </button>
    </div>

    <!-- Tab Contents -->
    <div class="mt-6">

        <!-- Tab 1: Pending Registrations -->
        <div x-show="activeTab === 'pending'" class="space-y-6">
            @if($pendingRegistrations->isEmpty())
                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800/80 rounded-2xl p-10 text-center shadow-xs">
                    <span class="text-3xl">☕</span>
                    <h4 class="font-extrabold text-slate-800 dark:text-slate-200 text-base mt-2">Tidak Ada Pendaftaran Masuk</h4>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Seluruh pengajuan pendaftaran tim baru telah diproses.</p>
                </div>
            @else
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    @foreach($pendingRegistrations as $reg)
                        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800/80 rounded-3xl p-5 sm:p-6 shadow-xs flex flex-col justify-between space-y-4 relative overflow-hidden group">
                            <div class="absolute left-0 top-0 bottom-0 w-1 bg-amber-500"></div>
                            
                            <!-- Header Info -->
                            <div class="space-y-1">
                                <div class="flex items-center justify-between">
                                    <span class="bg-amber-50 dark:bg-amber-950/30 text-amber-600 dark:text-amber-400 text-[10px] font-bold px-2.5 py-0.5 rounded-full border border-amber-200/50">
                                        {{ $reg->tournament->name }}
                                    </span>
                                    <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">
                                        Biaya: Rp {{ number_format($reg->tournament->registration_fee, 0, ',', '.') }}
                                    </span>
                                </div>
                                <h4 class="font-black text-slate-800 dark:text-slate-100 text-base pt-1">
                                    🛡️ Tim: {{ $reg->team_name }}
                                </h4>
                                <p class="text-xs text-slate-500">
                                    Kapten: <span class="font-bold text-slate-700 dark:text-slate-350">{{ $reg->captain->name }}</span> ({{ $reg->captain->email }})
                                </p>
                            </div>

                            <!-- Players Grid -->
                            <div class="bg-slate-50 dark:bg-slate-950/20 border border-slate-100 dark:border-slate-850 p-3.5 rounded-2xl space-y-2">
                                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest block border-b border-slate-200/30 dark:border-slate-800/30 pb-1">Anggota Skuad (4 Player)</span>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs">
                                    @foreach($reg->participants as $index => $p)
                                        <div class="space-y-0.5 overflow-hidden">
                                            <p class="font-bold text-slate-700 dark:text-slate-300 truncate">
                                                P{{ $index + 1 }}: {{ $p->nickname }}
                                            </p>
                                            <p class="text-[9px] text-slate-400 font-bold uppercase tracking-wide truncate">
                                                ID Game: {{ $p->game_id }} | Web User: {{ $p->user ? $p->user->name : 'N/A' }}
                                            </p>
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            <!-- Actions Form -->
                            <div class="flex items-center space-x-3 pt-3 border-t border-slate-100 dark:border-slate-850">
                                <form action="{{ route('admin.tournaments.approve_registration', $reg->id) }}" method="POST" class="flex-1">
                                    @csrf
                                    <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-2.5 rounded-xl text-xs shadow-md shadow-emerald-600/10 transition duration-150">
                                        ✅ ACC
                                    </button>
                                </form>

                                <!-- Reject Button Trigger (Inline simple reject form to avoid complex alpine modal if unnecessary, or we can use target rejection form) -->
                                <div class="flex-1" x-data="{ showReject: false }">
                                    <button @click="showReject = !showReject" class="w-full bg-red-650 hover:bg-red-700 text-white font-bold py-2.5 rounded-xl text-xs transition duration-150">
                                        ❌ Tolak
                                    </button>
                                    
                                    <!-- Reject Form Slide Down -->
                                    <template x-if="showReject">
                                        <div class="fixed inset-0 bg-slate-900/60 dark:bg-slate-950/80 z-50 flex items-center justify-center p-4">
                                            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-6 w-full max-w-md shadow-2xl space-y-4">
                                                <h5 class="font-extrabold text-slate-800 dark:text-slate-100 text-sm">Alasan Penolakan Skuad</h5>
                                                
                                                <form action="{{ route('admin.tournaments.reject_registration', $reg->id) }}" method="POST" class="space-y-4">
                                                    @csrf
                                                    <textarea name="rejection_reason" placeholder="Contoh: ID Game Free Fire Player 3 tidak valid atau karakter tidak ditemukan." class="w-full h-24 p-3 border border-slate-200 dark:border-slate-800 rounded-xl text-xs focus:border-red-500 focus:outline-none dark:bg-slate-950/30" required></textarea>
                                                    
                                                    <div class="flex items-center justify-end space-x-2 pt-2">
                                                        <button type="button" @click="showReject = false" class="bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-350 px-4 py-2 rounded-xl text-xs font-bold transition">
                                                            Batal
                                                        </button>
                                                        <button type="submit" class="bg-red-650 hover:bg-red-700 text-white px-5 py-2.5 rounded-xl text-xs font-bold transition shadow-md shadow-red-650/15">
                                                            Tolak Skuad & Refund
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <!-- Tab 2: Tournament Lists -->
        <div x-show="activeTab === 'list'" class="space-y-6">
            @if($tournaments->isEmpty())
                <p class="text-xs font-semibold text-slate-400 dark:text-slate-500 italic">Belum ada turnamen yang dibuat.</p>
            @else
                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800/80 rounded-2xl shadow-xs overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs sm:text-sm">
                        <thead>
                            <tr class="bg-slate-50 dark:bg-slate-950/40 text-slate-450 font-bold border-b border-slate-100 dark:border-slate-850">
                                <th class="p-4">Nama Turnamen</th>
                                <th class="p-4">Tipe</th>
                                <th class="p-4 text-center">Biaya Masuk</th>
                                <th class="p-4 text-center">Hadiah</th>
                                <th class="p-4 text-center">Slot Limit</th>
                                <th class="p-4">Status Aktif</th>
                                <th class="p-4 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-850">
                            @foreach($tournaments as $t)
                                <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-950/10">
                                    <td class="p-4 font-bold text-slate-800 dark:text-slate-250">
                                        {{ $t->name }}
                                        <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wider pt-0.5">
                                            Mulai: {{ $t->start_date ? $t->start_date->translatedFormat('d M Y, H:i') : '-' }}
                                        </p>
                                    </td>
                                    <td class="p-4 text-slate-500 uppercase font-semibold text-xs">
                                        {{ $t->type === 'clash_squad' ? 'Clash Squad 4v4' : 'Battle Royale' }}
                                    </td>
                                    <td class="p-4 text-center font-bold text-slate-700 dark:text-slate-300">
                                        {{ $t->registration_fee > 0 ? 'Rp ' . number_format($t->registration_fee, 0, ',', '.') : 'Gratis' }}
                                    </td>
                                    <td class="p-4 text-center font-semibold text-slate-600 dark:text-slate-350">
                                        {{ $t->prize_pool }}
                                    </td>
                                    <td class="p-4 text-center font-bold text-slate-700 dark:text-slate-300">
                                        {{ $t->max_slots ?? 'Tanpa Batas' }}
                                    </td>
                                    <td class="p-4">
                                        <!-- Status Badge -->
                                        @if($t->status === 'draft')
                                            <span class="bg-slate-100 text-slate-600 text-[10px] font-bold px-2 py-0.5 rounded-full border border-slate-200">DRAFT</span>
                                        @elseif($t->status === 'registration')
                                            <span class="bg-emerald-50 dark:bg-emerald-950/20 text-emerald-600 dark:text-emerald-400 text-[10px] font-bold px-2 py-0.5 rounded-full border border-emerald-200/40">REGISTRASI</span>
                                        @elseif($t->status === 'ongoing')
                                            <span class="bg-blue-50 dark:bg-blue-950/20 text-blue-600 dark:text-blue-400 text-[10px] font-bold px-2 py-0.5 rounded-full border border-blue-200/40">BERJALAN</span>
                                        @else
                                            <span class="bg-purple-50 dark:bg-purple-950/20 text-purple-600 dark:text-purple-400 text-[10px] font-bold px-2 py-0.5 rounded-full border border-purple-200/40">SELESAI</span>
                                        @endif
                                    </td>
                                    <td class="p-4 text-right">
                                        <!-- Status Update Dropdown Form -->
                                        <form action="{{ route('admin.tournaments.update_status', $t->id) }}" method="POST" class="inline-flex items-center space-x-1.5">
                                            @csrf
                                            <select name="status" class="px-2 py-1 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-lg text-xs font-semibold focus:outline-none">
                                                <option value="draft" {{ $t->status === 'draft' ? 'selected' : '' }}>Draft</option>
                                                <option value="registration" {{ $t->status === 'registration' ? 'selected' : '' }}>Registrasi</option>
                                                <option value="ongoing" {{ $t->status === 'ongoing' ? 'selected' : '' }}>Berjalan</option>
                                                <option value="completed" {{ $t->status === 'completed' ? 'selected' : '' }}>Selesai</option>
                                            </select>
                                            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-2.5 py-1 rounded-lg text-xs font-bold transition">
                                                Simpan
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <!-- Tab 3: Create New Event -->
        <div x-show="activeTab === 'create'" class="max-w-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800/80 rounded-3xl p-6 sm:p-8 shadow-xs">
            <h4 class="font-extrabold text-slate-800 dark:text-slate-100 text-base border-b border-slate-100 dark:border-slate-850 pb-4 mb-6">Form Pembuatan Turnamen Baru</h4>
            
            <form action="{{ route('admin.tournaments.store') }}" method="POST" class="space-y-5">
                @csrf
                
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <div class="space-y-1.5 sm:col-span-2">
                        <label for="name" class="block text-xs font-bold text-slate-550 dark:text-slate-400 uppercase tracking-wider">Nama Turnamen</label>
                        <input type="text" id="name" name="name" placeholder="Contoh: FF Clash Squad RZK Cup Season 1" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-950/20 border border-slate-200 dark:border-slate-800 focus:border-blue-600 focus:bg-white dark:focus:bg-slate-900 focus:outline-none rounded-xl text-sm transition-all duration-200" required>
                    </div>

                    <div class="space-y-1.5">
                        <label for="type" class="block text-xs font-bold text-slate-550 dark:text-slate-400 uppercase tracking-wider">Format Pertandingan</label>
                        <select id="type" name="type" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-950/20 border border-slate-200 dark:border-slate-800 focus:border-blue-600 focus:bg-white dark:focus:bg-slate-900 focus:outline-none rounded-xl text-sm font-semibold transition-all duration-200" required>
                            <option value="clash_squad">Clash Squad (4v4)</option>
                            <option value="battle_royale">Battle Royale</option>
                        </select>
                    </div>

                    <div class="space-y-1.5">
                        <label for="status" class="block text-xs font-bold text-slate-550 dark:text-slate-400 uppercase tracking-wider">Status Awal</label>
                        <select id="status" name="status" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-950/20 border border-slate-200 dark:border-slate-800 focus:border-blue-600 focus:bg-white dark:focus:bg-slate-900 focus:outline-none rounded-xl text-sm font-semibold transition-all duration-200" required>
                            <option value="draft">Draft (Disembunyikan)</option>
                            <option value="registration">Registrasi (Pendaftaran Dibuka)</option>
                        </select>
                    </div>

                    <div class="space-y-1.5">
                        <label for="registration_fee" class="block text-xs font-bold text-slate-550 dark:text-slate-400 uppercase tracking-wider">Biaya Pendaftaran (Rp)</label>
                        <input type="number" id="registration_fee" name="registration_fee" placeholder="0 jika gratis" min="0" value="0" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-950/20 border border-slate-200 dark:border-slate-800 focus:border-blue-600 focus:bg-white dark:focus:bg-slate-900 focus:outline-none rounded-xl text-sm transition-all duration-200" required>
                    </div>

                    <div class="space-y-1.5">
                        <label for="prize_pool" class="block text-xs font-bold text-slate-550 dark:text-slate-400 uppercase tracking-wider">Total Hadiah (Prize Pool)</label>
                        <input type="text" id="prize_pool" name="prize_pool" placeholder="Contoh: Rp 500.000 + Piala" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-950/20 border border-slate-200 dark:border-slate-800 focus:border-blue-600 focus:bg-white dark:focus:bg-slate-900 focus:outline-none rounded-xl text-sm transition-all duration-200" required>
                    </div>

                    <div class="space-y-1.5">
                        <label for="max_slots" class="block text-xs font-bold text-slate-550 dark:text-slate-400 uppercase tracking-wider">Batasan Slot Tim (Opsional)</label>
                        <select id="max_slots" name="max_slots" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-950/20 border border-slate-200 dark:border-slate-800 focus:border-blue-600 focus:bg-white dark:focus:bg-slate-900 focus:outline-none rounded-xl text-sm font-semibold transition-all duration-200">
                            <option value="">Tanpa Batasan</option>
                            <option value="8">8 Tim</option>
                            <option value="16">16 Tim</option>
                            <option value="32">32 Tim</option>
                        </select>
                    </div>

                    <div class="space-y-1.5">
                        <label for="start_date" class="block text-xs font-bold text-slate-550 dark:text-slate-400 uppercase tracking-wider">Jadwal Tanding</label>
                        <input type="datetime-local" id="start_date" name="start_date" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-950/20 border border-slate-200 dark:border-slate-800 focus:border-blue-600 focus:bg-white dark:focus:bg-slate-900 focus:outline-none rounded-xl text-sm transition-all duration-200">
                    </div>

                    <div class="space-y-1.5 sm:col-span-2">
                        <label for="description" class="block text-xs font-bold text-slate-550 dark:text-slate-400 uppercase tracking-wider">Deskripsi & Peraturan Turnamen</label>
                        <textarea id="description" name="description" placeholder="Jelaskan detail slot, tata cara mendaftar, peraturan chat grup WA/Telegram kapten, dsb." class="w-full h-32 px-4 py-3 bg-slate-50 dark:bg-slate-950/20 border border-slate-200 dark:border-slate-800 focus:border-blue-600 focus:bg-white dark:focus:bg-slate-900 focus:outline-none rounded-xl text-sm transition-all duration-200"></textarea>
                    </div>
                </div>

                <div class="flex items-center justify-end pt-3 border-t border-slate-100 dark:border-slate-850">
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-extrabold px-6 py-3 rounded-xl text-sm transition duration-150 hover:scale-[1.02] active:scale-95 shadow-md shadow-blue-600/10">
                        ✨ Buat Turnamen Sekarang
                    </button>
                </div>
            </form>
        </div>

    </div>
</div>
@endsection
