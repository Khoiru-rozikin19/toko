<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
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
     * Get dynamic stock count for local products,
     * or column stock for supplier products.
     *
     * @return int
     */
    public function getStockAttribute()
    {
        if (empty($this->orderkuota_product_code)) {
            if ($this->stocks()->exists()) {
                return $this->stocks()->where('status', 'ready')->count();
            }
            return $this->attributes['stock'] ?? 0;
        }
        return $this->attributes['stock'] ?? 0;
    }
}
