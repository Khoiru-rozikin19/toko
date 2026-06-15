<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'price',
        'duration_days',
        'config_template',
        'stock',
        'orderkuota_product_code',
    ];

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function stocks()
    {
        return $this->hasMany(AccountStock::class);
    }

    /**
     * Get dynamic stock count if account stocks exist,
     * otherwise use static column stock.
     *
     * @return int
     */
    public function getStockAttribute()
    {
        return $this->cekStok();
    }

    /**
     * Get flexible stock count:
     * - If account configurations exist, use dynamic stock from ready account data count.
     * - Otherwise, use manual stock from the static column.
     *
     * @return int
     */
    public function cekStok()
    {
        if ($this->stocks()->exists()) {
            return $this->stocks()->where('status', 'ready')->count();
        }
        return $this->attributes['stock'] ?? 0;
    }
}
