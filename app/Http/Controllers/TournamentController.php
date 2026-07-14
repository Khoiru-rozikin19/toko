<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Tournament;
use App\Models\TournamentPointsHistory;
use Illuminate\Support\Facades\DB;

class TournamentController extends Controller
{
    /**
     * Display a listing of active and completed tournaments, as well as the global leaderboard.
     */
    public function index()
    {
        // 1. Get active tournaments (status: registration, ongoing)
        $activeTournaments = Tournament::whereIn('status', ['registration', 'ongoing'])
            ->orderBy('start_date', 'asc')
            ->get();

        // 2. Get past tournaments (status: completed)
        $pastTournaments = Tournament::where('status', 'completed')
            ->orderBy('updated_at', 'desc')
            ->limit(5)
            ->get();

        // 3. Calculate Global Leaderboard (Top 10 users by sum of tournament points)
        $leaderboard = TournamentPointsHistory::select('user_id', DB::raw('SUM(points) as total_points'))
            ->groupBy('user_id')
            ->orderByDesc('total_points')
            ->with('user')
            ->limit(10)
            ->get();

        return view('tournaments.index', [
            'title' => 'Turnamen Event',
            'activeTournaments' => $activeTournaments,
            'pastTournaments' => $pastTournaments,
            'leaderboard' => $leaderboard,
        ]);
    }

    /**
     * Display details of a specific tournament, including registered teams.
     */
    public function show($id)
    {
        $tournament = Tournament::findOrFail($id);

        // Get approved registrations (teams)
        $approvedTeams = $tournament->registrations()
            ->where('status', 'approved')
            ->with(['captain', 'participants.user'])
            ->get();

        // Get matches if ongoing or completed
        $matches = collect();
        if (in_array($tournament->status, ['ongoing', 'completed'])) {
            $matches = \App\Models\TournamentMatch::where('tournament_id', $tournament->id)
                ->with(['team1', 'team2'])
                ->orderBy('round_number', 'asc')
                ->orderBy('match_number', 'asc')
                ->get();
        }

        return view('tournaments.show', [
            'title' => $tournament->name,
            'tournament' => $tournament,
            'approvedTeams' => $approvedTeams,
            'matches' => $matches,
        ]);
    }

    /**
     * Handle tournament registration form submission.
     */
    public function register(Request $request, $id)
    {
        $tournament = Tournament::findOrFail($id);

        // Ensure tournament is open for registration
        if ($tournament->status !== 'registration') {
            return back()->withErrors(['message' => 'Pendaftaran untuk turnamen ini sudah ditutup.']);
        }

        $type = $tournament->type;
        $teamMode = $tournament->team_mode;
        
        $expectedPlayerCount = 4; // default squad
        if ($type === 'battle_royale') {
            if ($teamMode === 'solo') {
                $expectedPlayerCount = 1;
            } elseif ($teamMode === 'duo') {
                $expectedPlayerCount = 2;
            }
        }
        
        $expectedMembersCount = $expectedPlayerCount - 1;

        // Validate basic inputs
        $rules = [
            'team_name' => 'required|string|max:255',
            'player_nickname' => 'required|array|size:' . $expectedPlayerCount,
            'player_nickname.*' => 'required|string|max:255',
            'player_game_id' => 'required|array|size:' . $expectedPlayerCount,
            'player_game_id.*' => 'required|string|max:255',
        ];

        if ($expectedMembersCount > 0) {
            $rules['player_website_id'] = 'required|array|size:' . $expectedMembersCount;
            $rules['player_website_id.*'] = 'required|string|max:255';
        }

        $request->validate($rules);

        $user = auth()->user();

        // 1. Check if Captain (current logged-in user) is already registered in this tournament
        $isRegisteredAsCaptain = \App\Models\TournamentRegistration::where('tournament_id', $tournament->id)
            ->where('captain_id', $user->id)
            ->exists();

        $isRegisteredAsParticipant = \App\Models\TournamentParticipant::whereHas('registration', function($q) use($tournament) {
                $q->where('tournament_id', $tournament->id);
            })
            ->where('user_id', $user->id)
            ->exists();

        if ($isRegisteredAsCaptain || $isRegisteredAsParticipant) {
            return back()->withErrors(['message' => 'Anda sudah terdaftar dalam turnamen ini (sebagai kapten atau anggota tim lain).']);
        }

        // 2. Check if Team Name is already taken in this tournament
        $isTeamNameTaken = \App\Models\TournamentRegistration::where('tournament_id', $tournament->id)
            ->where('team_name', $request->team_name)
            ->exists();

        if ($isTeamNameTaken) {
            return back()->withErrors(['message' => 'Nama tim "' . $request->team_name . '" sudah digunakan. Silakan pilih nama lain.']);
        }

        // 3. Check Slot Limit
        $approvedCount = $tournament->registrations()->where('status', 'approved')->count();
        if ($tournament->max_slots && $approvedCount >= $tournament->max_slots) {
            return back()->withErrors(['message' => 'Pendaftaran gagal. Slot turnamen sudah penuh!']);
        }

        // 4. Validate and Find Website Users for Player 2, 3, etc.
        $memberUsers = [];
        for ($i = 0; $i < $expectedMembersCount; $i++) {
            $playerNum = $i + 2;
            $identifier = trim($request->player_website_id[$i]);

            // Prevent self-registration as member
            if (strtolower($identifier) === strtolower($user->getWebsiteId())) {
                return back()->withErrors(['message' => "Player {$playerNum} tidak boleh berupa akun Anda sendiri (Anda sudah terdaftar otomatis sebagai Kapten)."]);
            }

            $memberUser = \App\Models\User::findByWebsiteId($identifier);

            if (!$memberUser) {
                return back()->withErrors(['message' => "Player {$playerNum} (ID Akun Website: '{$identifier}') tidak terdaftar di website ini. Silakan periksa kembali!"]);
            }

            // Check if member is already registered in another team in this tournament
            $isMemberRegistered = \App\Models\TournamentParticipant::whereHas('registration', function($q) use($tournament) {
                    $q->where('tournament_id', $tournament->id);
                })
                ->where('user_id', $memberUser->id)
                ->exists();

            if ($isMemberRegistered) {
                return back()->withErrors(['message' => "Player {$playerNum} ({$memberUser->name}) sudah terdaftar di tim lain dalam turnamen ini."]);
            }

            // Prevent duplicate members within the same registration input
            if (in_array($memberUser->id, array_column($memberUsers, 'id'))) {
                return back()->withErrors(['message' => "Player {$playerNum} ({$memberUser->name}) diinput lebih dari sekali di form pendaftaran."]);
            }

            $memberUsers[$playerNum] = $memberUser;
        }

        // 5. Balance transaction (wrap inside DB transaction for safety)
        try {
            $registration = DB::transaction(function() use ($tournament, $request, $user, $memberUsers) {
                $fee = (float) $tournament->registration_fee;
                
                if ($fee > 0) {
                    $balanceRecord = \App\Models\UserBalance::where('user_id', $user->id)
                        ->lockForUpdate()
                        ->firstOrCreate(['user_id' => $user->id], ['balance' => 0]);

                    if ($balanceRecord->balance < $fee) {
                        throw new \Exception('Saldo tidak cukup. Saldo Anda: Rp ' . number_format($balanceRecord->balance, 0, ',', '.') . ', Biaya: Rp ' . number_format($fee, 0, ',', '.') . '. Silakan Top Up terlebih dahulu.');
                    }

                    // Deduct Balance
                    $balanceBefore = $balanceRecord->balance;
                    $balanceRecord->decrement('balance', $fee);
                    $balanceRecord->refresh();

                    // Create registration record
                    $reg = \App\Models\TournamentRegistration::create([
                        'tournament_id' => $tournament->id,
                        'team_name' => $request->team_name,
                        'captain_id' => $user->id,
                        'status' => 'pending',
                    ]);

                    // Create balance transaction
                    \App\Models\BalanceTransaction::create([
                        'user_id' => $user->id,
                        'type' => 'purchase',
                        'amount' => $fee,
                        'balance_before' => $balanceBefore,
                        'balance_after' => $balanceRecord->balance,
                        'description' => 'Pendaftaran Turnamen: ' . $tournament->name,
                        'reference_id' => $reg->id,
                        'status' => 'success',
                    ]);
                } else {
                    // Free tournament
                    $reg = \App\Models\TournamentRegistration::create([
                        'tournament_id' => $tournament->id,
                        'team_name' => $request->team_name,
                        'captain_id' => $user->id,
                        'status' => 'pending',
                    ]);
                }

                // Save participants
                // Player 1 (Captain)
                \App\Models\TournamentParticipant::create([
                    'registration_id' => $reg->id,
                    'user_id' => $user->id,
                    'nickname' => $request->player_nickname[0],
                    'game_id' => $request->player_game_id[0],
                    'role' => 'captain',
                ]);

                // Players 2 and onwards
                for ($i = 0; $i < $expectedMembersCount; $i++) {
                    $playerNum = $i + 2;
                    $memberUser = $memberUsers[$playerNum];

                    \App\Models\TournamentParticipant::create([
                        'registration_id' => $reg->id,
                        'user_id' => $memberUser->id,
                        'nickname' => $request->player_nickname[$i + 1],
                        'game_id' => $request->player_game_id[$i + 1],
                        'role' => 'member',
                    ]);
                }

                return $reg;
            });
        } catch (\Exception $e) {
            return back()->withErrors(['message' => $e->getMessage()]);
        }

        // Trigger Telegram Bot Admin notification (handled in Phase 5)
        try {
            $telegramService = app(\App\Services\TelegramService::class);
            if (method_exists($telegramService, 'sendTournamentNotification')) {
                $telegramService->sendTournamentNotification($registration);
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning("Telegram Notification failed: " . $e->getMessage());
        }

        return back()->with('success', 'Pendaftaran tim "' . $request->team_name . '" berhasil diajukan! Menunggu persetujuan Admin.');
    }
}
