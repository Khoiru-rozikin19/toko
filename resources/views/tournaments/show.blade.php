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
                    @php
                        $maxSlots = (int) ($tournament->max_slots ?? 2);
                        if ($maxSlots < 2) $maxSlots = 2;
                        $totalRounds = (int) log($maxSlots, 2);
                        $half = $maxSlots / 2;

                        $teamsBySlot = [];
                        foreach ($approvedTeams as $idx => $team) {
                            if ($idx < $half) {
                                // Sisi kanan/bawah terisi dulu
                                $slotNum = $half + 1 + $idx;
                            } else {
                                // Sisi kiri/atas terisi setelahnya
                                $slotNum = 1 + ($idx - $half);
                            }
                            $teamsBySlot[$slotNum] = $team;
                        }

                        // Index database matches
                        $dbMatches = [];
                        if (isset($matches) && !$matches->isEmpty()) {
                            foreach ($matches as $m) {
                                $dbMatches[$m->round_number][$m->match_number] = $m;
                            }
                        }

                        // Generate full structure for rendering
                        $bracketStructure = [];
                        for ($round = 1; $round <= $totalRounds; $round++) {
                            $matchesCount = $maxSlots / pow(2, $round);
                            for ($matchNum = 1; $matchNum <= $matchesCount; $matchNum++) {
                                if (isset($dbMatches[$round][$matchNum])) {
                                    $m = $dbMatches[$round][$matchNum];
                                    $team1 = $m->team1;
                                    $team2 = $m->team2;
                                    $team1Score = $m->team1_score;
                                    $team2Score = $m->team2_score;
                                    $winnerId = $m->winner_id;
                                    $status = $m->status;
                                    $id = $m->id;
                                } else {
                                    $team1 = null;
                                    $team2 = null;
                                    $team1Score = null;
                                    $team2Score = null;
                                    $winnerId = null;
                                    $status = 'pending';
                                    $id = null;

                                    if ($round === 1) {
                                        $slot1 = 2 * $matchNum - 1;
                                        $slot2 = 2 * $matchNum;
                                        $team1 = $teamsBySlot[$slot1] ?? null;
                                        $team2 = $teamsBySlot[$slot2] ?? null;
                                    }
                                }

                                $bracketStructure[$round][$matchNum] = [
                                    'id' => $id,
                                    'team1' => $team1,
                                    'team2' => $team2,
                                    'team1_score' => $team1Score,
                                    'team2_score' => $team2Score,
                                    'winner_id' => $winnerId,
                                    'status' => $status,
                                    'match_number' => $matchNum,
                                    'slot1_num' => ($round === 1) ? (2 * $matchNum - 1) : null,
                                    'slot2_num' => ($round === 1) ? (2 * $matchNum) : null,
                                ];
                            }
                        }

                        // Fetch third place match
                        $thirdPlaceMatch = isset($dbMatches[99][1]) ? $dbMatches[99][1] : null;

                        // Dynamic round names
                        $roundNames = [];
                        for ($round = 1; $round <= $totalRounds; $round++) {
                            $matchesInRound = $maxSlots / pow(2, $round);
                            if ($matchesInRound == 8) {
                                $roundNames[$round] = '16 Besar';
                            } elseif ($matchesInRound == 4) {
                                $roundNames[$round] = 'Perempat Final';
                            } elseif ($matchesInRound == 2) {
                                $roundNames[$round] = 'Semifinal';
                            } elseif ($matchesInRound == 1) {
                                $roundNames[$round] = 'Final';
                            } else {
                                $roundNames[$round] = 'Ronde ' . $round;
                            }
                        }

                        // Helper to get premium circular colored avatars
                        $getAvatar = function($team) {
                            if (!$team) {
                                return '<div class="w-5 h-5 rounded-full bg-slate-100 dark:bg-slate-800 text-[10px] font-bold flex items-center justify-center text-slate-400 border border-slate-200/10">?</div>';
                            }
                            $name = $team->team_name;
                            $initial = strtoupper(substr($name, 0, 1));
                            $colors = ['bg-rose-500', 'bg-sky-500', 'bg-emerald-500', 'bg-amber-500', 'bg-indigo-500', 'bg-violet-500', 'bg-fuchsia-500', 'bg-orange-500'];
                            $colorIndex = crc32($name) % count($colors);
                            $color = $colors[$colorIndex];
                            return '<div class="w-5 h-5 rounded-full ' . $color . ' text-white text-[10px] font-black flex items-center justify-center shadow-xs border border-white/10">' . $initial . '</div>';
                        };
                    @endphp

                    <!-- Styles for Single-Sided Left-to-Right Bracket -->
                    <style>
                        .bracket-scroll-container {
                            overflow-x: auto;
                            padding: 1rem 0;
                            display: flex;
                            flex-direction: column;
                        }
                        .bracket-wrapper {
                            display: flex;
                            gap: 2.5rem;
                            padding: 1rem 0.5rem;
                            min-width: max-content;
                        }
                        .bracket-column {
                            display: flex;
                            flex-direction: column;
                            width: 15rem;
                            flex-shrink: 0;
                        }
                        .bracket-match-card {
                            background-color: #ffffff;
                            border: 1px solid #e2e8f0;
                            border-radius: 1.25rem;
                            padding: 0.85rem;
                            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05);
                            position: relative;
                            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
                        }
                        .dark .bracket-match-card {
                            background-color: #0f172a;
                            border-color: #1e293b;
                        }
                        .bracket-match-card:hover {
                            transform: translateY(-2px);
                            box-shadow: 0 8px 16px -2px rgba(0, 0, 0, 0.08);
                        }
                    </style>

                    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800/80 rounded-3xl p-6 shadow-xs space-y-6">
                        <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-850 pb-4">
                            <h4 class="text-base font-extrabold text-slate-800 dark:text-slate-100 flex items-center space-x-2">
                                <span>📊 Bagan Pertandingan (Clash Squad {{ $maxSlots }} Slot)</span>
                            </h4>
                            @if($tournament->status === 'registration')
                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase tracking-wider bg-orange-50 dark:bg-orange-950/20 text-orange-500 border border-orange-200/20">
                                    🌱 Penyusunan Real-Time
                                </span>
                            @endif
                        </div>

                        <div class="bracket-scroll-container no-scrollbar">
                            <div class="bracket-wrapper" style="height: 620px;">
                                @for($r = 1; $r <= $totalRounds; $r++)
                                    <div class="bracket-column h-full">
                                        <!-- Column Header -->
                                        <div class="text-center pb-3 border-b border-slate-100 dark:border-slate-800/60 mb-2">
                                            <span class="bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-350 text-[10px] font-black uppercase tracking-widest px-3 py-1 rounded-full border border-slate-200/10">
                                                {{ $roundNames[$r] }}
                                            </span>
                                        </div>

                                        <!-- Column Matches -->
                                        <div class="flex-1 flex flex-col justify-around py-4">
                                            @foreach($bracketStructure[$r] as $matchNum => $match)
                                                <div class="bracket-match-card space-y-2 {{ $match['id'] ? 'cursor-pointer hover:border-blue-500/50' : 'opacity-75' }}"
                                                     @if($match['id'])
                                                         onclick="openMatchDetailModal(this)"
                                                         data-id="{{ $match['id'] }}"
                                                         data-status="{{ $match['status'] }}"
                                                         data-match-num="{{ $match['match_number'] }}"
                                                         data-round-name="{{ $roundNames[$r] }}"
                                                         data-team1-id="{{ $match['team1'] ? $match['team1']->id : '' }}"
                                                         data-team2-id="{{ $match['team2'] ? $match['team2']->id : '' }}"
                                                         data-team1-name="{{ $match['team1'] ? $match['team1']->team_name : ($match['slot1_num'] ? 'Slot ' . $match['slot1_num'] : 'TBD') }}"
                                                         data-team2-name="{{ $match['team2'] ? $match['team2']->team_name : ($match['slot2_num'] ? 'Slot ' . $match['slot2_num'] : 'TBD') }}"
                                                         data-team1-score="{{ $match['team1_score'] ?? '-' }}"
                                                         data-team2-score="{{ $match['team2_score'] ?? '-' }}"
                                                         data-team1-phone="{{ $match['team1'] && $match['team1']->captain ? $match['team1']->captain->phone : '' }}"
                                                         data-team2-phone="{{ $match['team2'] && $match['team2']->captain ? $match['team2']->captain->phone : '' }}"
                                                         data-team1-captain-name="{{ $match['team1'] && $match['team1']->captain ? $match['team1']->captain->name : '' }}"
                                                         data-team2-captain-name="{{ $match['team2'] && $match['team2']->captain ? $match['team2']->captain->name : '' }}"
                                                         data-team1-captain-id="{{ $match['team1'] ? $match['team1']->captain_id : '' }}"
                                                         data-team2-captain-id="{{ $match['team2'] ? $match['team2']->captain_id : '' }}"
                                                         data-room-id="{{ $match['room_id'] ?? '' }}"
                                                         data-room-password="{{ $match['room_password'] ?? '' }}"
                                                         data-roster1="{{ json_encode($match['team1'] ? $match['team1']->participants->map(fn($p) => ['nickname' => $p->nickname, 'game_id' => $p->game_id, 'role' => $p->role]) : []) }}"
                                                         data-roster2="{{ json_encode($match['team2'] ? $match['team2']->participants->map(fn($p) => ['nickname' => $p->nickname, 'game_id' => $p->game_id, 'role' => $p->role]) : []) }}"
                                                     @endif
                                                >
                                                    <!-- Top Metadata -->
                                                    <div class="flex items-center justify-between text-[9px] text-slate-400 dark:text-slate-500 font-bold px-0.5">
                                                        <span>Match {{ $matchNum }}</span>
                                                        @if($match['status'] === 'completed')
                                                            <span class="bg-slate-100 dark:bg-slate-800/80 text-slate-500 dark:text-slate-400 px-1.5 py-0.5 rounded text-[8px] uppercase tracking-wider font-extrabold border border-slate-200/10">FT</span>
                                                        @else
                                                            <span class="text-orange-500 font-extrabold uppercase tracking-wider text-[8px]">Scheduled</span>
                                                        @endif
                                                    </div>

                                                    <!-- Team 1 -->
                                                    <div class="flex items-center justify-between text-xs min-h-[1.5rem]">
                                                        <div class="flex items-center space-x-2 min-w-0 {{ $match['winner_id'] && $match['winner_id'] === ($match['team1'] ? $match['team1']->id : null) ? 'text-slate-900 dark:text-slate-100 font-bold' : 'text-slate-500 dark:text-slate-400 font-medium' }}">
                                                            {!! $getAvatar($match['team1']) !!}
                                                            <span class="truncate" title="{{ $match['team1'] ? $match['team1']->team_name : '' }}">
                                                                {{ $match['team1'] ? $match['team1']->team_name : ($match['slot1_num'] ? 'Slot ' . $match['slot1_num'] : 'TBD') }}
                                                            </span>
                                                        </div>
                                                        <div class="flex items-center space-x-1.5 flex-shrink-0">
                                                            @if($match['status'] === 'completed' && $match['team1_score'] !== null)
                                                                <span class="font-black text-slate-850 dark:text-slate-200 {{ $match['winner_id'] && $match['winner_id'] === ($match['team1'] ? $match['team1']->id : null) ? 'text-slate-900 dark:text-slate-100' : 'text-slate-400 dark:text-slate-500' }}">
                                                                    {{ $match['team1_score'] }}
                                                                </span>
                                                            @endif
                                                            @if($match['winner_id'] && $match['winner_id'] === ($match['team1'] ? $match['team1']->id : null))
                                                                <span class="text-[8px] text-slate-950 dark:text-slate-50">◀</span>
                                                            @endif
                                                        </div>
                                                    </div>

                                                    <div class="border-t border-slate-100 dark:border-slate-850/60"></div>

                                                    <!-- Team 2 -->
                                                    <div class="flex items-center justify-between text-xs min-h-[1.5rem]">
                                                        <div class="flex items-center space-x-2 min-w-0 {{ $match['winner_id'] && $match['winner_id'] === ($match['team2'] ? $match['team2']->id : null) ? 'text-slate-900 dark:text-slate-100 font-bold' : 'text-slate-500 dark:text-slate-400 font-medium' }}">
                                                            {!! $getAvatar($match['team2']) !!}
                                                            <span class="truncate" title="{{ $match['team2'] ? $match['team2']->team_name : '' }}">
                                                                {{ $match['team2'] ? $match['team2']->team_name : ($match['slot2_num'] ? 'Slot ' . $match['slot2_num'] : 'TBD') }}
                                                            </span>
                                                        </div>
                                                        <div class="flex items-center space-x-1.5 flex-shrink-0">
                                                            @if($match['status'] === 'completed' && $match['team2_score'] !== null)
                                                                <span class="font-black text-slate-850 dark:text-slate-200 {{ $match['winner_id'] && $match['winner_id'] === ($match['team2'] ? $match['team2']->id : null) ? 'text-slate-900 dark:text-slate-100' : 'text-slate-400 dark:text-slate-500' }}">
                                                                    {{ $match['team2_score'] }}
                                                                </span>
                                                            @endif
                                                            @if($match['winner_id'] && $match['winner_id'] === ($match['team2'] ? $match['team2']->id : null))
                                                                <span class="text-[8px] text-slate-950 dark:text-slate-50">◀</span>
                                                            @endif
                                                        </div>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endfor
                            </div>
                        </div>

                        <!-- ================== THIRD PLACE PLAYOFF (Tempat Ketiga) ================== -->
                        @if($maxSlots >= 4)
                            <div class="border-t border-slate-100 dark:border-slate-800/80 pt-6 mt-6">
                                <div class="max-w-[15rem]">
                                    <div class="text-[10px] font-black uppercase tracking-widest text-slate-400 dark:text-slate-500 mb-3 px-1">
                                        🥉 Tempat Ketiga
                                    </div>
                                    @php
                                        $t1 = $thirdPlaceMatch ? $thirdPlaceMatch->team1 : null;
                                        $t2 = $thirdPlaceMatch ? $thirdPlaceMatch->team2 : null;
                                        $t1Score = $thirdPlaceMatch ? $thirdPlaceMatch->team1_score : null;
                                        $t2Score = $thirdPlaceMatch ? $thirdPlaceMatch->team2_score : null;
                                        $wId = $thirdPlaceMatch ? $thirdPlaceMatch->winner_id : null;
                                        $tStatus = $thirdPlaceMatch ? $thirdPlaceMatch->status : 'pending';
                                        
                                        $mockThirdPlace = [
                                            'team1' => $t1,
                                            'team2' => $t2,
                                            'team1_score' => $t1Score,
                                            'team2_score' => $t2Score,
                                            'winner_id' => $wId,
                                            'status' => $tStatus,
                                            'slot1_num' => null,
                                            'slot2_num' => null,
                                        ];
                                    @endphp
                                    
                                    <div class="bracket-match-card space-y-2 border-2 border-slate-100 dark:border-slate-800/80 shadow-xs {{ $thirdPlaceMatch ? 'cursor-pointer hover:border-blue-500/50' : 'opacity-75' }}"
                                         @if($thirdPlaceMatch)
                                             onclick="openMatchDetailModal(this)"
                                             data-id="{{ $thirdPlaceMatch->id }}"
                                             data-status="{{ $thirdPlaceMatch->status }}"
                                             data-match-num="1"
                                             data-round-name="Tempat Ketiga"
                                             data-team1-id="{{ $thirdPlaceMatch->team1 ? $thirdPlaceMatch->team1->id : '' }}"
                                             data-team2-id="{{ $thirdPlaceMatch->team2 ? $thirdPlaceMatch->team2->id : '' }}"
                                             data-team1-name="{{ $thirdPlaceMatch->team1 ? $thirdPlaceMatch->team1->team_name : 'Kalah Semifinal 1' }}"
                                             data-team2-name="{{ $thirdPlaceMatch->team2 ? $thirdPlaceMatch->team2->team_name : 'Kalah Semifinal 2' }}"
                                             data-team1-score="{{ $thirdPlaceMatch->team1_score ?? '-' }}"
                                             data-team2-score="{{ $thirdPlaceMatch->team2_score ?? '-' }}"
                                             data-team1-phone="{{ $thirdPlaceMatch->team1 && $thirdPlaceMatch->team1->captain ? $thirdPlaceMatch->team1->captain->phone : '' }}"
                                             data-team2-phone="{{ $thirdPlaceMatch->team2 && $thirdPlaceMatch->team2->captain ? $thirdPlaceMatch->team2->captain->phone : '' }}"
                                             data-team1-captain-name="{{ $thirdPlaceMatch->team1 && $thirdPlaceMatch->team1->captain ? $thirdPlaceMatch->team1->captain->name : '' }}"
                                             data-team2-captain-name="{{ $thirdPlaceMatch->team2 && $thirdPlaceMatch->team2->captain ? $thirdPlaceMatch->team2->captain->name : '' }}"
                                             data-team1-captain-id="{{ $thirdPlaceMatch->team1 ? $thirdPlaceMatch->team1->captain_id : '' }}"
                                             data-team2-captain-id="{{ $thirdPlaceMatch->team2 ? $thirdPlaceMatch->team2->captain_id : '' }}"
                                             data-room-id="{{ $thirdPlaceMatch->room_id ?? '' }}"
                                             data-room-password="{{ $thirdPlaceMatch->room_password ?? '' }}"
                                             data-roster1="{{ json_encode($thirdPlaceMatch->team1 ? $thirdPlaceMatch->team1->participants->map(fn($p) => ['nickname' => $p->nickname, 'game_id' => $p->game_id, 'role' => $p->role]) : []) }}"
                                             data-roster2="{{ json_encode($thirdPlaceMatch->team2 ? $thirdPlaceMatch->team2->participants->map(fn($p) => ['nickname' => $p->nickname, 'game_id' => $p->game_id, 'role' => $p->role]) : []) }}"
                                         @endif
                                    >
                                        <!-- Top Metadata -->
                                        <div class="flex items-center justify-between text-[9px] text-slate-400 dark:text-slate-500 font-bold px-0.5">
                                            <span>Perebutan Juara 3</span>
                                            @if($mockThirdPlace['status'] === 'completed')
                                                <span class="bg-slate-100 dark:bg-slate-800/80 text-slate-500 dark:text-slate-400 px-1.5 py-0.5 rounded text-[8px] uppercase tracking-wider font-extrabold border border-slate-200/10">FT</span>
                                            @else
                                                <span class="text-orange-500 font-extrabold uppercase tracking-wider text-[8px]">Scheduled</span>
                                            @endif
                                        </div>

                                        <!-- Team 1 -->
                                        <div class="flex items-center justify-between text-xs min-h-[1.5rem]">
                                            <div class="flex items-center space-x-2 min-w-0 {{ $mockThirdPlace['winner_id'] && $mockThirdPlace['winner_id'] === ($mockThirdPlace['team1'] ? $mockThirdPlace['team1']->id : null) ? 'text-slate-900 dark:text-slate-100 font-bold' : 'text-slate-500 dark:text-slate-400 font-medium' }}">
                                                {!! $getAvatar($mockThirdPlace['team1']) !!}
                                                <span class="truncate" title="{{ $mockThirdPlace['team1'] ? $mockThirdPlace['team1']->team_name : '' }}">
                                                    {{ $mockThirdPlace['team1'] ? $mockThirdPlace['team1']->team_name : 'Kalah Semifinal 1' }}
                                                </span>
                                            </div>
                                            <div class="flex items-center space-x-1.5 flex-shrink-0">
                                                @if($mockThirdPlace['status'] === 'completed' && $mockThirdPlace['team1_score'] !== null)
                                                    <span class="font-black text-slate-850 dark:text-slate-200 {{ $mockThirdPlace['winner_id'] && $mockThirdPlace['winner_id'] === ($mockThirdPlace['team1'] ? $mockThirdPlace['team1']->id : null) ? 'text-slate-900 dark:text-slate-100' : 'text-slate-400 dark:text-slate-500' }}">
                                                        {{ $mockThirdPlace['team1_score'] }}
                                                    </span>
                                                @endif
                                                @if($mockThirdPlace['winner_id'] && $mockThirdPlace['winner_id'] === ($mockThirdPlace['team1'] ? $mockThirdPlace['team1']->id : null))
                                                    <span class="text-[8px] text-slate-950 dark:text-slate-50">◀</span>
                                                @endif
                                            </div>
                                        </div>

                                        <div class="border-t border-slate-100 dark:border-slate-850/60"></div>

                                        <!-- Team 2 -->
                                        <div class="flex items-center justify-between text-xs min-h-[1.5rem]">
                                            <div class="flex items-center space-x-2 min-w-0 {{ $mockThirdPlace['winner_id'] && $mockThirdPlace['winner_id'] === ($mockThirdPlace['team2'] ? $mockThirdPlace['team2']->id : null) ? 'text-slate-900 dark:text-slate-100 font-bold' : 'text-slate-500 dark:text-slate-400 font-medium' }}">
                                                {!! $getAvatar($mockThirdPlace['team2']) !!}
                                                <span class="truncate" title="{{ $mockThirdPlace['team2'] ? $mockThirdPlace['team2']->team_name : '' }}">
                                                    {{ $mockThirdPlace['team2'] ? $mockThirdPlace['team2']->team_name : 'Kalah Semifinal 2' }}
                                                </span>
                                            </div>
                                            <div class="flex items-center space-x-1.5 flex-shrink-0">
                                                @if($mockThirdPlace['status'] === 'completed' && $mockThirdPlace['team2_score'] !== null)
                                                    <span class="font-black text-slate-850 dark:text-slate-200 {{ $mockThirdPlace['winner_id'] && $mockThirdPlace['winner_id'] === ($mockThirdPlace['team2'] ? $mockThirdPlace['team2']->id : null) ? 'text-slate-900 dark:text-slate-100' : 'text-slate-400 dark:text-slate-500' }}">
                                                        {{ $mockThirdPlace['team2_score'] }}
                                                    </span>
                                                @endif
                                                @if($mockThirdPlace['winner_id'] && $mockThirdPlace['winner_id'] === ($mockThirdPlace['team2'] ? $mockThirdPlace['team2']->id : null))
                                                    <span class="text-[8px] text-slate-950 dark:text-slate-50">◀</span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
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
                                <li>Anggota tim wajib memiliki Akun RZK Store. Masukkan <strong>ID Akun Website</strong> mereka (misal: <code>50595551</code>) yang dapat diperoleh di halaman Profil masing-masing anggota.</li>
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
                                                    <input type="text" name="player_website_id[]" placeholder="Contoh: 50595551" class="w-full px-3 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 focus:border-orange-500 focus:outline-none rounded-xl text-xs" required>
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

<!-- Modal Detail Match -->
<div id="matchDetailModal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <!-- Backdrop -->
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 transition-opacity bg-slate-900/60 dark:bg-slate-950/80 backdrop-blur-sm" onclick="closeMatchDetailModal()"></div>

        <!-- Modal Box -->
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
        <div class="relative z-10 inline-block align-bottom bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-xl sm:w-full rounded-3xl p-6 sm:p-8 space-y-6">
            
            <!-- Header -->
            <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-850 pb-4">
                <div>
                    <span id="modalRoundName" class="bg-blue-50 dark:bg-blue-950/20 text-blue-600 dark:text-blue-400 text-[9px] font-black uppercase tracking-widest px-2.5 py-0.5 rounded-full border border-blue-200/20">ROUND</span>
                    <h4 class="text-base font-extrabold text-slate-855 dark:text-slate-150 mt-1" id="modalMatchTitle">Match Detail</h4>
                </div>
                <button type="button" class="text-slate-400 hover:text-slate-650 dark:hover:text-slate-250 transition" onclick="closeMatchDetailModal()">
                    <span class="text-xl">✕</span>
                </button>
            </div>

            <!-- Tabs Navigation -->
            <div class="flex border-b border-slate-100 dark:border-slate-850 text-xs font-bold gap-4">
                <button type="button" id="tabBtnInfo" onclick="switchModalTab('info')" class="pb-3 border-b-2 border-blue-600 text-blue-600 dark:text-blue-400 focus:outline-none">
                    🎮 Info & Koordinasi
                </button>
                <button type="button" id="tabBtnRoster" onclick="switchModalTab('roster')" class="pb-3 border-b-2 border-transparent text-slate-400 dark:text-slate-500 focus:outline-none">
                    🛡️ Daftar Roster Nickname
                </button>
            </div>

            <!-- Tab Content: Info & Koordinasi -->
            <div id="modalTabContentInfo" class="space-y-5">
                
                <!-- Captain Contacts -->
                <div class="grid grid-cols-2 gap-4">
                    <div class="bg-slate-50 dark:bg-slate-950/20 border border-slate-100 dark:border-slate-850 p-4 rounded-2xl text-center space-y-2">
                        <span class="text-[9px] font-black text-slate-400 uppercase tracking-widest block">Kapten Tim 1</span>
                        <p id="modalTeam1Captain" class="text-xs font-bold text-slate-800 dark:text-slate-200 truncate">-</p>
                        <a id="modalTeam1WA" href="#" target="_blank" class="inline-flex items-center justify-center space-x-1.5 bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold text-[10px] px-3 py-1.5 rounded-full transition shadow-xs">
                            <span>💬 Hubungi WA</span>
                        </a>
                    </div>
                    <div class="bg-slate-50 dark:bg-slate-950/20 border border-slate-100 dark:border-slate-850 p-4 rounded-2xl text-center space-y-2">
                        <span class="text-[9px] font-black text-slate-400 uppercase tracking-widest block">Kapten Tim 2</span>
                        <p id="modalTeam2Captain" class="text-xs font-bold text-slate-800 dark:text-slate-200 truncate">-</p>
                        <a id="modalTeam2WA" href="#" target="_blank" class="inline-flex items-center justify-center space-x-1.5 bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold text-[10px] px-3 py-1.5 rounded-full transition shadow-xs">
                            <span>💬 Hubungi WA</span>
                        </a>
                    </div>
                </div>

                <!-- Room Credentials Display & Input -->
                <div class="bg-slate-50 dark:bg-slate-950/20 border border-slate-100 dark:border-slate-850 p-4 rounded-2xl space-y-3">
                    <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest block border-b border-slate-200/30 pb-1">🔑 Kredensial Room Lobby FF</span>
                    
                    <!-- View Room ID/Password -->
                    <div id="modalViewRoom" class="grid grid-cols-2 gap-4 text-xs">
                        <div>
                            <p class="text-[10px] font-bold text-slate-400">ROOM ID</p>
                            <p id="modalShowRoomId" class="font-extrabold text-slate-800 dark:text-slate-200 text-sm mt-0.5">-</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-bold text-slate-400">PASSWORD</p>
                            <p id="modalShowRoomPassword" class="font-extrabold text-slate-800 dark:text-slate-200 text-sm mt-0.5">-</p>
                        </div>
                    </div>

                    <!-- Edit Room ID/Password (Captains only) -->
                    @auth
                        <form id="modalFormRoom" action="#" method="POST" class="space-y-3 pt-2 border-t border-slate-200/20" style="display: none;">
                            @csrf
                            <div class="grid grid-cols-2 gap-4">
                                <div class="space-y-1">
                                    <label for="input_room_id" class="block text-[10px] font-bold text-slate-400 uppercase">Input Room ID</label>
                                    <input type="text" name="room_id" id="input_room_id" class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 focus:outline-none rounded-xl text-xs" placeholder="Misal: 1234567" required>
                                </div>
                                <div class="space-y-1">
                                    <label for="input_room_password" class="block text-[10px] font-bold text-slate-400 uppercase">Input Password</label>
                                    <input type="text" name="room_password" id="input_room_password" class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 focus:outline-none rounded-xl text-xs" placeholder="Password Room" required>
                                </div>
                            </div>
                            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 rounded-xl text-xs transition">
                                🚀 Bagikan Info Room ke WhatsApp Group & Lawan
                            </button>
                        </form>
                    @endauth
                </div>

                <!-- Upload Screenshot Form (Captains only) -->
                @auth
                    <div id="modalContainerReport" class="bg-slate-50 dark:bg-slate-950/20 border border-slate-100 dark:border-slate-850 p-4 rounded-2xl space-y-3" style="display: none;">
                        <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest block border-b border-slate-200/30 pb-1">🏆 Unggah Hasil Pertandingan (Bo3)</span>
                        
                        <form id="modalFormReport" action="#" method="POST" enctype="multipart/form-data" class="space-y-4">
                            @csrf
                            
                            <div class="grid grid-cols-3 gap-3">
                                <div class="space-y-1 col-span-2">
                                    <label for="reported_winner_id" class="block text-[10px] font-bold text-slate-400 uppercase">Klaim Pemenang Match</label>
                                    <select id="reported_winner_id" name="reported_winner_id" class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl text-xs" required>
                                        <!-- Will be populated dynamically by JS -->
                                    </select>
                                </div>
                                <div class="space-y-1">
                                    <label class="block text-[10px] font-bold text-slate-400 uppercase">Skor Match (Game)</label>
                                    <div class="flex items-center space-x-1">
                                        <input type="number" name="team1_score" id="input_score1" min="0" max="3" class="w-10 px-1 py-2 text-center bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 focus:outline-none rounded-xl text-xs" required>
                                        <span class="text-xs text-slate-400">-</span>
                                        <input type="number" name="team2_score" id="input_score2" min="0" max="3" class="w-10 px-1 py-2 text-center bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 focus:outline-none rounded-xl text-xs" required>
                                    </div>
                                </div>
                            </div>

                            <div class="space-y-3">
                                <div class="space-y-1">
                                    <label for="screenshot_1" class="block text-[10px] font-bold text-slate-400 uppercase">Bukti Screenshot Game 1 (Wajib)</label>
                                    <input type="file" name="screenshot_1" id="screenshot_1" accept="image/*" class="w-full text-xs text-slate-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-blue-50 dark:file:bg-slate-850 file:text-blue-700 dark:file:text-blue-300" required>
                                </div>
                                <div class="space-y-1">
                                    <label for="screenshot_2" class="block text-[10px] font-bold text-slate-400 uppercase">Bukti Screenshot Game 2 (Opsional)</label>
                                    <input type="file" name="screenshot_2" id="screenshot_2" accept="image/*" class="w-full text-xs text-slate-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-blue-50 dark:file:bg-slate-850 file:text-blue-700 dark:file:text-blue-300">
                                </div>
                                <div class="space-y-1">
                                    <label for="screenshot_3" class="block text-[10px] font-bold text-slate-400 uppercase">Bukti Screenshot Game 3 (Opsional)</label>
                                    <input type="file" name="screenshot_3" id="screenshot_3" accept="image/*" class="w-full text-xs text-slate-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-blue-50 dark:file:bg-slate-850 file:text-blue-700 dark:file:text-blue-300">
                                </div>
                            </div>

                            <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-2.5 rounded-xl text-xs transition shadow-md shadow-emerald-600/10">
                                ✅ Kirim Laporan Hasil & Bukti Screenshot
                            </button>
                        </form>
                    </div>
                @endauth
            </div>

            <!-- Tab Content: Roster List -->
            <div id="modalTabContentRoster" class="space-y-4 hidden">
                <div class="grid grid-cols-2 gap-4">
                    <!-- Team 1 Roster -->
                    <div class="space-y-2">
                        <span id="modalTeam1RosterTitle" class="text-[10px] font-black text-slate-400 uppercase tracking-widest block border-b border-slate-200/30 pb-1">TIM 1</span>
                        <div id="modalTeam1RosterList" class="space-y-2 text-xs">
                            <!-- Will be populated dynamically by JS -->
                        </div>
                    </div>

                    <!-- Team 2 Roster -->
                    <div class="space-y-2">
                        <span id="modalTeam2RosterTitle" class="text-[10px] font-black text-slate-400 uppercase tracking-widest block border-b border-slate-200/30 pb-1">TIM 2</span>
                        <div id="modalTeam2RosterList" class="space-y-2 text-xs">
                            <!-- Will be populated dynamically by JS -->
                        </div>
                    </div>
                </div>
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

function openMatchDetailModal(card) {
    const matchId = card.getAttribute('data-id');
    if (!matchId) return;

    const status = card.getAttribute('data-status');
    const matchNum = card.getAttribute('data-match-num');
    const roundName = card.getAttribute('data-round-name');
    const team1Name = card.getAttribute('data-team1-name');
    const team2Name = card.getAttribute('data-team2-name');
    const team1Id = card.getAttribute('data-team1-id');
    const team2Id = card.getAttribute('data-team2-id');
    
    const team1Captain = card.getAttribute('data-team1-captain-name') || '-';
    const team2Captain = card.getAttribute('data-team2-captain-name') || '-';
    const team1Phone = card.getAttribute('data-team1-phone') || '';
    const team2Phone = card.getAttribute('data-team2-phone') || '';
    
    const roomId = card.getAttribute('data-room-id') || '-';
    const roomPassword = card.getAttribute('data-room-password') || '-';
    
    const roster1 = JSON.parse(card.getAttribute('data-roster1') || '[]');
    const roster2 = JSON.parse(card.getAttribute('data-roster2') || '[]');

    const currentUserId = {{ auth()->check() ? auth()->id() : 'null' }};
    const team1CaptainId = card.getAttribute('data-team1-captain-id') || null;
    const team2CaptainId = card.getAttribute('data-team2-captain-id') || null;
    const isCaptain = (currentUserId && (currentUserId == team1CaptainId || currentUserId == team2CaptainId));

    // Set basic texts
    document.getElementById('modalRoundName').innerText = roundName;
    document.getElementById('modalMatchTitle').innerText = `${team1Name} vs ${team2Name}`;
    document.getElementById('modalTeam1Captain').innerText = team1Captain;
    document.getElementById('modalTeam2Captain').innerText = team2Captain;
    document.getElementById('modalShowRoomId').innerText = roomId;
    document.getElementById('modalShowRoomPassword').innerText = roomPassword;

    // Set WA links
    const waUrl = (phone) => phone ? `https://wa.me/${phone.replace(/[^0-9]/g, '')}` : '#';
    const t1WA = document.getElementById('modalTeam1WA');
    const t2WA = document.getElementById('modalTeam2WA');
    
    t1WA.href = waUrl(team1Phone);
    t1WA.style.display = team1Phone ? 'inline-flex' : 'none';
    t2WA.href = waUrl(team2Phone);
    t2WA.style.display = team2Phone ? 'inline-flex' : 'none';

    // Populating dropdown reported winner
    const selectWinner = document.getElementById('reported_winner_id');
    if (selectWinner) {
        selectWinner.innerHTML = `
            <option value="">-- Pilih Tim Pemenang --</option>
            ${team1Id ? `<option value="${team1Id}">${team1Name}</option>` : ''}
            ${team2Id ? `<option value="${team2Id}">${team2Name}</option>` : ''}
        `;
    }

    // Populate Roster lists
    document.getElementById('modalTeam1RosterTitle').innerText = `Roster: ${team1Name}`;
    document.getElementById('modalTeam2RosterTitle').innerText = `Roster: ${team2Name}`;

    const rosterListHtml = (roster) => {
        if (roster.length === 0) return '<p class="text-slate-400 italic p-2">Belum ada roster terdaftar</p>';
        return roster.map(p => `
            <div class="bg-slate-50 dark:bg-slate-950/20 border border-slate-100 dark:border-slate-850 p-2.5 rounded-xl space-y-0.5">
                <p class="font-extrabold text-slate-800 dark:text-slate-200 text-xs">🛡️ Nick: ${p.nickname}</p>
                <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">ID: ${p.game_id} | Role: ${p.role}</p>
            </div>
        `).join('');
    };

    document.getElementById('modalTeam1RosterList').innerHTML = rosterListHtml(roster1);
    document.getElementById('modalTeam2RosterList').innerHTML = rosterListHtml(roster2);

    // Configure room credentials form visibility
    const formRoom = document.getElementById('modalFormRoom');
    if (formRoom) {
        if (isCaptain && status !== 'completed') {
            formRoom.style.display = 'block';
            formRoom.action = `/tournaments/matches/${matchId}/room`;
            document.getElementById('input_room_id').value = roomId !== '-' ? roomId : '';
            document.getElementById('input_room_password').value = roomPassword !== '-' ? roomPassword : '';
        } else {
            formRoom.style.display = 'none';
        }
    }

    // Configure score reporting form visibility
    const containerReport = document.getElementById('modalContainerReport');
    if (containerReport) {
        if (isCaptain && status !== 'completed' && team1Id && team2Id) {
            containerReport.style.display = 'block';
            document.getElementById('modalFormReport').action = `/tournaments/matches/${matchId}/report`;
            document.getElementById('input_score1').value = '';
            document.getElementById('input_score2').value = '';
        } else {
            containerReport.style.display = 'none';
        }
    }

    // Switch default tab to info
    switchModalTab('info');

    // Show modal
    document.getElementById('matchDetailModal').classList.remove('hidden');
}

function closeMatchDetailModal() {
    document.getElementById('matchDetailModal').classList.add('hidden');
}

function switchModalTab(tab) {
    const btnInfo = document.getElementById('tabBtnInfo');
    const btnRoster = document.getElementById('tabBtnRoster');
    const contentInfo = document.getElementById('modalTabContentInfo');
    const contentRoster = document.getElementById('modalTabContentRoster');

    if (tab === 'info') {
        btnInfo.className = "pb-3 border-b-2 border-blue-600 text-blue-600 dark:text-blue-400 focus:outline-none";
        btnRoster.className = "pb-3 border-b-2 border-transparent text-slate-400 dark:text-slate-550 focus:outline-none";
        contentInfo.classList.remove('hidden');
        contentRoster.classList.add('hidden');
    } else {
        btnRoster.className = "pb-3 border-b-2 border-blue-600 text-blue-600 dark:text-blue-400 focus:outline-none";
        btnInfo.className = "pb-3 border-b-2 border-transparent text-slate-400 dark:text-slate-550 focus:outline-none";
        contentRoster.classList.remove('hidden');
        contentInfo.classList.add('hidden');
    }
}
</script>
@endsection
