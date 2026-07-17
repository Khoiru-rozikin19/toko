<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TournamentMatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'tournament_id',
        'round_number',
        'match_number',
        'team1_id',
        'team2_id',
        'team1_score',
        'team2_score',
        'winner_id',
        'status', // pending, ongoing, completed
        'scheduled_time',
        'room_id',
        'room_password',
        'reported_winner_id',
        'screenshot_1',
        'screenshot_2',
        'screenshot_3',
    ];

    protected $casts = [
        'scheduled_time' => 'datetime',
    ];

    /**
     * Get the tournament this match belongs to.
     */
    public function tournament()
    {
        return $this->belongsTo(Tournament::class);
    }

    /**
     * Get Team 1 in this match.
     */
    public function team1()
    {
        return $this->belongsTo(TournamentRegistration::class, 'team1_id');
    }

    /**
     * Get Team 2 in this match.
     */
    public function team2()
    {
        return $this->belongsTo(TournamentRegistration::class, 'team2_id');
    }

    /**
     * Get the winning team in this match.
     */
    public function winner()
    {
        return $this->belongsTo(TournamentRegistration::class, 'winner_id');
    }
}
