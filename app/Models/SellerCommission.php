<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SellerCommission extends Model
{
    use HasFactory;

    protected $fillable = [
        'seller_id',
        'product_id',
        'commission_amount',
        'is_active',
    ];

    protected $casts = [
        'commission_amount' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /**
     * The seller (user) who receives the commission.
     */
    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    /**
     * The product that triggers the commission.
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Scope: only active commissions.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Process commission for a completed order.
     * Finds all active commission rules for the order's product and credits each seller.
     */
    public static function processForOrder(Order $order): void
    {
        if (!$order->product_id) {
            return;
        }

        $commissions = static::where('product_id', $order->product_id)
            ->where('seller_id', $order->user_id)
            ->where('is_active', true)
            ->with('seller')
            ->get();

        foreach ($commissions as $commission) {
            $seller = $commission->seller;
            if (!$seller) {
                continue;
            }

            // Update commission earned in this order
            $order->update([
                'commission_earned' => $commission->commission_amount
            ]);
        }
    }
}
