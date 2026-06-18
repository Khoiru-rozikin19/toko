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
        'access_token',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
    ];
}
