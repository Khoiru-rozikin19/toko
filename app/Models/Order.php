<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'product_id',
        'email_or_whatsapp',
        'base_amount',
        'unique_code',
        'total_amount',
        'status',
        'qris_payload',
        'vpn_config',
        'sn',
        'expired_at',
    ];

    protected $casts = [
        'expired_at' => 'datetime',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function isExpired(): bool
    {
        return $this->status === 'pending' && $this->expired_at && $this->expired_at->isPast();
    }
}
