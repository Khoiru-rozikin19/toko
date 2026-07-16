@php
    $userRegistration = null;
    $hasPendingOrApproved = false;
    $isCaptain = false;

    if (Auth::check()) {
        // Fetch active registration for this user (either captain or participant)
        $userRegistration = \App\Models\TournamentRegistration::where('tournament_id', $tournament->id)
            ->where(function($q) {
                $q->where('captain_id', Auth::id())
                  ->orWhereHas('participants', function($pq) {
                      $pq->where('user_id', Auth::id());
                  });
            })
            ->orderBy('created_at', 'desc')
            ->first();

        if ($userRegistration) {
            $isCaptain = $userRegistration->captain_id === Auth::id();
            if (in_array($userRegistration->status, ['pending', 'approved'])) {
                $hasPendingOrApproved = true;
            }
        }
    }

    // Hitung jumlah anggota tim yang dibutuhkan (selain kapten)
    $memberCount = 3; // Default Clash Squad / Squad BR
    if ($tournament->type === 'battle_royale') {
        if ($tournament->team_mode === 'solo') {
            $memberCount = 0;
        } elseif ($tournament->team_mode === 'duo') {
            $memberCount = 1;
        }
    }
@endphp

@extends('layouts.app')

@section('content')
<div class="space-y-6 max-w-6xl mx-auto">
    <!-- Breadcrumb -->
    <nav class="flex text-xs font-semibold text-slate-450 dark:text-slate-500" aria-label="Breadcrumb">
        <ol class="inline-flex items-center space-x-1.5">
            <li>
                <a href="{{ route('tournaments.index') }}" class="hover:text-orange-500 transition duration-150 flex items-center space-x-1">
                    <span>🏆</span>
                    <span>Turnamen</span>
                </a>
            </li>
            <li class="flex items-center space-x-1">
                <svg class="w-3.5 h-3.5 text-slate-300 dark:text-slate-800" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path></svg>
                <span class="text-slate-700 dark:text-slate-350 font-bold truncate max-w-[200px]">{{ $tournament->name }}</span>
            </li>
        </ol>
    </nav>

    <!-- Error/Validation Alert -->
    @if($errors->any())
        <div class="bg-red-50 dark:bg-red-955/20 border border-red-200 dark:border-red-900/50 p-4 rounded-2xl text-red-650 dark:text-red-400 text-xs sm:text-sm font-semibold flex items-center space-x-2 shadow-xs">
            <svg class="w-5 h-5 text-red-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            <span>{{ $errors->first() }}</span>
        </div>
    @endif

    <!-- Main Detail Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <!-- Left Side: Detail Event, Brackets, Rules -->
        <div class="lg:col-span-2 space-y-6 order-2 lg:order-1">
            
            <!-- Tournament General Card -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800/80 rounded-3xl p-6 shadow-xs relative overflow-hidden">
                <div class="absolute left-0 top-0 bottom-0 w-1.5 bg-gradient-to-b from-orange-500 to-red-650"></div>
                
                <div class="space-y-4">
                    <div class="flex items-center space-x-2">
                        @if($tournament->status === 'registration')
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-emerald-50 dark:bg-emerald-950/30 text-emerald-600 dark:text-emerald-400 border border-emerald-255/20">
                                Pendaftaran Buka
                            </span>
                        @elseif($tournament->status === 'ongoing')
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-blue-50 dark:bg-blue-950/30 text-blue-600 dark:text-blue-400 border border-blue-255/20">
                                Sedang Tanding
                            </span>
                        @elseif($tournament->status === 'completed')
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-slate-150 dark:bg-slate-800 text-slate-550 dark:text-slate-400 border border-slate-200/50">
                                Selesai
                            </span>
                        @endif
                        <span class="text-xs font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">
                            {{ $tournament->type === 'clash_squad' ? 'Clash Squad Free Fire' : 'Battle Royale Free Fire' }}
                        </span>
                    </div>

                    <h3 class="text-2xl sm:text-3xl font-extrabold text-slate-850 dark:text-slate-100 tracking-tight leading-tight">
                        {{ $tournament->name }}
                    </h3>

                    @if($tournament->description)
                        <div class="text-sm text-slate-600 dark:text-slate-300 leading-relaxed bg-slate-50 dark:bg-slate-950/10 border border-slate-200/40 dark:border-slate-800/40 p-4 rounded-2xl">
                            {!! nl2br(e($tournament->description)) !!}
                        </div>
                    @endif

                    <!-- Details Grid Cards -->
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 pt-2">
                        <div class="bg-slate-50 dark:bg-slate-950/20 border border-slate-200/30 dark:border-slate-800/30 p-3 rounded-2xl flex items-center space-x-2.5">
                            <span class="text-2xl">💰</span>
                            <div>
                                <p class="text-[9px] text-slate-400 dark:text-slate-500 uppercase font-bold tracking-wider">Biaya</p>
                                <p class="text-xs sm:text-sm text-slate-800 dark:text-slate-250 font-extrabold truncate">
                                    {{ $tournament->registration_fee > 0 ? 'Rp ' . number_format($tournament->registration_fee, 0, ',', '.') : 'GRATIS' }}
                                </p>
                            </div>
                        </div>
                        <div class="bg-slate-50 dark:bg-slate-950/20 border border-slate-200/30 dark:border-slate-800/30 p-3 rounded-2xl flex items-center space-x-2.5">
                            <span class="text-2xl">🎁</span>
                            <div>
                                <p class="text-[9px] text-slate-400 dark:text-slate-500 uppercase font-bold tracking-wider">Prize Pool</p>
                                <p class="text-xs sm:text-sm text-orange-500 font-extrabold truncate">{{ $tournament->prize_pool }}</p>
                            </div>
                        </div>
                        <div class="bg-slate-50 dark:bg-slate-950/20 border border-slate-200/30 dark:border-slate-800/30 p-3 rounded-2xl flex items-center space-x-2.5">
                            <span class="text-2xl">📅</span>
                            <div>
                                <p class="text-[9px] text-slate-400 dark:text-slate-500 uppercase font-bold tracking-wider">Jadwal Tanding</p>
                                <p class="text-xs sm:text-sm text-slate-800 dark:text-slate-250 font-extrabold truncate">
                                    {{ $tournament->start_date ? $tournament->start_date->translatedFormat('d M Y, H:i') : '-' }}
                                </p>
                            </div>
                        </div>
                        <div class="bg-slate-50 dark:bg-slate-950/20 border border-slate-200/30 dark:border-slate-800/30 p-3 rounded-2xl flex items-center space-x-2.5">
                            <span class="text-2xl">👥</span>
                            <div>
                                <p class="text-[9px] text-slate-400 dark:text-slate-500 uppercase font-bold tracking-wider">Slot Terisi</p>
                                <p class="text-xs sm:text-sm text-slate-800 dark:text-slate-250 font-extrabold truncate">
                                    {{ $approvedTeams->count() }} / {{ $tournament->max_slots ?? '∞' }} {{ ($tournament->type === 'battle_royale' && $tournament->team_mode === 'solo') ? 'Pemain' : 'Tim' }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabs Navigation Bar (Modern Underline Design) -->
            <div class="border-b border-slate-200 dark:border-slate-800/80 flex gap-6 overflow-x-auto no-scrollbar scroll-smooth">
                <button onclick="switchDetailTab('bagan')" id="detail-tab-button-bagan" class="detail-tab-btn pb-3 text-sm font-bold border-b-2 border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200 transition duration-150 whitespace-nowrap flex items-center space-x-2">
                    <span>📊</span>
                    <span>Bagan & Klasemen</span>
                </button>
                <button onclick="switchDetailTab('rules')" id="detail-tab-button-rules" class="detail-tab-btn pb-3 text-sm font-bold border-b-2 border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200 transition duration-150 whitespace-nowrap flex items-center space-x-2">
                    <span>📜</span>
                    <span>Syarat & Ketentuan</span>
                </button>
                <button onclick="switchDetailTab('teams')" id="detail-tab-button-teams" class="detail-tab-btn pb-3 text-sm font-bold border-b-2 border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200 transition duration-150 whitespace-nowrap flex items-center space-x-2">
                    <span>👥</span>
                    <span>Peserta Terverifikasi</span>
                </button>
                @if(Auth::check() && $tournament->status === 'registration' && !$hasPendingOrApproved && (!$tournament->max_slots || $approvedTeams->count() < $tournament->max_slots))
                    <button onclick="switchDetailTab('register')" id="detail-tab-button-register" class="detail-tab-btn pb-3 text-sm font-bold border-b-2 border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200 transition duration-150 whitespace-nowrap flex items-center space-x-2">
                        <span>🏆</span>
                        <span>Form Pendaftaran</span>
                    </button>
                @endif
            </div>

            <!-- Tab: Bagan / Klasemen -->
            <div id="detail-tab-content-bagan" class="detail-tab-content">
                @if($tournament->type === 'clash_squad')
                    @if(in_array($tournament->status, ['ongoing', 'completed']) && !$matches->isEmpty())
                        <!-- Actual Bracket -->
                        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800/80 rounded-3xl p-6 shadow-xs space-y-6 overflow-x-auto">
                            <h4 class="text-base font-extrabold text-slate-800 dark:text-slate-100 flex items-center space-x-2 border-b border-slate-100 dark:border-slate-850 pb-4">
                                <span>📊 Bagan Pertandingan (Clash Squad)</span>
                            </h4>
                            
                            <div class="flex gap-8 justify-around min-w-[700px] pt-2">
                                @foreach($matches->groupBy('round_number') as $roundNumber => $roundMatches)
                                    <div class="flex flex-col justify-around space-y-8 flex-1">
                                        <div class="text-center">
                                            <span class="bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-350 text-[10px] font-black uppercase tracking-widest px-3 py-1 rounded-full border border-slate-200/10">
                                                @if($roundNumber == 1)
                                                    Babak Awal
                                                @elseif($loop->last)
                                                    Final
                                                @else
                                                    Semifinal
                                                @endif
                                            </span>
                                        </div>
                                        
                                        <div class="space-y-6 flex-1 flex flex-col justify-center">
                                            @foreach($roundMatches as $m)
                                                <div class="bg-slate-50 dark:bg-slate-950/20 border border-slate-200 dark:border-slate-800 rounded-2xl p-4 shadow-xs relative space-y-2">
                                                    <div class="flex items-center justify-between text-xs">
                                                        <span class="truncate max-w-[120px] font-bold flex items-center space-x-1.5 {{ $m->winner_id && $m->winner_id === $m->team1_id ? 'text-emerald-500 font-extrabold' : 'text-slate-655 dark:text-slate-400' }}">
                                                            <span>🛡️</span>
                                                            <span>{{ $m->team1 ? $m->team1->team_name : 'BYE / Belum Lolos' }}</span>
                                                        </span>
                                                        @if($m->status === 'completed')
                                                            <span class="font-black text-slate-850 dark:text-slate-200 {{ $m->winner_id && $m->winner_id === $m->team1_id ? 'text-emerald-500 font-extrabold' : '' }}">
                                                                {{ $m->team1_score }}
                                                            </span>
                                                        @endif
                                                    </div>
                                                    
                                                    <div class="border-t border-slate-200/50 dark:border-slate-800/50 my-1"></div>
                                                    
                                                    <div class="flex items-center justify-between text-xs">
                                                        <span class="truncate max-w-[120px] font-bold flex items-center space-x-1.5 {{ $m->winner_id && $m->winner_id === $m->team2_id ? 'text-emerald-500 font-extrabold' : 'text-slate-655 dark:text-slate-400' }}">
                                                            <span>🛡️</span>
                                                            <span>{{ $m->team2 ? $m->team2->team_name : 'BYE / Belum Lolos' }}</span>
                                                        </span>
                                                        @if($m->status === 'completed')
                                                            <span class="font-black text-slate-850 dark:text-slate-200 {{ $m->winner_id && $m->winner_id === $m->team2_id ? 'text-emerald-500 font-extrabold' : '' }}">
                                                                {{ $m->team2_score }}
                                                            </span>
                                                        @endif
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @else
                        <!-- Bagan Sedang Disusun -->
                        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800/80 rounded-3xl p-8 shadow-xs text-center">
                            <span class="text-3xl">🌱</span>
                            <h5 class="font-bold text-slate-800 dark:text-slate-200 text-sm mt-2">Bagan Sedang Disusun</h5>
                            <p class="text-xs text-slate-450 dark:text-slate-500 mt-1 max-w-sm mx-auto">
                                Bagan pertandingan Clash Squad otomatis dibentuk setelah pendaftaran ditutup oleh Admin.
                            </p>
                        </div>
                    @endif
                @else
                    <!-- Format Battle Royale -->
                    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800/80 rounded-3xl p-8 shadow-xs text-center space-y-2">
                        <span class="text-3xl">🏆</span>
                        <h5 class="font-bold text-slate-800 dark:text-slate-200 text-sm">Klasemen Format Battle Royale</h5>
                        <p class="text-xs text-slate-450 dark:text-slate-500 max-w-sm mx-auto">
                            Leaderboard poin Battle Royale akan diperbarui secara langsung oleh Admin setiap kali pertandingan ronde selesai!
                        </p>
                    </div>
                @endif
            </div>

            <!-- Tab: Syarat & Ketentuan -->
            <div id="detail-tab-content-rules" class="detail-tab-content hidden">
                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800/80 rounded-3xl p-6 shadow-xs space-y-4">
                    <h4 class="text-base font-extrabold text-slate-800 dark:text-slate-100 flex items-center space-x-2 border-b border-slate-100 dark:border-slate-850 pb-4">
                        <span>📜 Syarat & Ketentuan Turnamen</span>
                    </h4>
                    
                    <ul class="space-y-4 text-xs sm:text-sm text-slate-600 dark:text-slate-350 font-medium">
                        <li class="flex items-start space-x-3">
                            <span class="bg-orange-500 text-white rounded-full w-5 h-5 flex items-center justify-center font-bold text-xs flex-shrink-0 mt-0.5 shadow-sm">1</span>
                            @if($tournament->type === 'battle_royale' && $tournament->team_mode === 'solo')
                                <span>Pemain **wajib memiliki akun terdaftar dan aktif** di website ini.</span>
                            @elseif($tournament->type === 'battle_royale' && $tournament->team_mode === 'duo')
                                <span>Seluruh anggota tim (2 pemain) **wajib memiliki akun terdaftar dan aktif** di website ini.</span>
                            @else
                                <span>Seluruh anggota tim (4 pemain) **wajib memiliki akun terdaftar dan aktif** di website ini. Akun yang belum terdaftar tidak akan bisa ditambahkan ke dalam daftar tim.</span>
                            @endif
                        </li>
                        <li class="flex items-start space-x-3">
                            <span class="bg-orange-500 text-white rounded-full w-5 h-5 flex items-center justify-center font-bold text-xs flex-shrink-0 mt-0.5 shadow-sm">2</span>
                            <span>Biaya pendaftaran akan langsung dipotong dari **Saldo Akun Pendaftar/Kapten** saat mendaftar. Pendaftar wajib mengisi saldo terlebih dahulu sebelum mendaftar.</span>
                        </li>
                        <li class="flex items-start space-x-3">
                            <span class="bg-orange-500 text-white rounded-full w-5 h-5 flex items-center justify-center font-bold text-xs flex-shrink-0 mt-0.5 shadow-sm">3</span>
                            @if($tournament->type === 'battle_royale' && $tournament->team_mode === 'solo')
                                <span>Setiap pendaftar harus mengisi nickname Free Fire dan ID karakter yang valid.</span>
                            @elseif($tournament->type === 'battle_royale' && $tournament->team_mode === 'duo')
                                <span>Setiap tim harus diisi tepat 2 orang pemain yang valid (ID Free Fire dan Nickname harus persis seperti di game).</span>
                            @else
                                <span>Setiap tim harus diisi tepat 4 orang pemain yang valid (ID Free Fire dan Nickname harus persis seperti di game).</span>
                            @endif
                        </li>
                        <li class="flex items-start space-x-3">
                            <span class="bg-orange-500 text-white rounded-full w-5 h-5 flex items-center justify-center font-bold text-xs flex-shrink-0 mt-0.5 shadow-sm">4</span>
                            <span>Apabila pendaftaran Anda ditolak oleh Admin (karena data tidak valid/alasan lainnya), biaya pendaftaran akan **dikembalikan 100% (Refund Otomatis)** ke saldo akun Kapten/Pendaftar.</span>
                        </li>
                        <li class="flex items-start space-x-3">
                            <span class="bg-orange-500 text-white rounded-full w-5 h-5 flex items-center justify-center font-bold text-xs flex-shrink-0 mt-0.5 shadow-sm">5</span>
                            <span>Setiap akun hanya diperbolehkan bergabung dalam 1 pendaftaran pada turnamen yang sama. Dilarang melakukan kecurangan (*multi-account*).</span>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Tab: Form Pendaftaran -->
            @if(Auth::check() && $tournament->status === 'registration' && !$hasPendingOrApproved && (!$tournament->max_slots || $approvedTeams->count() < $tournament->max_slots))
                <div id="detail-tab-content-register" class="detail-tab-content hidden">
                    <div id="registration-form" class="space-y-6">
                        <!-- Helper Information Box -->
                        <div class="bg-gradient-to-r from-blue-500/10 to-indigo-500/10 border border-blue-500/20 rounded-3xl p-5 text-slate-700 dark:text-slate-300 text-xs leading-relaxed space-y-2">
                            <div class="flex items-center space-x-2 text-blue-600 dark:text-blue-400 font-extrabold">
                                <span class="text-base">💡</span>
                                <span>Informasi Penting Sebelum Mendaftar</span>
                            </div>
                            <ul class="list-disc pl-4 space-y-1 text-slate-550 dark:text-slate-400 font-medium">
                                <li>Pendaftaran diproses menggunakan saldo akun Anda (Kapten).</li>
                                <li>Anggota tim wajib memiliki Akun RZK Store. Masukkan <strong>ID Akun Website</strong> mereka (misal: <code>RZK-00024</code>) yang dapat diperoleh di halaman Profil masing-masing anggota.</li>
                                <li>Tindakan curang berupa manipulasi ID Game atau multi-akun dapat didiskualifikasi secara permanen tanpa pengembalian biaya pendaftaran.</li>
                            </ul>
                        </div>

                        <!-- Form Card -->
                        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800/80 rounded-3xl p-6 shadow-sm space-y-6">
                            <div class="flex items-center space-x-3 border-b border-slate-100 dark:border-slate-850 pb-4">
                                <span class="text-2xl">🛡️</span>
                                <div>
                                    <h4 class="text-base font-extrabold text-slate-850 dark:text-slate-100">
                                        @if($tournament->type === 'battle_royale' && $tournament->team_mode === 'solo')
                                            Formulir Pendaftaran Solo
                                        @elseif($tournament->type === 'battle_royale' && $tournament->team_mode === 'duo')
                                            Formulir Pendaftaran Duo
                                        @else
                                            Formulir Pendaftaran Skuad (4v4)
                                        @endif
                                    </h4>
                                    <p class="text-xs text-slate-450">Silakan isi data akun Free Fire skuad Anda secara lengkap.</p>
                                </div>
                            </div>

                            <form action="{{ route('tournaments.register', $tournament->id) }}" method="POST" class="space-y-6">
                                @csrf
                                
                                @if(!($tournament->type === 'battle_royale' && $tournament->team_mode === 'solo'))
                                    <!-- Nama Tim -->
                                    <div class="space-y-1.5">
                                        <label for="team_name" class="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                            Nama Tim / Skuad
                                        </label>
                                        <input type="text" id="team_name" name="team_name" placeholder="Contoh: RZK Gaming Esports" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-950/20 border border-slate-200 dark:border-slate-800 focus:border-orange-500 focus:bg-white dark:focus:bg-slate-900 focus:outline-none rounded-2xl text-sm transition-all duration-200" required>
                                    </div>
                                @endif

                                <!-- Grid Player Input -->
                                <div class="space-y-5">
                                    <!-- Player 1 (Kapten) -->
                                    <div class="p-5 bg-gradient-to-br from-orange-500/5 to-red-500/5 dark:from-orange-500/5 dark:to-red-500/5 border-2 border-orange-500/20 dark:border-orange-500/10 rounded-2xl space-y-4">
                                        <div class="flex items-center justify-between border-b border-orange-500/10 pb-2">
                                            <span class="text-xs font-black text-orange-500 uppercase tracking-wider flex items-center space-x-1.5">
                                                <span>🛡️</span>
                                                <span>Player 1: Kapten Tim</span>
                                            </span>
                                            <span class="text-[10px] text-orange-500 font-extrabold bg-orange-500/10 px-2.5 py-0.5 rounded-full border border-orange-500/10">Pemilik Saldo</span>
                                        </div>
                                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                            <div>
                                                <label class="block text-[10px] font-bold text-slate-450 dark:text-slate-500 uppercase tracking-wider mb-1">ID Akun RZK Anda</label>
                                                <p class="text-xs font-extrabold text-slate-800 dark:text-slate-200 py-2.5 bg-white/50 dark:bg-slate-900/50 px-3 rounded-xl border border-slate-200/50 truncate select-all">{{ Auth::user()->getWebsiteId() }}</p>
                                            </div>
                                            <div>
                                                <label class="block text-[10px] font-bold text-slate-450 dark:text-slate-400 uppercase tracking-wider mb-1">Nickname Free Fire</label>
                                                <input type="text" name="player_nickname[]" placeholder="Nickname FF Anda" class="w-full px-3 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 focus:border-orange-500 focus:outline-none rounded-xl text-xs" required>
                                            </div>
                                            <div>
                                                <label class="block text-[10px] font-bold text-slate-450 dark:text-slate-400 uppercase tracking-wider mb-1">ID Karakter Free Fire</label>
                                                <input type="text" name="player_game_id[]" placeholder="ID Angka FF Anda" class="w-full px-3 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 focus:border-orange-500 focus:outline-none rounded-xl text-xs" required>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Players 2 and onwards -->
                                    @for($i = 2; $i <= $memberCount + 1; $i++)
                                        <div class="p-5 bg-slate-50/50 dark:bg-slate-950/10 border border-slate-200/40 dark:border-slate-800/40 rounded-2xl space-y-4">
                                            <div class="flex items-center justify-between border-b border-slate-200/30 pb-2">
                                                <span class="text-xs font-bold text-slate-700 dark:text-slate-350 uppercase tracking-wider">Player {{ $i }}: Anggota</span>
                                                <span class="text-[9px] text-slate-400 font-bold bg-slate-100 dark:bg-slate-800 px-2 py-0.5 rounded border border-slate-200/10">Wajib Terdaftar Web</span>
                                            </div>
                                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                                <div>
                                                    <label class="block text-[10px] font-bold text-slate-450 dark:text-slate-400 uppercase tracking-wider mb-1">ID Akun RZK Anggota</label>
                                                    <input type="text" name="player_website_id[]" placeholder="Contoh: RZK-00024" class="w-full px-3 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 focus:border-orange-500 focus:outline-none rounded-xl text-xs" required>
                                                </div>
                                                <div>
                                                    <label class="block text-[10px] font-bold text-slate-450 dark:text-slate-400 uppercase tracking-wider mb-1">Nickname Free Fire</label>
                                                    <input type="text" name="player_nickname[]" placeholder="Nickname Game" class="w-full px-3 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 focus:border-orange-500 focus:outline-none rounded-xl text-xs" required>
                                                </div>
                                                <div>
                                                    <label class="block text-[10px] font-bold text-slate-450 dark:text-slate-400 uppercase tracking-wider mb-1">ID Karakter Free Fire</label>
                                                    <input type="text" name="player_game_id[]" placeholder="ID Angka Game" class="w-full px-3 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 focus:border-orange-500 focus:outline-none rounded-xl text-xs" required>
                                                </div>
                                            </div>
                                        </div>
                                    @endfor
                                </div>

                                <!-- Button Submit -->
                                <div class="pt-4 border-t border-slate-100 dark:border-slate-850 flex flex-col sm:flex-row items-center justify-between gap-4">
                                    <p class="text-xs text-slate-450 font-medium">
                                        *Dengan mengklik tombol daftar, saldo akun Anda akan dikurangi secara langsung.
                                    </p>
                                    
                                    <button type="submit" class="w-full sm:w-auto bg-gradient-to-r from-orange-500 to-red-600 hover:from-orange-600 hover:to-red-700 text-white font-extrabold px-8 py-3.5 rounded-2xl shadow-lg shadow-orange-500/20 hover:scale-[1.02] active:scale-95 transition duration-200 text-sm">
                                        @if($tournament->registration_fee > 0)
                                            Daftar & Bayar Rp {{ number_format($tournament->registration_fee, 0, ',', '.') }}
                                        @else
                                            Kirim Pendaftaran (GRATIS)
                                        @endif
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Tab: Tim Terverifikasi -->
            <div id="detail-tab-content-teams" class="detail-tab-content hidden">
                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800/80 rounded-3xl p-6 shadow-xs space-y-4">
                    <h4 class="font-extrabold text-slate-800 dark:text-slate-100 text-base flex items-center justify-between border-b border-slate-100 dark:border-slate-850 pb-4">
                        <span>👥 Daftar Peserta / Tim Terverifikasi</span>
                        <span class="bg-orange-50 dark:bg-orange-950/20 text-orange-500 text-xs px-3 py-1 rounded-full font-bold">
                            {{ $approvedTeams->count() }} {{ ($tournament->type === 'battle_royale' && $tournament->team_mode === 'solo') ? 'Peserta' : 'Tim' }}
                        </span>
                    </h4>
                    
                    @if($approvedTeams->isEmpty())
                        <p class="text-xs text-slate-450 dark:text-slate-500 font-medium italic py-4">Belum ada peserta yang disetujui oleh Admin.</p>
                    @else
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            @foreach($approvedTeams as $team)
                                <div class="bg-slate-50 dark:bg-slate-950/20 border border-slate-200/50 p-4 rounded-2xl space-y-3">
                                    <div class="flex items-center justify-between">
                                        <h5 class="font-extrabold text-slate-850 dark:text-slate-200 text-sm truncate">
                                            🛡️ {{ $team->team_name }}
                                        </h5>
                                        <span class="text-[9px] font-bold text-slate-450 dark:text-slate-500 bg-slate-100 dark:bg-slate-800 px-2 py-0.5 rounded border border-slate-200/10 truncate max-w-[120px]">
                                            Kapten: {{ $team->captain->name }}
                                        </span>
                                    </div>
                                    <div class="grid grid-cols-2 gap-2 text-xs font-semibold text-slate-500 dark:text-slate-400 border-t border-slate-200/30 dark:border-slate-800/40 pt-2.5">
                                        @foreach($team->participants as $p)
                                            <div class="bg-white dark:bg-slate-900 border border-slate-200/30 dark:border-slate-800/30 p-2 rounded-xl">
                                                <span class="block text-[8px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wide">Nickname</span>
                                                <span class="text-slate-700 dark:text-slate-300 font-bold block truncate">{{ $p->nickname }}</span>
                                                <span class="text-[8px] text-slate-450 block">ID: {{ $p->game_id }}</span>
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

        <!-- Right Side: Action Box -->
        <div class="space-y-6 order-1 lg:order-2">
            
            <!-- Registration Status / CTA Card -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800/80 rounded-3xl p-6 shadow-xs relative overflow-hidden">
                <div class="absolute left-0 right-0 top-0 h-1 bg-gradient-to-r from-orange-500 to-red-650"></div>
                
                <h4 class="font-extrabold text-slate-850 dark:text-slate-100 text-base mb-3.5 flex items-center space-x-2">
                    <span>📝 Status Keikutsertaan</span>
                </h4>
                
                @if($hasPendingOrApproved)
                    <!-- Registered Status Badge -->
                    <div class="space-y-4">
                        <div class="p-4 bg-slate-50 dark:bg-slate-950/20 border border-slate-200/50 dark:border-slate-800/50 rounded-2xl space-y-3">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold text-slate-500 uppercase">Nama Tim:</span>
                                <span class="text-sm font-extrabold text-slate-800 dark:text-slate-200">{{ $userRegistration->team_name }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold text-slate-550 uppercase">Peran Anda:</span>
                                <span class="text-xs font-bold text-slate-700 dark:text-slate-300">
                                    @if($tournament->type === 'battle_royale' && $tournament->team_mode === 'solo')
                                        👤 Peserta Solo
                                    @else
                                        {{ $isCaptain ? '🛡️ Kapten Tim' : '👤 Anggota Tim' }}
                                    @endif
                                </span>
                            </div>
                            <div class="flex items-center justify-between pt-2.5 border-t border-slate-100 dark:border-slate-850">
                                <span class="text-xs font-bold text-slate-500 uppercase">Status:</span>
                                @if($userRegistration->status === 'approved')
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-emerald-50 dark:bg-emerald-950/30 text-emerald-600 dark:text-emerald-400 border border-emerald-200/60 shadow-xs">
                                        Disetujui ✅
                                    </span>
                                @else
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-amber-50 dark:bg-amber-950/30 text-amber-600 dark:text-amber-400 border border-amber-200/60 animate-pulse">
                                        Menunggu ACC ⏳
                                    </span>
                                @endif
                            </div>
                        </div>

                        <div class="p-3 bg-slate-50 dark:bg-slate-950/10 text-slate-500 dark:text-slate-400 rounded-2xl text-[11px] font-semibold text-center border border-slate-200/10">
                            @if($userRegistration->status === 'approved')
                                Selamat! Pendaftaran Skuad Anda disetujui. Silakan pantau bagan tanding.
                            @else
                                Pendaftaran Anda sedang dikunci dan sedang ditinjau oleh Admin RZK Store.
                            @endif
                        </div>
                    </div>
                @else
                    <!-- Eligible to Register / Register CTA -->
                    @if($tournament->status === 'registration')
                        <div class="space-y-4">
                            <p class="text-xs text-slate-550 dark:text-slate-400 font-medium">
                                Anda belum terdaftar dalam turnamen ini. Hubungi anggota skuad Anda untuk mengumpulkan ID Akun RZK mereka.
                            </p>
                            
                            @if($tournament->max_slots && $approvedTeams->count() >= $tournament->max_slots)
                                <div class="bg-red-50 dark:bg-red-955/20 text-red-600 border border-red-200/40 p-4 rounded-2xl text-center text-xs font-bold shadow-xs">
                                    🚫 Slot Tim Sudah Penuh!
                                </div>
                            @else
                                @if(Auth::check())
                                    <!-- Button to switch tab to registration form -->
                                    <button onclick="switchDetailTab('register'); document.getElementById('registration-form').scrollIntoView({behavior: 'smooth'});" class="w-full bg-gradient-to-r from-orange-500 to-red-650 hover:from-orange-600 hover:to-red-700 text-white font-extrabold py-3.5 rounded-2xl shadow-lg shadow-orange-500/20 flex items-center justify-center space-x-2 transition duration-200 hover:scale-[1.02] text-sm">
                                        <span>🏆 Isi Form Pendaftaran</span>
                                    </button>
                                    
                                    @if($userRegistration && $userRegistration->status === 'rejected')
                                        <div class="bg-red-50 dark:bg-red-955/20 text-red-600 border border-red-200/30 p-3.5 rounded-2xl text-xs space-y-1.5 shadow-xs">
                                            <p class="font-extrabold text-[10px] uppercase text-red-750">❌ PENDAFTARAN DITOLAK:</p>
                                            <p class="italic text-[10px] font-medium">"{{ $userRegistration->rejection_reason ?? 'Tidak ada alasan diberikan.' }}"</p>
                                            <p class="text-[9px] text-slate-450 dark:text-slate-500 font-bold pt-1 uppercase">Dana refund telah masuk otomatis ke saldo Anda. Silakan isi form dan ajukan ulang.</p>
                                        </div>
                                    @else
                                        <p class="text-[10px] text-center text-slate-450 dark:text-slate-500 font-bold uppercase tracking-wider">
                                            Potong Saldo: {{ $tournament->registration_fee > 0 ? 'Rp ' . number_format($tournament->registration_fee, 0, ',', '.') : 'GRATIS' }}
                                        </p>
                                    @endif
                                @else
                                    <a href="{{ route('login') }}" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold py-3.5 rounded-2xl shadow-lg shadow-emerald-500/20 flex items-center justify-center space-x-2 transition duration-200 hover:scale-[1.02] text-sm">
                                        <span>🔑 Login untuk Mendaftar</span>
                                    </a>
                                    <p class="text-[10px] text-center text-slate-450 dark:text-slate-500 font-bold uppercase tracking-wider">
                                        Wajib login untuk berpartisipasi
                                    </p>
                                @endif
                            @endif
                        </div>
                    @else
                        <div class="bg-slate-50 dark:bg-slate-950/20 border border-slate-200/40 p-4 rounded-2xl text-center space-y-1">
                            <span class="text-xl">🔒</span>
                            <h5 class="font-bold text-slate-800 dark:text-slate-200 text-xs">Pendaftaran Ditutup</h5>
                            <p class="text-[10px] text-slate-450">Turnamen sedang berjalan atau telah selesai.</p>
                        </div>
                    @endif
                @endif
            </div>
        </div>

    </div>
</div>

<script>
function switchDetailTab(tabName) {
    // Hide all tab contents
    document.querySelectorAll('.detail-tab-content').forEach(el => {
        el.classList.add('hidden');
    });

    // Reset button styles to inactive
    document.querySelectorAll('.detail-tab-btn').forEach(btn => {
        btn.className = "detail-tab-btn pb-3 text-sm font-bold border-b-2 border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200 transition duration-150 whitespace-nowrap flex items-center space-x-2";
    });

    // Show selected content
    const activeContent = document.getElementById('detail-tab-content-' + tabName);
    if (activeContent) {
        activeContent.classList.remove('hidden');
    }

    // Set active button styles
    const activeBtn = document.getElementById('detail-tab-button-' + tabName);
    if (activeBtn) {
        activeBtn.className = "detail-tab-btn pb-3 text-sm font-extrabold border-b-2 border-orange-500 text-orange-500 dark:text-orange-400 transition duration-150 whitespace-nowrap flex items-center space-x-2";
    }
    
    // Store current tab in localStorage
    localStorage.setItem('tournament_detail_active_tab_{{ $tournament->id }}', tabName);
}

document.addEventListener('DOMContentLoaded', function() {
    // Read tab from localStorage if exists, default to 'bagan'
    const savedTab = localStorage.getItem('tournament_detail_active_tab_{{ $tournament->id }}') || 'bagan';
    switchDetailTab(savedTab);
});
</script>
@endsection
