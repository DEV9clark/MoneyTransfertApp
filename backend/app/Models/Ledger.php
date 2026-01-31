<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ledger extends Model
{
    use HasFactory;

    protected $fillable = [
        'transaction_id',
        'wallet_id',
        'amount',
        'balance_after',
        'type',
    ];

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    public function wallet()
    {
        return $this->belongsTo(Wallet::class);
    }
}
