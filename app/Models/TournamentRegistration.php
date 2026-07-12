<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TournamentRegistration extends Model
{
    use HasFactory;

    protected $fillable = [
        'tournament_id',
        'team_name',
        'captain_id',
        'status', // pending, approved, rejected
        'rejection_reason',
        'telegram_message_id',
    ];

    /**
     * Get the tournament this registration belongs to.
     */
    public function tournament()
    {
        return $this->belongsTo(Tournament::class);
    }

    /**
     * Get the user who registered as the captain.
     */
    public function captain()
    {
        return $this->belongsTo(User::class, 'captain_id');
    }

    /**
     * Get all participants (players) in this registered team.
     */
    public function participants()
    {
        return $this->hasMany(TournamentParticipant::class, 'registration_id');
    }

    /**
     * Get matches where this team is Team 1.
     */
    public function team1Matches()
    {
        return $this->hasMany(TournamentMatch::class, 'team1_id');
    }

    /**
     * Get matches where this team is Team 2.
     */
    public function team2Matches()
    {
        return $this->hasMany(TournamentMatch::class, 'team2_id');
    }

    /**
     * Get matches won by this team.
     */
    public function wonMatches()
    {
        return $this->hasMany(TournamentMatch::class, 'winner_id');
    }
}
