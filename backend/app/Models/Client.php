<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'name',
        'email',
        'phone',
        'address',
        'country_id',
    ];

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }
}
