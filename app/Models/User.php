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

    /**
     * Get unique website ID for profile and registration.
     * Returns a unique, obfuscated 8-digit string.
     */
    public function getWebsiteId()
    {
        $m = 100000000;
        $p = 38249873;
        $c = 12345678;
        
        $encoded = ($this->id * $p + $c) % $m;
        return str_pad($encoded, 8, '0', STR_PAD_LEFT);
    }

    /**
     * Find user by website ID format (obfuscated 8-digit number).
     */
    public static function findByWebsiteId($websiteId)
    {
        // Hapus prefix RZK- jika pengguna secara tidak sengaja memasukkannya
        $clean = str_replace('RZK-', '', $websiteId);
        $clean = trim($clean);

        if (!preg_match('/^\d{8}$/', $clean)) {
            // Fallback jika berupa ID database biasa (untuk kompatibilitas cadangan)
            if (is_numeric($clean)) {
                return self::find((int) $clean);
            }
            return null;
        }

        $m = 100000000;
        $inv = 54253937;
        $c = 12345678;

        $val = intval($clean, 10);
        $val = $val - $c;
        
        // Tangani modulo negatif di PHP
        $val = ($val % $m + $m) % $m;
        
        $userId = ($val * $inv) % $m;
        return self::find($userId);
    }
}
