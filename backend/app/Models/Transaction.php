<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'sender_wallet_id',
        'client_id', // Replaced receiver_wallet_id
        'payout_wallet_id', // Added
        'amount',
        'currency',
        'status',
        'type',
        'reference',
        'fee_amount',
        'total_amount',
        'destination',
        'recipient_name',
        'recipient_phone',
    ];

    public function senderWallet()
    {
        return $this->belongsTo(Wallet::class, 'sender_wallet_id');
    }

    public function payoutWallet()
    {
        return $this->belongsTo(Wallet::class, 'payout_wallet_id');
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function ledgers()
    {
        return $this->hasMany(Ledger::class);
    }
}
