<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use MoneyTransfer\InlineEdit\Traits\HasInlineEdit;

class Client extends Model
{
    use HasFactory;
    use HasInlineEdit;
    
    protected $inlineEditableFields = [
        'name',
        'email',
        'phone',
        'address',
    ];

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
