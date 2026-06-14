<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'raw_text',
        'amount',
        'matched_order_id',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class, 'matched_order_id');
    }
}
