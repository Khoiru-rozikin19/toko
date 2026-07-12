<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TournamentParticipant extends Model
{
    use HasFactory;

    protected $fillable = [
        'registration_id',
        'user_id',
        'nickname',
        'game_id',
        'role', // captain, member
    ];

    /**
     * Get the team registration this participant belongs to.
     */
    public function registration()
    {
        return $this->belongsTo(TournamentRegistration::class, 'registration_id');
    }

    /**
     * Get the website user associated with this participant.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
