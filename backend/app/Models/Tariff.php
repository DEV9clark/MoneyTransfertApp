<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tariff extends Model
{
    protected $fillable = ['type', 'min_amount', 'max_amount', 'fee_amount'];
}
