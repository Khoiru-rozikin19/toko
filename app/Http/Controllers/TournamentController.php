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
            ->withCount(['registrations as approved_registrations_count' => function ($q) {
                $q->where('status', 'approved');
            }])
            ->orderBy('start_date', 'asc')
            ->get();

        // 2. Get past tournaments (status: completed)
        $pastTournaments = Tournament::where('status', 'completed')
            ->withCount(['registrations as approved_registrations_count' => function ($q) {
                $q->where('status', 'approved');
            }])
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
            ->orderBy('id', 'asc')
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

        $isSoloBR = ($type === 'battle_royale' && $teamMode === 'solo');

        // Validate basic inputs
        $rules = [
            'team_name' => ($isSoloBR ? 'nullable' : 'required') . '|string|max:255',
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

        // Fallback team name for Solo BR
        $teamName = $request->team_name ?: $request->player_nickname[0];

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
            ->where('team_name', $teamName)
            ->exists();

        if ($isTeamNameTaken) {
            return back()->withErrors(['message' => $isSoloBR 
                ? 'Nickname game "' . $teamName . '" sudah terdaftar dalam turnamen ini.' 
                : 'Nama tim "' . $teamName . '" sudah digunakan. Silakan pilih nama lain.'
            ]);
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
            $registration = DB::transaction(function() use ($tournament, $request, $user, $memberUsers, $teamName, $expectedMembersCount) {
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
                        'team_name' => $teamName,
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
                        'team_name' => $teamName,
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

        $successMsg = $isSoloBR 
            ? 'Pendaftaran Anda berhasil diajukan! Menunggu persetujuan Admin.' 
            : 'Pendaftaran tim "' . $teamName . '" berhasil diajukan! Menunggu persetujuan Admin.';

        return back()->with('success', $successMsg);
    }

    /**
     * Update room credentials for a match (Captains only).
     */
    public function updateRoomCredentials(Request $request, $id)
    {
        $request->validate([
            'room_id' => 'required|string|max:100',
            'room_password' => 'required|string|max:100',
        ]);

        $match = \App\Models\TournamentMatch::findOrFail($id);

        // Pastikan user adalah kapten dari salah satu tim
        $userId = auth()->id();
        $isCaptain1 = $match->team1 && $match->team1->captain_id === $userId;
        $isCaptain2 = $match->team2 && $match->team2->captain_id === $userId;

        if (!$isCaptain1 && !$isCaptain2) {
            return back()->with('error', 'Hanya Kapten Tim yang dapat menginput Room ID & Password.');
        }

        $match->update([
            'room_id' => $request->room_id,
            'room_password' => $request->room_password,
        ]);

        // Kirim WhatsApp jika grup terhubung
        $tournament = $match->tournament;
        if ($tournament->whatsapp_group_jid) {
            try {
                $team1Name = $match->team1 ? $match->team1->team_name : 'TBD';
                $team2Name = $match->team2 ? $match->team2->team_name : 'TBD';
                $message = "📢 *INFO KREDENSIAL ROOM FF (Match {$match->match_number})*\n\n"
                         . "*Tim*: {$team1Name} vs {$team2Name}\n"
                         . "*Room ID*: {$request->room_id}\n"
                         . "*Password*: {$request->room_password}\n\n"
                         . "Silakan kedua tim segera masuk dan memulai pertandingan!";
                
                $waService = app(\App\Services\WhatsappService::class);
                if ($waService->isEnabled()) {
                    $waService->sendGenericMessage($tournament->whatsapp_group_jid, $message);
                }
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error("Failed to send WhatsApp room update: " . $e->getMessage());
            }
        }

        return back()->with('success', 'Kredensial Room berhasil dibagikan!');
    }

    /**
     * Report score and upload screenshots for a match (Captains only).
     */
    public function reportMatchScore(Request $request, $id)
    {
        $request->validate([
            'reported_winner_id' => 'required|integer|exists:tournament_registrations,id',
            'team1_score' => 'required|integer|min:0',
            'team2_score' => 'required|integer|min:0',
            'screenshot_1' => 'required|file|image|max:5120',
            'screenshot_2' => 'nullable|file|image|max:5120',
            'screenshot_3' => 'nullable|file|image|max:5120',
        ]);

        $match = \App\Models\TournamentMatch::findOrFail($id);

        // Pastikan user adalah kapten dari salah satu tim
        $userId = auth()->id();
        $isCaptain1 = $match->team1 && $match->team1->captain_id === $userId;
        $isCaptain2 = $match->team2 && $match->team2->captain_id === $userId;

        if (!$isCaptain1 && !$isCaptain2) {
            return back()->with('error', 'Hanya Kapten Tim yang dapat melaporkan hasil pertandingan.');
        }

        // Upload files
        $paths = [];
        if (!file_exists(public_path('uploads/screenshots'))) {
            mkdir(public_path('uploads/screenshots'), 0777, true);
        }

        // Tentukan prefix kolom (t1_ atau t2_)
        $prefix = $isCaptain1 ? 't1_' : 't2_';

        foreach (['screenshot_1', 'screenshot_2', 'screenshot_3'] as $key) {
            if ($request->hasFile($key)) {
                $file = $request->file($key);
                $fileName = 'match_' . $id . '_' . $prefix . $key . '_' . time() . '.' . $file->getClientOriginalExtension();
                $file->move(public_path('uploads/screenshots'), $fileName);
                $paths[$prefix . $key] = '/uploads/screenshots/' . $fileName;
            }
        }

        $updateData = [
            $prefix . 'reported_winner_id' => $request->reported_winner_id,
            $prefix . 'team1_score' => $request->team1_score,
            $prefix . 'team2_score' => $request->team2_score,
        ];

        $match->update(array_merge($updateData, $paths));

        // Kirim WhatsApp jika grup terhubung
        $tournament = $match->tournament;
        if ($tournament->whatsapp_group_jid) {
            try {
                $team1Name = $match->team1 ? $match->team1->team_name : 'TBD';
                $team2Name = $match->team2 ? $match->team2->team_name : 'TBD';
                $reporterTeam = $isCaptain1 ? $team1Name : $team2Name;
                $message = "📢 *LAPORAN HASIL PERTANDINGAN (Match {$match->match_number})*\n\n"
                         . "*Tim*: {$team1Name} vs {$team2Name}\n"
                         . "*Skor Dilaporkan*: {$request->team1_score} - {$request->team2_score}\n"
                         . "*Dilaporkan oleh*: Kapten {$reporterTeam}\n\n"
                         . "Bukti hasil pertandingan (screenshot Bo3) telah berhasil diunggah ke website. Menunggu persetujuan & verifikasi dari Admin!";
                
                $waService = app(\App\Services\WhatsappService::class);
                if ($waService->isEnabled()) {
                    $waService->sendGenericMessage($tournament->whatsapp_group_jid, $message);
                }
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error("Failed to send WhatsApp score report update: " . $e->getMessage());
            }
        }

        return back()->with('success', 'Laporan skor dan screenshot berhasil dikirim!');
    }
}
