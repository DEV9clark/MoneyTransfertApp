<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Wallet extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'user_id',
        'currency',
        'balance',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function sentTransactions()
    {
        return $this->hasMany(Transaction::class, 'sender_wallet_id');
    }

    public function receivedTransactions()
    {
        return $this->hasMany(Transaction::class, 'receiver_wallet_id');
    }

    public function ledgers()
    {
        return $this->hasMany(Ledger::class);
    }
}
