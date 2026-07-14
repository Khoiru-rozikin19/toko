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

    <!-- Error/Validation Alert -->
    @if($errors->any())
        <div class="bg-red-50 dark:bg-red-950/20 border border-red-200 dark:border-red-900/50 p-4 rounded-2xl text-red-600 dark:text-red-400 text-xs sm:text-sm font-semibold flex items-center space-x-2">
            <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            <span>{{ $errors->first() }}</span>
        </div>
    @endif

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
                                {{ $approvedTeams->count() }} / {{ $tournament->max_slots ?? '∞' }} {{ ($tournament->type === 'battle_royale' && $tournament->team_mode === 'solo') ? 'Peserta' : 'Tim' }}
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
                        @if($tournament->type === 'battle_royale' && $tournament->team_mode === 'solo')
                            <span>Pemain **wajib memiliki akun terdaftar dan aktif** di website ini.</span>
                        @elseif($tournament->type === 'battle_royale' && $tournament->team_mode === 'duo')
                            <span>Seluruh anggota tim (2 pemain) **wajib memiliki akun terdaftar dan aktif** di website ini.</span>
                        @else
                            <span>Seluruh anggota tim (4 pemain) **wajib memiliki akun terdaftar dan aktif** di website ini. Akun yang belum terdaftar tidak akan bisa ditambahkan ke dalam daftar tim.</span>
                        @endif
                    </li>
                    <li class="flex items-start space-x-2.5">
                        <span class="bg-orange-500 text-white rounded-full w-5 h-5 flex items-center justify-center font-bold text-xs flex-shrink-0 mt-0.5">2</span>
                        <span>Biaya pendaftaran akan langsung dipotong dari **Saldo Akun Pendaftar/Kapten** saat mendaftar. Pendaftar wajib mengisi saldo terlebih dahulu sebelum mendaftar.</span>
                    </li>
                    <li class="flex items-start space-x-2.5">
                        <span class="bg-orange-500 text-white rounded-full w-5 h-5 flex items-center justify-center font-bold text-xs flex-shrink-0 mt-0.5">3</span>
                        @if($tournament->type === 'battle_royale' && $tournament->team_mode === 'solo')
                            <span>Setiap pendaftar harus mengisi nickname Free Fire dan ID karakter yang valid.</span>
                        @elseif($tournament->type === 'battle_royale' && $tournament->team_mode === 'duo')
                            <span>Setiap tim harus diisi tepat 2 orang pemain yang valid (ID Free Fire dan Nickname harus persis seperti di game).</span>
                        @else
                            <span>Setiap tim harus diisi tepat 4 orang pemain yang valid (ID Free Fire dan Nickname harus persis seperti di game).</span>
                        @endif
                    </li>
                    <li class="flex items-start space-x-2.5">
                        <span class="bg-orange-500 text-white rounded-full w-5 h-5 flex items-center justify-center font-bold text-xs flex-shrink-0 mt-0.5">4</span>
                        <span>Apabila pendaftaran Anda ditolak oleh Admin (karena data tidak valid/alasan lainnya), biaya pendaftaran akan **dikembalikan 100% (Refund Otomatis)** ke saldo akun Kapten/Pendaftar.</span>
                    </li>
                    <li class="flex items-start space-x-2.5">
                        <span class="bg-orange-500 text-white rounded-full w-5 h-5 flex items-center justify-center font-bold text-xs flex-shrink-0 mt-0.5">5</span>
                        <span>Setiap akun hanya diperbolehkan bergabung dalam 1 pendaftaran pada turnamen yang sama. Dilarang melakukan kecurangan (*multi-account*).</span>
                    </li>
                </ul>
            </div>

            <!-- BRACKET BAGAN CLASH SQUAD (Only when ongoing or completed) -->
            @if(in_array($tournament->status, ['ongoing', 'completed']) && $tournament->type === 'clash_squad' && !$matches->isEmpty())
                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800/80 rounded-3xl p-6 sm:p-8 shadow-xs space-y-6 overflow-x-auto">
                    <h4 class="text-lg font-bold text-slate-800 dark:text-slate-100 flex items-center space-x-2 border-b border-slate-100 dark:border-slate-850 pb-4">
                        <span>📊 Bagan Pertandingan (Bracket)</span>
                    </h4>
                    
                    <!-- Bracket Tree Layout -->
                    <div class="flex gap-8 justify-around min-w-[700px] pt-4">
                        @foreach($matches->groupBy('round_number') as $roundNumber => $roundMatches)
                            <!-- Round Column -->
                            <div class="flex flex-col justify-around space-y-8 flex-1">
                                <div class="text-center">
                                    <span class="bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-350 text-[10px] font-black uppercase tracking-widest px-3 py-1 rounded-full border border-slate-250/20 dark:border-slate-800">
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
                                        <div class="bg-slate-50 dark:bg-slate-950/20 border border-slate-200 dark:border-slate-800 rounded-2xl p-3 shadow-xs relative space-y-2">
                                            <!-- Team 1 -->
                                            <div class="flex items-center justify-between text-xs">
                                                <span class="truncate max-w-[120px] font-bold flex items-center space-x-1.5 {{ $m->winner_id && $m->winner_id === $m->team1_id ? 'text-emerald-500 font-extrabold' : 'text-slate-600 dark:text-slate-400' }}">
                                                    <span>🛡️</span>
                                                    <span>{{ $m->team1 ? $m->team1->team_name : 'BYE / Belum Lolos' }}</span>
                                                </span>
                                                @if($m->status === 'completed')
                                                    <span class="font-black text-slate-800 dark:text-slate-200 {{ $m->winner_id && $m->winner_id === $m->team1_id ? 'text-emerald-500 font-extrabold' : '' }}">
                                                        {{ $m->team1_score }}
                                                    </span>
                                                @endif
                                            </div>
                                            
                                            <!-- VS Separator Line -->
                                            <div class="border-t border-slate-200/50 dark:border-slate-800/50 my-1"></div>
                                            
                                            <!-- Team 2 -->
                                            <div class="flex items-center justify-between text-xs">
                                                <span class="truncate max-w-[120px] font-bold flex items-center space-x-1.5 {{ $m->winner_id && $m->winner_id === $m->team2_id ? 'text-emerald-500 font-extrabold' : 'text-slate-600 dark:text-slate-400' }}">
                                                    <span>🛡️</span>
                                                    <span>{{ $m->team2 ? $m->team2->team_name : 'BYE / Belum Lolos' }}</span>
                                                </span>
                                                @if($m->status === 'completed')
                                                    <span class="font-black text-slate-800 dark:text-slate-200 {{ $m->winner_id && $m->winner_id === $m->team2_id ? 'text-emerald-500 font-extrabold' : '' }}">
                                                        {{ $m->team2_score }}
                                                    </span>
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
            <!-- REGISTRATION FORM CARD (Left Side, Bottom) -->
            @if(Auth::check() && $tournament->status === 'registration' && !$hasPendingOrApproved && (!$tournament->max_slots || $approvedTeams->count() < $tournament->max_slots))
                <div id="registration-form" class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800/80 rounded-3xl p-6 sm:p-8 shadow-md space-y-6">
                    <div class="flex items-center space-x-3 border-b border-slate-100 dark:border-slate-850 pb-4">
                        <span class="text-2xl">🛡️</span>
                        <div>
                            <h4 class="text-lg font-bold text-slate-800 dark:text-slate-100">
                                @if($tournament->type === 'battle_royale' && $tournament->team_mode === 'solo')
                                    Formulir Pendaftaran Solo
                                @elseif($tournament->type === 'battle_royale' && $tournament->team_mode === 'duo')
                                    Formulir Pendaftaran Duo
                                @else
                                    Formulir Pendaftaran Skuad
                                @endif
                            </h4>
                            <p class="text-xs text-slate-500 dark:text-slate-400">Pastikan data yang Anda input sudah valid sebelum mengirimkan.</p>
                        </div>
                    </div>

                    <form action="{{ route('tournaments.register', $tournament->id) }}" method="POST" class="space-y-6">
                        @csrf
                        
                        <!-- Nama Tim -->
                        <div class="space-y-2">
                            <label for="team_name" class="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                @if($tournament->type === 'battle_royale' && $tournament->team_mode === 'solo')
                                    Nama Tim / Solo Nickname
                                @elseif($tournament->type === 'battle_royale' && $tournament->team_mode === 'duo')
                                    Nama Tim (Duo)
                                @else
                                    Nama Tim (Squad)
                                @endif
                            </label>
                            <input type="text" id="team_name" name="team_name" placeholder="Contoh: RZK Gaming Team" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-950/20 border border-slate-200 dark:border-slate-800 focus:border-orange-500 focus:bg-white dark:focus:bg-slate-900 focus:outline-none rounded-xl text-sm transition-all duration-200" required>
                        </div>

                        <!-- Grid Player -->
                        <div class="space-y-6">
                            <!-- Player 1 (Kapten / Solo) -->
                            <div class="p-4 bg-slate-50 dark:bg-slate-950/25 border border-slate-200/50 dark:border-slate-800/50 rounded-2xl space-y-3">
                                <div class="flex items-center justify-between border-b border-slate-200/40 dark:border-slate-800/40 pb-2">
                                    <span class="text-xs font-black text-orange-500 uppercase tracking-wider">
                                        @if($tournament->type === 'battle_royale' && $tournament->team_mode === 'solo')
                                            Data Pemain (Solo)
                                        @else
                                            Player 1: Kapten Skuad
                                        @endif
                                    </span>
                                    <span class="text-[10px] text-slate-400 font-bold bg-slate-100 dark:bg-slate-800 px-2 py-0.5 rounded-md">Akun Anda</span>
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                    <div class="space-y-1 sm:col-span-1">
                                        <span class="text-[10px] font-bold text-slate-450 dark:text-slate-400 uppercase tracking-wider">Akun Website</span>
                                        <p class="text-xs font-extrabold text-slate-700 dark:text-slate-300 py-2.5 truncate">{{ Auth::user()->getWebsiteId() }}</p>
                                    </div>
                                    <div class="space-y-1">
                                        <label class="text-[10px] font-bold text-slate-450 dark:text-slate-400 uppercase tracking-wider">Nickname FF</label>
                                        <input type="text" name="player_nickname[]" placeholder="Nickname Game" class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 focus:border-orange-500 focus:outline-none rounded-xl text-xs" required>
                                    </div>
                                    <div class="space-y-1">
                                        <label class="text-[10px] font-bold text-slate-450 dark:text-slate-400 uppercase tracking-wider">Character ID FF</label>
                                        <input type="text" name="player_game_id[]" placeholder="ID Angka" class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 focus:border-orange-500 focus:outline-none rounded-xl text-xs" required>
                                    </div>
                                </div>
                            </div>

                            <!-- Players 2 to $memberCount + 1 -->
                            @for($i = 2; $i <= $memberCount + 1; $i++)
                                <div class="p-4 bg-slate-50/50 dark:bg-slate-950/10 border border-slate-200/30 dark:border-slate-800/30 rounded-2xl space-y-3">
                                    <div class="flex items-center justify-between border-b border-slate-200/40 dark:border-slate-800/40 pb-2">
                                        <span class="text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider">Player {{ $i }}: Anggota Tim</span>
                                        <span class="text-[10px] text-red-500 font-bold uppercase tracking-wider">Wajib Akun Web</span>
                                    </div>
                                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                        <div class="space-y-1">
                                            <label class="text-[10px] font-bold text-slate-450 dark:text-slate-400 uppercase tracking-wider">ID Akun Website</label>
                                            <input type="text" name="player_website_id[]" placeholder="Contoh: RZK-00024" class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 focus:border-orange-500 focus:outline-none rounded-xl text-xs" required>
                                        </div>
                                        <div class="space-y-1">
                                            <label class="text-[10px] font-bold text-slate-450 dark:text-slate-400 uppercase tracking-wider">Nickname FF</label>
                                            <input type="text" name="player_nickname[]" placeholder="Nickname Game" class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 focus:border-orange-500 focus:outline-none rounded-xl text-xs" required>
                                        </div>
                                        <div class="space-y-1">
                                            <label class="text-[10px] font-bold text-slate-450 dark:text-slate-400 uppercase tracking-wider">Character ID FF</label>
                                            <input type="text" name="player_game_id[]" placeholder="ID Angka" class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 focus:border-orange-500 focus:outline-none rounded-xl text-xs" required>
                                        </div>
                                    </div>
                                </div>
                            @endfor
                        </div>

                        <!-- Button Submit -->
                        <div class="pt-4 border-t border-slate-100 dark:border-slate-850 flex flex-col sm:flex-row items-center justify-between gap-4">
                            <p class="text-xs text-slate-500 dark:text-slate-400 font-medium">
                                *Dengan mendaftar, saldo Kapten akan langsung dipotong sebesar biaya pendaftaran turnamen (jika berbayar).
                            </p>
                            
                            <button type="submit" class="w-full sm:w-auto bg-gradient-to-r from-orange-500 to-red-600 hover:from-orange-600 hover:to-red-700 text-white font-extrabold px-8 py-3.5 rounded-xl shadow-lg shadow-orange-500/20 hover:scale-[1.02] active:scale-95 transition duration-200 text-sm">
                                @if($tournament->registration_fee > 0)
                                    Daftar & Bayar Rp {{ number_format($tournament->registration_fee, 0, ',', '.') }}
                                @else
                                    Kirim Pendaftaran (GRATIS)
                                @endif
                            </button>
                        </div>
                    </form>
                </div>
            @endif
        </div>

        <!-- Right Side: Action Box & Registered Teams List -->
        <div class="space-y-8">
            
            <!-- Registration Status / CTA Card -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800/80 rounded-3xl p-6 shadow-sm relative overflow-hidden">
                <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-orange-500 to-red-600"></div>
                
                <h4 class="font-extrabold text-slate-800 dark:text-slate-100 text-base mb-3 flex items-center space-x-2">
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
                            <div class="flex items-center justify-between pt-2 border-t border-slate-100 dark:border-slate-850">
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

                        <div class="p-3 bg-emerald-50/50 dark:bg-emerald-950/10 text-slate-600 dark:text-slate-350 rounded-2xl text-[11px] font-semibold text-center border border-emerald-500/10">
                            Status pendaftaran tim Anda sedang dikunci. Silakan pantau halaman ini untuk melihat bagan jika sudah disetujui.
                        </div>
                    </div>
                @else
                    <!-- Eligible to Register / Register CTA -->
                    @if($tournament->status === 'registration')
                        <div class="space-y-4">
                            <p class="text-xs text-slate-500 dark:text-slate-400 font-medium">
                                Anda belum terdaftar dalam turnamen ini.
                            </p>
                            
                            @if($tournament->max_slots && $approvedTeams->count() >= $tournament->max_slots)
                                <div class="bg-red-50 dark:bg-red-950/20 text-red-600 dark:text-red-400 border border-red-200 dark:border-red-900/50 p-4 rounded-2xl text-center text-xs font-bold">
                                    🚫 Slot Tim Sudah Penuh!
                                </div>
                            @else
                                @if(Auth::check())
                                    <!-- Anchor link down to registration form card -->
                                    <a href="#registration-form" class="w-full bg-gradient-to-r from-orange-500 to-red-600 hover:from-orange-600 hover:to-red-700 text-white font-extrabold py-3.5 rounded-2xl shadow-lg shadow-orange-500/20 flex items-center justify-center space-x-2 transition duration-200 hover:scale-[1.02] text-sm">
                                        <span>🏆 Isi Form Pendaftaran</span>
                                    </a>
                                    
                                    @if($userRegistration && $userRegistration->status === 'rejected')
                                        <div class="bg-red-50 dark:bg-red-950/20 text-red-650 dark:text-red-400 border border-red-200/40 p-3 rounded-2xl text-xs space-y-1">
                                            <p class="font-extrabold text-[11px] uppercase">❌ PENDAFTARAN SEBELUMNYA DITOLAK:</p>
                                            <p class="italic text-[10px] font-medium">"{{ $userRegistration->rejection_reason ?? 'Tidak ada alasan diberikan.' }}"</p>
                                            <p class="text-[9px] text-slate-400 dark:text-slate-500 font-bold pt-1 uppercase">Saldo pendaftaran sebelumnya telah di-refund otomatis. Silakan submit ulang.</p>
                                        </div>
                                    @else
                                        <p class="text-[10px] text-center text-slate-400 dark:text-slate-500 font-bold uppercase tracking-wider">
                                            Potong Saldo: {{ $tournament->registration_fee > 0 ? 'Rp ' . number_format($tournament->registration_fee, 0, ',', '.') : 'GRATIS' }}
                                        </p>
                                    @endif
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
                                    <h5 class="font-extrabold text-slate-800 dark:text-slate-200 text-sm truncate max-w-[120px]">
                                        🛡️ {{ $team->team_name }}
                                    </h5>
                                    <span class="text-[10px] font-bold text-slate-450 dark:text-slate-500 truncate max-w-[90px]">
                                        Kapten: {{ $team->captain->name }}
                                    </span>
                                </div>
                                <div class="grid grid-cols-2 gap-2 text-[10px] font-semibold text-slate-500 dark:text-slate-400 border-t border-slate-250/20 dark:border-slate-800/40 pt-2">
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
