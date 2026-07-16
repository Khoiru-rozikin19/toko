@extends('layouts.app')

@section('content')
<div class="space-y-8 max-w-6xl mx-auto">
    <!-- Hero Banner -->
    <div class="relative rounded-3xl overflow-hidden bg-gradient-to-br from-slate-900 via-slate-800 to-indigo-950 p-6 sm:p-10 shadow-xl border border-slate-700/30 text-white">
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_30%_30%,rgba(249,115,22,0.15),transparent_50%)]"></div>
        <div class="relative z-10 max-w-2xl space-y-4">
            <span class="inline-flex items-center space-x-1.5 px-3 py-1 rounded-full text-xs font-bold bg-orange-500/20 text-orange-400 border border-orange-500/30 uppercase tracking-widest animate-pulse">
                🏆 Arena Kompetisi RZK
            </span>
            <h2 class="text-3xl sm:text-4xl font-extrabold tracking-tight bg-gradient-to-r from-orange-400 via-amber-400 to-yellow-300 bg-clip-text text-transparent">
                Turnamen Free Fire RZK Store
            </h2>
            <p class="text-sm sm:text-base text-slate-300 font-medium leading-relaxed">
                Tunjukkan kehebatan skuadmu, menangkan turnamen mingguan, kumpulkan poin kejuaraan, dan jadilah yang terbaik di Papan Peringkat Global!
            </p>
        </div>
    </div>

    <!-- Navigation Tabs Bar -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800/80 rounded-2xl p-2 shadow-xs flex flex-col sm:flex-row gap-1">
        <button onclick="switchTab('active')" id="tab-button-active" class="tab-btn w-full flex items-center justify-center space-x-2 px-5 py-3 rounded-xl text-sm font-bold transition-all duration-200">
            <span>🔥</span>
            <span>Event Aktif</span>
        </button>
        <button onclick="switchTab('leaderboard')" id="tab-button-leaderboard" class="tab-btn w-full flex items-center justify-center space-x-2 px-5 py-3 rounded-xl text-sm font-bold transition-all duration-200">
            <span>👑</span>
            <span>Papan Peringkat</span>
        </button>
        <button onclick="switchTab('archive')" id="tab-button-archive" class="tab-btn w-full flex items-center justify-center space-x-2 px-5 py-3 rounded-xl text-sm font-bold transition-all duration-200">
            <span>📂</span>
            <span>Arsip Turnamen</span>
        </button>
    </div>

    <!-- Main Content Layout -->
    <div class="space-y-8">
        <!-- Active Tournaments Tab Section -->
        <div id="tab-content-active" class="tab-content">
            <section id="active-tournaments" class="space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-xl font-bold text-slate-800 dark:text-slate-100 flex items-center space-x-2">
                        <span>🔥 Turnamen Berlangsung (Event Aktif)</span>
                    </h3>
                </div>

                @if($activeTournaments->isEmpty())
                    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800/80 rounded-2xl p-10 text-center shadow-xs">
                        <div class="w-16 h-16 bg-slate-100 dark:bg-slate-800/50 text-slate-400 dark:text-slate-600 rounded-full flex items-center justify-center mx-auto mb-4 text-2xl">
                            🏆
                        </div>
                        <h4 class="font-extrabold text-slate-800 dark:text-slate-100 text-base">Belum Ada Turnamen Aktif</h4>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 max-w-sm mx-auto">
                            Saat ini pendaftaran sedang ditutup. Pantau terus halaman ini atau Bot Telegram kami untuk jadwal selanjutnya!
                        </p>
                    </div>
                @else
                    <div class="grid grid-cols-1 gap-6">
                        @foreach($activeTournaments as $t)
                            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800/80 rounded-3xl p-5 sm:p-6 shadow-xs hover:shadow-md hover:border-orange-500/20 dark:hover:border-orange-500/10 transition-all duration-200 relative overflow-hidden group">
                                <div class="absolute left-0 top-0 bottom-0 w-1.5 bg-gradient-to-b from-orange-500 to-red-650"></div>
                                
                                <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
                                    <div class="space-y-3 flex-1">
                                        <div class="flex flex-wrap items-center gap-2">
                                            @if($t->status === 'registration')
                                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-emerald-50 dark:bg-emerald-950/30 text-emerald-600 dark:text-emerald-400 border border-emerald-255/30 dark:border-emerald-900/50">
                                                    Pendaftaran Buka
                                                </span>
                                            @else
                                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-blue-50 dark:bg-blue-950/30 text-blue-600 dark:text-blue-400 border border-blue-255/30 dark:border-blue-900/50">
                                                    Sedang Tanding
                                                </span>
                                            @endif
                                            <span class="text-xs font-bold text-slate-450 dark:text-slate-500 uppercase tracking-wide bg-slate-100 dark:bg-slate-800/60 px-2 py-0.5 rounded-lg border border-slate-200/10">
                                                @if($t->type === 'clash_squad')
                                                    Clash Squad 4v4
                                                @else
                                                    Battle Royale ({{ ucfirst($t->team_mode ?? 'squad') }})
                                                @endif
                                            </span>
                                        </div>
                                        
                                        <h4 class="font-extrabold text-slate-850 dark:text-slate-100 text-lg sm:text-xl group-hover:text-orange-500 transition duration-200">
                                            {{ $t->name }}
                                        </h4>
                                        
                                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-xs font-semibold text-slate-500 dark:text-slate-400 pt-1">
                                            <div class="bg-slate-50 dark:bg-slate-950/20 border border-slate-200/30 dark:border-slate-800/30 p-2 rounded-xl flex items-center space-x-2">
                                                <span class="text-base">💰</span>
                                                <div>
                                                    <p class="text-[9px] text-slate-400 dark:text-slate-500 uppercase font-bold tracking-wider">Biaya</p>
                                                    <p class="text-slate-850 dark:text-slate-200 font-extrabold">
                                                        {{ $t->registration_fee > 0 ? 'Rp ' . number_format($t->registration_fee, 0, ',', '.') : 'GRATIS' }}
                                                    </p>
                                                </div>
                                            </div>
                                            <div class="bg-slate-50 dark:bg-slate-950/20 border border-slate-200/30 dark:border-slate-800/30 p-2 rounded-xl flex items-center space-x-2">
                                                <span class="text-base">🎁</span>
                                                <div>
                                                    <p class="text-[9px] text-slate-400 dark:text-slate-500 uppercase font-bold tracking-wider">Hadiah</p>
                                                    <p class="text-slate-850 dark:text-slate-200 font-extrabold text-orange-500">{{ $t->prize_pool }}</p>
                                                </div>
                                            </div>
                                            <div class="bg-slate-50 dark:bg-slate-950/20 border border-slate-200/30 dark:border-slate-800/30 p-2 rounded-xl flex items-center space-x-2 col-span-2 md:col-span-1">
                                                <span class="text-base">📅</span>
                                                <div>
                                                    <p class="text-[9px] text-slate-400 dark:text-slate-500 uppercase font-bold tracking-wider">Tanggal Mulai</p>
                                                    <p class="text-slate-850 dark:text-slate-200 font-extrabold">
                                                        {{ $t->start_date ? $t->start_date->translatedFormat('d M Y, H:i') : '-' }}
                                                    </p>
                                                </div>
                                            </div>
                                            <div class="bg-slate-50 dark:bg-slate-950/20 border border-slate-200/30 dark:border-slate-800/30 p-2 rounded-xl flex items-center space-x-2 col-span-2 md:col-span-1">
                                                <span class="text-base">👤</span>
                                                <div>
                                                    <p class="text-[9px] text-slate-400 dark:text-slate-500 uppercase font-bold tracking-wider">Format</p>
                                                    <p class="text-slate-850 dark:text-slate-200 font-extrabold truncate">
                                                        {{ ($t->type === 'battle_royale' && $t->team_mode === 'solo') ? 'Solo / Individu' : 'Duo/Squad Tim' }}
                                                    </p>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Progress Bar Slot -->
                                        @if($t->max_slots)
                                            <div class="pt-2">
                                                <div class="flex justify-between items-center text-xs font-semibold text-slate-550 dark:text-slate-400 mb-1">
                                                    <span class="flex items-center space-x-1">
                                                        <span>Slot Terisi:</span>
                                                        <strong class="text-slate-800 dark:text-slate-200">{{ $t->approved_registrations_count ?? 0 }} / {{ $t->max_slots }} {{ ($t->type === 'battle_royale' && $t->team_mode === 'solo') ? 'Peserta' : 'Tim' }}</strong>
                                                    </span>
                                                    <span>{{ round((($t->approved_registrations_count ?? 0) / $t->max_slots) * 100) }}%</span>
                                                </div>
                                                <div class="w-full bg-slate-100 dark:bg-slate-800 rounded-full h-2 overflow-hidden border border-slate-200/10">
                                                    <div class="bg-gradient-to-r from-orange-500 to-red-500 h-2 rounded-full transition-all duration-350" style="width: {{ min(100, round((($t->approved_registrations_count ?? 0) / $t->max_slots) * 100)) }}%"></div>
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                    
                                    <!-- Action Section -->
                                    <div class="lg:w-44 flex flex-row lg:flex-col lg:items-end justify-between lg:justify-center border-t lg:border-t-0 border-slate-100 dark:border-slate-800 pt-4 lg:pt-0 gap-4">
                                        <a href="{{ route('tournaments.show', $t->id) }}" class="w-full text-center bg-gradient-to-r from-orange-500 to-red-650 hover:from-orange-600 hover:to-red-700 text-white font-extrabold px-5 py-3 rounded-2xl text-xs shadow-md shadow-orange-500/15 hover:scale-[1.02] active:scale-95 transition-all duration-200">
                                            Detail Turnamen
                                        </a>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>
        </div>

        <!-- Leaderboard Tab Section -->
        <div id="tab-content-leaderboard" class="tab-content hidden">
            <div class="max-w-3xl mx-auto">
                <section id="global-leaderboard" class="space-y-6">
                    <div class="text-center">
                        <h3 class="text-2xl font-black text-slate-850 dark:text-slate-100 flex items-center justify-center space-x-2">
                            <span>👑 Papan Peringkat Global</span>
                        </h3>
                        <p class="text-xs text-slate-500 mt-1">Hall of Fame kejuaraan RZK Store. Poin didapatkan dari keaktifan & peringkat turnamen.</p>
                    </div>

                    @if($leaderboard->isEmpty())
                        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800/80 rounded-3xl p-10 text-center shadow-xs">
                            <span class="text-4xl">⚔️</span>
                            <p class="text-xs text-slate-400 dark:text-slate-500 mt-3 font-medium">Belum ada poin dibagikan. Jadilah yang pertama memenangkan turnamen!</p>
                        </div>
                    @else
                        @php
                            $top1 = $leaderboard->get(0);
                            $top2 = $leaderboard->get(1);
                            $top3 = $leaderboard->get(2);
                            $ranks4to10 = $leaderboard->slice(3);
                        @endphp

                        <!-- Top 3 Podium Layout -->
                        <div class="flex flex-col md:flex-row items-stretch md:items-end justify-center gap-4 pt-4">
                            <!-- Rank 2 -->
                            @if($top2)
                                <div class="bg-gradient-to-b from-slate-50 to-slate-100 dark:from-slate-850 dark:to-slate-900 border border-slate-200 dark:border-slate-800/80 rounded-3xl p-5 text-center flex-1 order-2 md:order-1 flex flex-col justify-between shadow-xs hover:shadow-md transition duration-200">
                                    <div>
                                        <span class="text-3xl block">🥈</span>
                                        <div class="mt-2">
                                            <h5 class="font-extrabold text-slate-800 dark:text-slate-200 text-sm truncate">{{ $top2->user ? $top2->user->name : 'Player' }}</h5>
                                            <p class="text-[9px] text-slate-400 font-bold uppercase tracking-wider mt-0.5">Peringkat 2</p>
                                        </div>
                                    </div>
                                    <div class="mt-4">
                                        <span class="bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-300 text-xs font-black px-3.5 py-1 rounded-lg">
                                            {{ $top2->total_points }} Pts
                                        </span>
                                    </div>
                                </div>
                            @endif

                            <!-- Rank 1 -->
                            @if($top1)
                                <div class="bg-gradient-to-b from-amber-50 via-yellow-50 to-amber-100/50 dark:from-amber-950/20 dark:via-yellow-950/10 dark:to-amber-900/10 border-2 border-yellow-400 rounded-3xl p-6 text-center flex-1 order-1 md:order-2 flex flex-col justify-between shadow-md transform md:-translate-y-4 hover:shadow-lg transition duration-200 relative overflow-hidden">
                                    <div class="absolute -right-4 -top-4 w-12 h-12 bg-yellow-400/10 rounded-full blur-xl"></div>
                                    <div>
                                        <span class="text-4xl block animate-bounce">🥇</span>
                                        <div class="mt-2">
                                            <span class="inline-block text-[9px] font-black text-amber-600 uppercase tracking-widest bg-yellow-400/20 px-2.5 py-0.5 rounded-full mb-1">Champion</span>
                                            <h5 class="font-extrabold text-slate-900 dark:text-slate-100 text-base truncate">{{ $top1->user ? $top1->user->name : 'Player' }}</h5>
                                            <p class="text-[9px] text-slate-400 font-bold uppercase tracking-wider mt-0.5">Peringkat 1</p>
                                        </div>
                                    </div>
                                    <div class="mt-5">
                                        <span class="bg-gradient-to-r from-yellow-500 to-amber-500 text-white text-xs font-black px-5 py-1.5 rounded-xl shadow-xs">
                                            {{ $top1->total_points }} Pts
                                        </span>
                                    </div>
                                </div>
                            @endif

                            <!-- Rank 3 -->
                            @if($top3)
                                <div class="bg-gradient-to-b from-slate-50 to-slate-100 dark:from-slate-850 dark:to-slate-900 border border-slate-200 dark:border-slate-800/80 rounded-3xl p-5 text-center flex-1 order-3 flex flex-col justify-between shadow-xs hover:shadow-md transition duration-200">
                                    <div>
                                        <span class="text-3xl block">🥉</span>
                                        <div class="mt-2">
                                            <h5 class="font-extrabold text-slate-800 dark:text-slate-200 text-sm truncate">{{ $top3->user ? $top3->user->name : 'Player' }}</h5>
                                            <p class="text-[9px] text-slate-400 font-bold uppercase tracking-wider mt-0.5">Peringkat 3</p>
                                        </div>
                                    </div>
                                    <div class="mt-4">
                                        <span class="bg-amber-100/40 dark:bg-amber-950/20 text-amber-700 dark:text-amber-400 text-xs font-black px-3.5 py-1 rounded-lg">
                                            {{ $top3->total_points }} Pts
                                        </span>
                                    </div>
                                </div>
                            @endif
                        </div>

                        <!-- Remaining Leaderboard List (Ranks 4-10) -->
                        @if($ranks4to10->isNotEmpty())
                            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800/80 rounded-3xl p-4 sm:p-5 shadow-xs space-y-2 mt-6">
                                @foreach($ranks4to10 as $index => $record)
                                    <div class="flex items-center justify-between p-3 rounded-2xl hover:bg-slate-50 dark:hover:bg-slate-800/30 transition duration-150">
                                        <div class="flex items-center space-x-4">
                                            <!-- Rank Number -->
                                            <div class="w-7 h-7 rounded-xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-slate-500 dark:text-slate-400 font-bold text-xs">
                                                {{ $index + 4 }}
                                            </div>
                                            
                                            <div class="overflow-hidden">
                                                <h5 class="font-bold text-slate-800 dark:text-slate-200 text-sm truncate max-w-[180px]">
                                                    {{ $record->user ? $record->user->name : 'Unknown Player' }}
                                                </h5>
                                                <p class="text-[9px] text-slate-450 dark:text-slate-500 font-bold uppercase tracking-wider mt-0.5">
                                                    {{ $record->user ? ucfirst($record->user->role) : 'Guest' }}
                                                </p>
                                            </div>
                                        </div>
                                        
                                        <div class="text-right">
                                            <span class="bg-slate-100 dark:bg-slate-800/60 text-slate-600 dark:text-slate-300 text-xs font-extrabold px-3 py-1 rounded-lg">
                                                {{ $record->total_points }} Pts
                                            </span>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    @endif
                </section>
            </div>
        </div>

        <!-- Archive Tab Section -->
        <div id="tab-content-archive" class="tab-content hidden">
            <section id="past-tournaments" class="space-y-4">
                <h3 class="text-xl font-bold text-slate-800 dark:text-slate-100 flex items-center space-x-2">
                    <span>🏆 Turnamen Selesai (Arsip)</span>
                </h3>

                @if($pastTournaments->isEmpty())
                    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800/80 rounded-2xl p-10 text-center shadow-xs">
                        <span class="text-3xl">📂</span>
                        <p class="text-xs text-slate-400 dark:text-slate-500 mt-2 font-medium">Belum ada turnamen terdahulu yang diselesaikan.</p>
                    </div>
                @else
                    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800/80 rounded-3xl divide-y divide-slate-100 dark:divide-slate-800/60 shadow-xs overflow-hidden">
                        @foreach($pastTournaments as $t)
                            <div class="p-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 hover:bg-slate-50/50 dark:hover:bg-slate-950/10 transition">
                                <div class="space-y-1">
                                    <div class="flex items-center space-x-2">
                                        <span class="px-2.5 py-0.5 rounded-full text-[9px] font-bold uppercase tracking-wider bg-slate-150 dark:bg-slate-800 text-slate-550 dark:text-slate-400 border border-slate-200/50">
                                            Selesai
                                        </span>
                                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wide bg-slate-50 dark:bg-slate-850 px-2 py-0.5 rounded border border-slate-200/10">
                                            @if($t->type === 'clash_squad')
                                                Clash Squad 4v4
                                            @else
                                                Battle Royale ({{ ucfirst($t->team_mode ?? 'squad') }})
                                            @endif
                                        </span>
                                    </div>
                                    <h4 class="font-extrabold text-slate-850 dark:text-slate-200 text-sm">
                                        {{ $t->name }}
                                    </h4>
                                </div>
                                <a href="{{ route('tournaments.show', $t->id) }}" class="border border-slate-200 dark:border-slate-800 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-355 px-4 py-2 rounded-xl text-xs font-bold transition flex items-center justify-center space-x-1">
                                    <span>Lihat Hasil</span>
                                    <span>→</span>
                                </a>
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>
        </div>

    </div>
</div>

<script>
function switchTab(tabName) {
    // Hide all tab contents
    document.querySelectorAll('.tab-content').forEach(el => {
        el.classList.add('hidden');
    });

    // Remove active styles from all tab buttons, and restore inactive styles
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.className = "tab-btn w-full flex items-center justify-center space-x-2 px-5 py-3 rounded-xl text-sm font-bold text-slate-550 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-all duration-200";
    });

    // Show selected tab content
    const activeContent = document.getElementById('tab-content-' + tabName);
    if (activeContent) {
        activeContent.classList.remove('hidden');
    }

    // Set active style for selected button
    const activeBtn = document.getElementById('tab-button-' + tabName);
    if (activeBtn) {
        activeBtn.className = "tab-btn w-full flex items-center justify-center space-x-2 px-5 py-3 rounded-xl text-sm font-extrabold bg-orange-500 text-white shadow-md shadow-orange-500/20 hover:bg-orange-600 transition-all duration-200";
    }
    
    // Store current tab in localStorage so it persists on reload
    localStorage.setItem('tournament_active_tab', tabName);
}

document.addEventListener('DOMContentLoaded', function() {
    // Read tab from localStorage if exists, default to 'active'
    const savedTab = localStorage.getItem('tournament_active_tab') || 'active';
    switchTab(savedTab);
});
</script>
@endsectionhTab(savedTab);
});
</script>
@endsection
