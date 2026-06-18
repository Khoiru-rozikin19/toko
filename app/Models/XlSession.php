<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class XlSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'msisdn',
        'label',
        'subscriber_id',
        'subscription_type',
        'access_token',
        'id_token',
        'refresh_token',
        'is_active',
        'payload',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'payload' => 'array',
    ];
}
