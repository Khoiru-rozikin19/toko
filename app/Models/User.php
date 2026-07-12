<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'phone', 'telegram_chat_id', 'role', 'is_verified', 'seller_request'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_verified' => 'boolean',
        ];
    }

    /**
     * Get the user's balance record.
     */
    public function balance()
    {
        return $this->hasOne(UserBalance::class);
    }

    /**
     * Get the user's balance transactions.
     */
    public function balanceTransactions()
    {
        return $this->hasMany(BalanceTransaction::class);
    }

    /**
     * Get the user's current balance amount (creates record if not exists).
     */
    public function getBalance(): float
    {
        return (float) $this->getOrCreateBalance()->balance;
    }

    /**
     * Get or create the user's balance record.
     */
    public function getOrCreateBalance(): UserBalance
    {
        $balance = $this->balance()->firstOrCreate([], ['balance' => 0]);
        $this->setRelation('balance', $balance);
        return $balance;
    }

    /**
     * Get the teams registered by this user as captain.
     */
    public function tournamentRegistrations()
    {
        return $this->hasMany(TournamentRegistration::class, 'captain_id');
    }

    /**
     * Get the user's participations as a team member/captain.
     */
    public function tournamentParticipations()
    {
        return $this->hasMany(TournamentParticipant::class);
    }

    /**
     * Get the user's tournament points history.
     */
    public function tournamentPoints()
    {
        return $this->hasMany(TournamentPointsHistory::class);
    }

    /**
     * Helper to get total tournament points for leaderboard.
     */
    public function totalTournamentPoints(): int
    {
        return (int) $this->tournamentPoints()->sum('points');
    }
}
