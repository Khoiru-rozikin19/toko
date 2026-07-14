@extends('layouts.app')

@section('content')
<div class="space-y-8 max-w-6xl mx-auto">
    <!-- Hero Banner -->
    <div class="relative rounded-3xl overflow-hidden bg-gradient-to-br from-slate-900 via-slate-800 to-indigo-950 p-6 sm:p-10 shadow-xl border border-slate-700/30 text-white">
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_30%_30%,rgba(249,115,22,0.1),transparent_50%)]"></div>
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
            <div class="flex items-center space-x-4 pt-2">
                <a href="#active-tournaments" class="bg-orange-500 hover:bg-orange-600 text-white font-bold px-5 py-2.5 rounded-xl text-sm shadow-lg shadow-orange-500/25 transition-all duration-200 hover:scale-[1.02]">
                    Lihat Event Aktif
                </a>
                <a href="#global-leaderboard" class="bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 font-bold px-5 py-2.5 rounded-xl text-sm transition-all duration-200 hover:scale-[1.02]">
                    Papan Peringkat
                </a>
            </div>
        </div>
    </div>

    <!-- Main Content Layout -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <!-- Left Side: Active and Past Tournaments -->
        <div class="lg:col-span-2 space-y-8">
            
            <!-- Active Tournaments Section -->
            <section id="active-tournaments" class="space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-xl font-bold text-slate-800 dark:text-slate-100 flex items-center space-x-2">
                        <span>🔥 Turnamen Berlangsung</span>
                    </h3>
                </div>

                @if($activeTournaments->isEmpty())
                    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800/80 rounded-2xl p-8 text-center shadow-xs">
                        <div class="w-16 h-16 bg-slate-100 dark:bg-slate-800/50 text-slate-400 dark:text-slate-600 rounded-full flex items-center justify-center mx-auto mb-4">
                            🏆
                        </div>
                        <h4 class="font-bold text-slate-800 dark:text-slate-100 text-base">Belum Ada Turnamen Aktif</h4>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 max-w-sm mx-auto">
                            Saat ini pendaftaran sedang ditutup. Pantau terus halaman ini atau Bot Telegram kami untuk jadwal selanjutnya!
                        </p>
                    </div>
                @else
                    <div class="grid grid-cols-1 gap-5">
                        @foreach($activeTournaments as $t)
                            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800/80 rounded-2xl p-5 sm:p-6 shadow-xs hover:shadow-md transition-all duration-200 relative overflow-hidden group">
                                <div class="absolute left-0 top-0 bottom-0 w-1.5 bg-gradient-to-b from-orange-500 to-red-600"></div>
                                
                                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                                    <div class="space-y-2">
                                        <div class="flex items-center space-x-2">
                                            @if($t->status === 'registration')
                                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-emerald-50 dark:bg-emerald-950/30 text-emerald-600 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-900/50">
                                                    Pendaftaran Buka
                                                </span>
                                            @else
                                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-blue-50 dark:bg-blue-950/30 text-blue-600 dark:text-blue-400 border border-blue-200 dark:border-blue-900/50">
                                                    Sedang Tanding
                                                </span>
                                            @endif
                                            <span class="text-xs font-semibold text-slate-400 dark:text-slate-500 uppercase tracking-wide">
                                                @if($t->type === 'clash_squad')
                                                    Clash Squad 4v4
                                                @else
                                                    Battle Royale ({{ ucfirst($t->team_mode ?? 'squad') }})
                                                @endif
                                            </span>
                                        </div>
                                        <h4 class="font-extrabold text-slate-800 dark:text-slate-100 text-lg group-hover:text-orange-500 transition duration-200">
                                            {{ $t->name }}
                                        </h4>
                                        <div class="flex flex-wrap items-center gap-x-4 gap-y-1.5 text-xs font-semibold text-slate-500 dark:text-slate-400">
                                            <span class="flex items-center space-x-1">
                                                <span>💰 Biaya:</span>
                                                <span class="text-slate-700 dark:text-slate-300 font-bold">
                                                    {{ $t->registration_fee > 0 ? 'Rp ' . number_format($t->registration_fee, 0, ',', '.') : 'GRATIS' }}
                                                </span>
                                            </span>
                                            <span class="hidden sm:inline text-slate-300 dark:text-slate-800">•</span>
                                            <span class="flex items-center space-x-1">
                                                <span>🎁 Hadiah:</span>
                                                <span class="text-slate-700 dark:text-slate-300 font-bold">{{ $t->prize_pool }}</span>
                                            </span>
                                            @if($t->max_slots)
                                                <span class="hidden sm:inline text-slate-300 dark:text-slate-800">•</span>
                                                <span class="flex items-center space-x-1">
                                                    <span>📦 Slot:</span>
                                                    <span class="text-slate-700 dark:text-slate-300 font-bold">Max {{ $t->max_slots }} {{ ($t->type === 'battle_royale' && $t->team_mode === 'solo') ? 'Peserta' : 'Tim' }}</span>
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="sm:text-right flex sm:flex-col items-center sm:items-end justify-between sm:justify-center gap-2 pt-3 sm:pt-0 border-t sm:border-t-0 border-slate-100 dark:border-slate-800">
                                        <div class="text-left sm:text-right">
                                            <p class="text-[10px] text-slate-400 dark:text-slate-500 uppercase tracking-wide font-bold">Jadwal Tanding</p>
                                            <p class="text-xs text-slate-700 dark:text-slate-300 font-bold">
                                                {{ $t->start_date ? $t->start_date->translatedFormat('d M Y, H:i') . ' WIB' : '-' }}
                                            </p>
                                        </div>
                                        <a href="{{ route('tournaments.show', $t->id) }}" class="bg-slate-100 hover:bg-orange-500 hover:text-white text-slate-700 dark:text-slate-300 dark:bg-slate-800/80 font-extrabold px-4 py-2 rounded-xl text-xs transition duration-200">
                                            Detail Event
                                        </a>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>

            <!-- Past/Archive Tournaments Section -->
            <section id="past-tournaments" class="space-y-4">
                <h3 class="text-xl font-bold text-slate-800 dark:text-slate-100">🏆 Turnamen Selesai (Arsip)</h3>

                @if($pastTournaments->isEmpty())
                    <p class="text-xs font-semibold text-slate-400 dark:text-slate-500 italic">Belum ada turnamen terdahulu yang diselesaikan.</p>
                @else
                    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800/80 rounded-2xl divide-y divide-slate-100 dark:divide-slate-800 shadow-xs overflow-hidden">
                        @foreach($pastTournaments as $t)
                            <div class="p-4 sm:p-5 flex items-center justify-between gap-4">
                                <div class="space-y-1">
                                    <div class="flex items-center space-x-2">
                                        <span class="px-2 py-0.5 rounded-full text-[9px] font-bold uppercase tracking-wider bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400">
                                            Selesai
                                        </span>
                                        <span class="text-[10px] font-semibold text-slate-400">
                                            @if($t->type === 'clash_squad')
                                                Clash Squad 4v4
                                            @else
                                                Battle Royale ({{ ucfirst($t->team_mode ?? 'squad') }})
                                            @endif
                                        </span>
                                    </div>
                                    <h4 class="font-bold text-slate-800 dark:text-slate-200 text-sm">
                                        {{ $t->name }}
                                    </h4>
                                </div>
                                <a href="{{ route('tournaments.show', $t->id) }}" class="border border-slate-200 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-400 px-3 py-1.5 rounded-lg text-xs font-bold transition">
                                    Lihat Hasil
                                </a>
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>
        </div>

        <!-- Right Side: Global Leaderboard (Hall of Fame) -->
        <div class="space-y-8">
            <section id="global-leaderboard" class="space-y-4">
                <h3 class="text-xl font-bold text-slate-800 dark:text-slate-100 flex items-center space-x-2">
                    <span>👑 Papan Peringkat Global</span>
                </h3>

                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800/80 rounded-2xl p-5 shadow-xs relative overflow-hidden">
                    <div class="absolute inset-x-0 top-0 h-1.5 bg-gradient-to-r from-yellow-400 to-amber-500"></div>
                    
                    <div class="text-center pb-4 mb-4 border-b border-slate-100 dark:border-slate-800/80">
                        <span class="text-xs font-bold text-amber-500 uppercase tracking-widest">Hall of Fame</span>
                        <h4 class="font-extrabold text-slate-800 dark:text-slate-100 text-base mt-0.5">Top 10 Player Terbaik</h4>
                        <p class="text-[10px] text-slate-400 dark:text-slate-500">Poin keaktifan & kemenangan turnamen</p>
                    </div>

                    @if($leaderboard->isEmpty())
                        <div class="text-center py-6">
                            <span class="text-3xl">⚔️</span>
                            <p class="text-xs text-slate-400 dark:text-slate-500 mt-2 font-medium">Belum ada poin dibagikan. Jadilah yang pertama!</p>
                        </div>
                    @else
                        <div class="space-y-3.5">
                            @foreach($leaderboard as $index => $record)
                                <div class="flex items-center justify-between p-2.5 rounded-xl hover:bg-slate-50 dark:hover:bg-slate-800/30 transition duration-150">
                                    <div class="flex items-center space-x-3">
                                        <!-- Rank Badge -->
                                        @if($index === 0)
                                            <div class="w-6 h-6 rounded-full bg-yellow-400 flex items-center justify-center text-slate-900 font-extrabold text-xs shadow-md shadow-yellow-400/20">
                                                🥇
                                            </div>
                                        @elseif($index === 1)
                                            <div class="w-6 h-6 rounded-full bg-slate-350 flex items-center justify-center text-slate-900 font-extrabold text-xs shadow-md shadow-slate-300/20">
                                                🥈
                                            </div>
                                        @elseif($index === 2)
                                            <div class="w-6 h-6 rounded-full bg-amber-600 flex items-center justify-center text-white font-extrabold text-xs shadow-md shadow-amber-600/20">
                                                🥉
                                            </div>
                                        @else
                                            <div class="w-6 h-6 rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-slate-500 dark:text-slate-400 font-bold text-xs">
                                                {{ $index + 1 }}
                                            </div>
                                        @endif
                                        
                                        <div class="overflow-hidden">
                                            <h5 class="font-bold text-slate-800 dark:text-slate-200 text-sm truncate max-w-[120px]">
                                                {{ $record->user ? $record->user->name : 'Unknown Player' }}
                                            </h5>
                                            <p class="text-[9px] text-slate-400 dark:text-slate-500 font-bold uppercase tracking-wider">
                                                {{ $record->user ? ucfirst($record->user->role) : 'Guest' }}
                                            </p>
                                        </div>
                                    </div>
                                    
                                    <div class="text-right">
                                        <span class="bg-amber-50 dark:bg-amber-950/20 border border-amber-250/20 text-amber-600 dark:text-amber-400 text-xs font-black px-2.5 py-1 rounded-lg">
                                            {{ $record->total_points }} Pts
                                        </span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </section>
        </div>

    </div>
</div>
@endsection
