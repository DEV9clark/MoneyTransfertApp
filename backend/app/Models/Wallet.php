<?php

namespace App\Models;

use App\Exceptions\TransferException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Wallet extends Model
{
    use HasFactory;

    public const TYPE_AGENT = 'agent';
    public const TYPE_REVENUE = 'revenue';

    protected $fillable = [
        'uuid',
        'user_id',
        'type',
        'currency',
        'balance',
    ];

    protected $casts = [
        'balance' => 'float',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function sentTransactions()
    {
        return $this->hasMany(Transaction::class, 'sender_wallet_id');
    }

    public function paidTransactions()
    {
        return $this->hasMany(Transaction::class, 'payout_wallet_id');
    }

    public function ledgers()
    {
        return $this->hasMany(Ledger::class);
    }

    /**
     * Compte de commissions de la société pour une devise (créé à la volée,
     * rattaché au premier administrateur).
     */
    public static function revenue(string $currency): self
    {
        $wallet = static::where('type', self::TYPE_REVENUE)->where('currency', $currency)->first();
        if ($wallet) {
            return $wallet;
        }

        $admin = User::where('role', 'admin')->orderBy('id')->first();
        if (!$admin) {
            throw new TransferException("Aucun administrateur : impossible de créer le compte de commissions.", 500);
        }

        return static::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $admin->id,
            'type' => self::TYPE_REVENUE,
            'currency' => $currency,
            'balance' => 0,
        ]);
    }

    public function scopeVisibleTo($query, $user = null)
    {
        // Lighthouse (GraphQL) passe ses arguments en tableau : on retombe alors sur l'utilisateur connecté.
        $user = $user instanceof User ? $user : auth()->user();
        if (!$user) {
            return $query->whereRaw('1 = 0');
        }

        return $user->isAdmin() ? $query : $query->where('user_id', $user->id)->where('type', self::TYPE_AGENT);
    }
}
