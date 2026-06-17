<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'phone', 'role', 'is_verified', 'seller_request'])]
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
}
