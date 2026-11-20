<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tariff extends Model
{
    protected $fillable = ['type', 'min_amount', 'max_amount', 'fee_amount'];

    protected $casts = [
        'min_amount' => 'float',
        'max_amount' => 'float',
        'fee_amount' => 'float',
    ];
}
