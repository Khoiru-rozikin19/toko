<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VpsServer extends Model
{
    protected $fillable = [
        'name',
        'ip_address',
        'ssh_port',
        'ssh_username',
        'ssh_password',
        'ssh_private_key',
    ];

    public function products()
    {
        return $this->hasMany(Product::class);
    }
}
