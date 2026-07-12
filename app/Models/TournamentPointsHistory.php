<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TournamentPointsHistory extends Model
{
    use HasFactory;

    protected $table = 'tournament_points_history';

    protected $fillable = [
        'user_id',
        'tournament_id',
        'points',
        'reason',
    ];

    /**
     * Get the user who received/lost points.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the tournament related to these points, if any.
     */
    public function tournament()
    {
        return $this->belongsTo(Tournament::class);
    }
}
