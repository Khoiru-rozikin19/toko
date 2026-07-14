<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tournament extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'type', // clash_squad, battle_royale
        'team_mode', // solo, duo, squad
        'status', // draft, registration, ongoing, completed
        'registration_fee',
        'prize_pool',
        'max_slots',
        'start_date',
    ];

    protected $casts = [
        'registration_fee' => 'decimal:2',
        'start_date' => 'datetime',
    ];

    /**
     * Get all registrations (teams) for this tournament.
     */
    public function registrations()
    {
        return $this->hasMany(TournamentRegistration::class);
    }

    /**
     * Get all matches for this tournament.
     */
    public function matches()
    {
        return $this->hasMany(TournamentMatch::class);
    }

    /**
     * Get all points histories associated with this tournament.
     */
    public function pointsHistories()
    {
        return $this->hasMany(TournamentPointsHistory::class);
    }
}
