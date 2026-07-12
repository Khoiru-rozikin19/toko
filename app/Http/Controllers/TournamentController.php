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

        return view('tournaments.show', [
            'title' => $tournament->name,
            'tournament' => $tournament,
            'approvedTeams' => $approvedTeams,
        ]);
    }
}
