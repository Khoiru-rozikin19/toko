@extends('layouts.app')

@section('content')
<div class="space-y-8 max-w-6xl mx-auto">
    <!-- Breadcrumb -->
    <nav class="flex text-xs sm:text-sm font-semibold text-slate-500 dark:text-slate-400" aria-label="Breadcrumb">
        <ol class="inline-flex items-center space-x-1.5 md:space-x-2">
            <li class="inline-flex items-center">
                <a href="{{ route('tournaments.index') }}" class="hover:text-orange-500 transition duration-150">Turnamen Event</a>
            </li>
            <li class="flex items-center space-x-1.5 md:space-x-2">
                <svg class="w-4 h-4 text-slate-300 dark:text-slate-700" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path></svg>
                <span class="text-slate-700 dark:text-slate-300 font-bold truncate max-w-[200px]">{{ $tournament->name }}</span>
            </li>
        </ol>
    </nav>

    <!-- Main Detail Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <!-- Left Side: Detail Event, Brackets, Rules -->
        <div class="lg:col-span-2 space-y-8">
            
            <!-- Tournament General Card -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800/80 rounded-3xl p-6 sm:p-8 shadow-xs relative overflow-hidden">
                <div class="absolute inset-x-0 top-0 h-1.5 bg-gradient-to-r from-orange-500 to-red-600"></div>
                
                <div class="space-y-4">
                    <div class="flex items-center space-x-2">
                        @if($tournament->status === 'registration')
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-emerald-50 dark:bg-emerald-950/30 text-emerald-600 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-900/50">
                                Pendaftaran Dibuka
                            </span>
                        @elseif($tournament->status === 'ongoing')
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-blue-50 dark:bg-blue-950/30 text-blue-600 dark:text-blue-400 border border-blue-200 dark:border-blue-900/50">
                                Turnamen Sedang Berjalan
                            </span>
                        @elseif($tournament->status === 'completed')
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400">
                                Turnamen Selesai
                            </span>
                        @else
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-slate-150 dark:bg-slate-850 text-slate-500 dark:text-slate-400 border border-slate-200 dark:border-slate-800">
                                Draft
                            </span>
                        @endif
                        <span class="text-xs font-semibold text-slate-400 dark:text-slate-500 uppercase tracking-wider">
                            {{ $tournament->type === 'clash_squad' ? 'Clash Squad Free Fire' : 'Battle Royale Free Fire' }}
                        </span>
                    </div>

                    <h3 class="text-2xl sm:text-3xl font-extrabold text-slate-800 dark:text-slate-100 tracking-tight leading-tight">
                        {{ $tournament->name }}
                    </h3>

                    @if($tournament->description)
                        <div class="text-sm text-slate-600 dark:text-slate-300 space-y-2 leading-relaxed bg-slate-50 dark:bg-slate-950/20 border border-slate-100 dark:border-slate-800/50 p-4 rounded-2xl">
                            {!! nl2br(e($tournament->description)) !!}
                        </div>
                    @endif

                    <!-- Details Grid -->
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 pt-4 border-t border-slate-100 dark:border-slate-800/80 text-xs sm:text-sm">
                        <div class="space-y-0.5">
                            <span class="text-slate-400 dark:text-slate-500 font-bold uppercase text-[9px] tracking-wider">Biaya Registrasi</span>
                            <p class="font-extrabold text-slate-800 dark:text-slate-200">
                                {{ $tournament->registration_fee > 0 ? 'Rp ' . number_format($tournament->registration_fee, 0, ',', '.') : 'GRATIS' }}
                            </p>
                        </div>
                        <div class="space-y-0.5">
                            <span class="text-slate-400 dark:text-slate-500 font-bold uppercase text-[9px] tracking-wider">Prize Pool</span>
                            <p class="font-extrabold text-orange-500">{{ $tournament->prize_pool }}</p>
                        </div>
                        <div class="space-y-0.5">
                            <span class="text-slate-400 dark:text-slate-500 font-bold uppercase text-[9px] tracking-wider">Jadwal Tanding</span>
                            <p class="font-extrabold text-slate-800 dark:text-slate-200">
                                {{ $tournament->start_date ? $tournament->start_date->translatedFormat('d M Y, H:i') . ' WIB' : '-' }}
                            </p>
                        </div>
                        <div class="space-y-0.5">
                            <span class="text-slate-400 dark:text-slate-500 font-bold uppercase text-[9px] tracking-wider">Slot Terisi</span>
                            <p class="font-extrabold text-slate-800 dark:text-slate-200">
                                {{ $approvedTeams->count() }} / {{ $tournament->max_slots ?? '∞' }} Tim
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bracket / Standings Section -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800/80 rounded-3xl p-6 sm:p-8 shadow-xs space-y-4">
                <h4 class="text-lg font-bold text-slate-800 dark:text-slate-100 flex items-center space-x-2">
                    <span>📊 Bagan Pertandingan</span>
                </h4>
                
                @if($tournament->type === 'clash_squad')
                    <div class="p-6 bg-slate-50 dark:bg-slate-950/20 rounded-2xl text-center border border-slate-150 dark:border-slate-800/60">
                        <span class="text-2xl">🌱</span>
                        <h5 class="font-bold text-slate-800 dark:text-slate-200 text-sm mt-2">Bagan Sedang Disusun</h5>
                        <p class="text-xs text-slate-400 dark:text-slate-500 mt-1 max-w-sm mx-auto">
                            Bagan pertandingan Clash Squad otomatis dibentuk setelah pendaftaran ditutup oleh Admin.
                        </p>
                    </div>
                @else
                    <div class="p-6 bg-slate-50 dark:bg-slate-950/20 rounded-2xl text-center border border-slate-150 dark:border-slate-800/60">
                        <span class="text-2xl">🏆</span>
                        <h5 class="font-bold text-slate-800 dark:text-slate-200 text-sm mt-2">Format Battle Royale</h5>
                        <p class="text-xs text-slate-400 dark:text-slate-500 mt-1 max-w-sm mx-auto">
                            Leaderboard poin Battle Royale akan diperbarui secara langsung oleh Admin setiap kali pertandingan selesai!
                        </p>
                    </div>
                @endif
            </div>

            <!-- Rules / Peraturan Section -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800/80 rounded-3xl p-6 sm:p-8 shadow-xs space-y-4">
                <h4 class="text-lg font-bold text-slate-800 dark:text-slate-100 flex items-center space-x-2">
                    <span>📜 Syarat & Ketentuan Turnamen</span>
                </h4>
                
                <ul class="space-y-3.5 text-xs sm:text-sm text-slate-600 dark:text-slate-300 font-medium">
                    <li class="flex items-start space-x-2.5">
                        <span class="bg-orange-500 text-white rounded-full w-5 h-5 flex items-center justify-center font-bold text-xs flex-shrink-0 mt-0.5">1</span>
                        <span>Seluruh anggota tim (4 pemain) **wajib memiliki akun terdaftar dan aktif** di website ini. Akun yang belum terdaftar tidak akan bisa ditambahkan ke dalam daftar tim.</span>
                    </li>
                    <li class="flex items-start space-x-2.5">
                        <span class="bg-orange-500 text-white rounded-full w-5 h-5 flex items-center justify-center font-bold text-xs flex-shrink-0 mt-0.5">2</span>
                        <span>Biaya pendaftaran akan langsung dipotong dari **Saldo Akun Kapten** saat mendaftar. Kapten wajib mengisi saldo terlebih dahulu sebelum mendaftar.</span>
                    </li>
                    <li class="flex items-start space-x-2.5">
                        <span class="bg-orange-500 text-white rounded-full w-5 h-5 flex items-center justify-center font-bold text-xs flex-shrink-0 mt-0.5">3</span>
                        <span>Setiap tim harus diisi tepat 4 orang pemain yang valid (ID Free Fire dan Nickname harus persis seperti di game).</span>
                    </li>
                    <li class="flex items-start space-x-2.5">
                        <span class="bg-orange-500 text-white rounded-full w-5 h-5 flex items-center justify-center font-bold text-xs flex-shrink-0 mt-0.5">4</span>
                        <span>Apabila pendaftaran Anda ditolak oleh Admin (karena data ID tidak valid/alasan lainnya), biaya pendaftaran akan **dikembalikan 100% (Refund Otomatis)** ke saldo akun Kapten.</span>
                    </li>
                    <li class="flex items-start space-x-2.5">
                        <span class="bg-orange-500 text-white rounded-full w-5 h-5 flex items-center justify-center font-bold text-xs flex-shrink-0 mt-0.5">5</span>
                        <span>Setiap akun hanya diperbolehkan bergabung dalam 1 tim pada turnamen yang sama. Dilarang melakukan kecurangan (*multi-account*).</span>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Right Side: Action Box & Registered Teams List -->
        <div class="space-y-8">
            
            <!-- Registration CTA Card -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800/80 rounded-3xl p-6 shadow-sm relative overflow-hidden">
                <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-orange-500 to-red-600"></div>
                
                <h4 class="font-extrabold text-slate-800 dark:text-slate-100 text-base mb-3 flex items-center space-x-2">
                    <span>📝 Pendaftaran Tim</span>
                </h4>
                
                @if($tournament->status === 'registration')
                    <div class="space-y-4">
                        <p class="text-xs text-slate-500 dark:text-slate-400 font-medium">
                            Pendaftaran sedang dibuka! Amankan slot tim Anda sekarang dengan mendaftarkan squad terbaikmu.
                        </p>
                        
                        @if($tournament->max_slots && $approvedTeams->count() >= $tournament->max_slots)
                            <div class="bg-red-50 dark:bg-red-950/20 text-red-600 dark:text-red-400 border border-red-200 dark:border-red-900/50 p-4 rounded-2xl text-center text-xs font-bold">
                                🚫 Slot Tim Sudah Penuh!
                            </div>
                        @else
                            @if(Auth::check())
                                <!-- Link or trigger registration form (which we will build in Phase 3) -->
                                <a href="#" class="w-full bg-gradient-to-r from-orange-500 to-red-600 hover:from-orange-600 hover:to-red-700 text-white font-extrabold py-3.5 rounded-2xl shadow-lg shadow-orange-500/20 flex items-center justify-center space-x-2 transition duration-200 hover:scale-[1.02] text-sm">
                                    <span>🏆 Daftar Skuad Sekarang</span>
                                </a>
                                <p class="text-[10px] text-center text-slate-400 dark:text-slate-500 font-bold uppercase tracking-wider">
                                    Membayar Menggunakan Saldo Akun Anda
                                </p>
                            @else
                                <a href="{{ route('login') }}" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold py-3.5 rounded-2xl shadow-lg shadow-emerald-500/20 flex items-center justify-center space-x-2 transition duration-200 hover:scale-[1.02] text-sm">
                                    <span>🔑 Login untuk Mendaftar</span>
                                </a>
                                <p class="text-[10px] text-center text-slate-400 dark:text-slate-500 font-bold uppercase tracking-wider">
                                    Wajib login untuk berpartisipasi
                                </p>
                            @endif
                        @endif
                    </div>
                @else
                    <div class="bg-slate-50 dark:bg-slate-950/20 border border-slate-150 dark:border-slate-800/60 p-4 rounded-2xl text-center">
                        <span class="text-xl">🔒</span>
                        <h5 class="font-bold text-slate-800 dark:text-slate-200 text-xs mt-1">Pendaftaran Ditutup</h5>
                        <p class="text-[10px] text-slate-400 dark:text-slate-500 mt-0.5">Turnamen sedang berjalan atau telah selesai.</p>
                    </div>
                @endif
            </div>

            <!-- Registered Teams List -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800/80 rounded-3xl p-6 shadow-xs space-y-4">
                <h4 class="font-extrabold text-slate-800 dark:text-slate-100 text-base flex items-center justify-between">
                    <span>👥 Tim Terverifikasi</span>
                    <span class="bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-350 text-[10px] px-2 py-0.5 rounded-full font-bold">
                        {{ $approvedTeams->count() }} Tim
                    </span>
                </h4>
                
                @if($approvedTeams->isEmpty())
                    <p class="text-xs text-slate-400 dark:text-slate-500 font-medium italic">Belum ada tim yang disetujui (ACC) oleh Admin.</p>
                @else
                    <div class="space-y-4 max-h-[350px] overflow-y-auto pr-1">
                        @foreach($approvedTeams as $team)
                            <div class="bg-slate-50 dark:bg-slate-950/20 border border-slate-150 dark:border-slate-800/60 p-3.5 rounded-2xl space-y-2">
                                <div class="flex items-center justify-between">
                                    <h5 class="font-extrabold text-slate-800 dark:text-slate-200 text-sm">
                                        🛡️ {{ $team->team_name }}
                                    </h5>
                                    <span class="text-[10px] font-bold text-slate-400">
                                        Kapten: {{ $team->captain->name }}
                                    </span>
                                </div>
                                <div class="grid grid-cols-2 gap-2 text-[10px] sm:text-xs font-semibold text-slate-500 dark:text-slate-400 border-t border-slate-200/50 dark:border-slate-800/50 pt-2">
                                    @foreach($team->participants as $p)
                                        <div class="truncate">
                                            • {{ $p->nickname }}
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

    </div>
</div>
@endsection
