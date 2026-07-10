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
        'user_id',
        'product_id',
        'email_or_whatsapp',
        'target_phone',
        'base_amount',
        'unique_code',
        'total_amount',
        'status',
        'payment_method',
        'qris_payload',
        'vpn_config',
        'sn',
        'commission_earned',
        'telegram_message_id',
        'expired_at',
        'escrow_status',
        'escrow_amount',
        'escrow_released_at',
        'paid_at',
    ];

    protected $casts = [
        'expired_at' => 'datetime',
        'commission_earned' => 'decimal:2',
        'escrow_released_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function complaints()
    {
        return $this->hasMany(Complaint::class);
    }

    public function isExpired(): bool
    {
        return in_array($this->status, ['pending', 'pending_manual']) && $this->expired_at && $this->expired_at->isPast();
    }

    public function processEscrowAndNotification()
    {
        $this->paid_at = now();
        $product = $this->product;
        
        if ($product && $product->user_id && $product->seller && $product->seller->role === 'seller') {
            // Seller product
            $escrowAmount = $this->total_amount - ($product->harga_modal ?? 0);
            $this->escrow_status = 'held';
            $this->escrow_amount = $escrowAmount;
            $this->save();
            
            // Increment seller held balance
            $sellerBalance = \App\Models\UserBalance::firstOrCreate(
                ['user_id' => $product->user_id],
                ['balance' => 0, 'held_balance' => 0]
            );
            $sellerBalance->increment('held_balance', $escrowAmount);
            
            // Send seller Telegram notification
            $seller = $product->seller;
            if ($seller && !empty($seller->telegram_chat_id)) {
                $telegramService = app(\App\Services\TelegramService::class);
                $telegramService->sendSellerOrderNotification(
                    $this->id,
                    $this->total_amount,
                    $this->email_or_whatsapp,
                    $seller->telegram_chat_id
                );
            }
        } else {
            // Admin/System product
            $this->escrow_status = 'none';
            $this->save();
        }
    }
}
