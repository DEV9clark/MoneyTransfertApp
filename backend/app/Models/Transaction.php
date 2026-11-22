<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use MoneyTransfer\InlineEdit\Traits\HasInlineEdit;

class Transaction extends Model
{
    use HasFactory;
    use HasInlineEdit;
    
    protected $inlineEditableFields = [
        'reference',
        'status',
        'type',
        'recipient_name',
        'recipient_phone',
        'destination',
        'client.name', // Champ imbriqué
    ];

    public const STATUS_AWAITING_PAYMENT = 'awaiting_payment'; // pré-envoi client non encore payé en agence
    public const STATUS_PENDING = 'pending';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_EXPIRED = 'expired';

    public const TYPE_SEND = 'send';
    public const TYPE_TOPUP = 'topup';

    public const CHANNEL_AGENT = 'agent';
    public const CHANNEL_CLIENT_APP = 'client_app';

    protected $fillable = [
        'uuid',
        'sender_wallet_id',
        'client_id',
        'payout_wallet_id',
        'created_by',
        'paid_by',
        'cancelled_by',
        'amount',
        'currency',
        'status',
        'type',
        'reference',
        'payment_code',
        'channel',
        'funded_at',
        'withdrawal_code_hash',
        'fee_amount',
        'total_amount',
        'destination',
        'destination_country_id',
        'recipient_name',
        'recipient_phone',
        'sender_id_type',
        'sender_id_number',
        'recipient_id_type',
        'recipient_id_number',
        'sender_receipt_number',
        'payout_receipt_number',
        'expires_at',
        'paid_at',
        'cancelled_at',
        'cancel_reason',
        'notes',
    ];

    /** Le code de retrait (même haché) ne sort jamais de l'API. */
    protected $hidden = [
        'withdrawal_code_hash',
    ];

    protected $casts = [
        'amount' => 'float',
        'fee_amount' => 'float',
        'total_amount' => 'float',
        'expires_at' => 'datetime',
        'paid_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'funded_at' => 'datetime',
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

    public function destinationCountry()
    {
        return $this->belongsTo(Country::class, 'destination_country_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function payer()
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function ledgers()
    {
        return $this->hasMany(Ledger::class);
    }

    /**
     * Représentation destinée à l'application client (sans données internes).
     */
    public function toClientArray(): array
    {
        $this->loadMissing('destinationCountry');

        return [
            'uuid' => $this->uuid,
            'reference' => $this->reference,
            'payment_code' => $this->status === self::STATUS_AWAITING_PAYMENT ? $this->payment_code : null,
            'status' => $this->status,
            'amount' => $this->amount,
            'fee' => $this->fee_amount,
            'total' => $this->total_amount,
            'currency' => $this->currency,
            'recipient_name' => $this->recipient_name,
            'recipient_phone' => $this->recipient_phone,
            'destination' => optional($this->destinationCountry)->name ?? $this->destination,
            'destination_country_id' => $this->destination_country_id,
            'channel' => $this->channel,
            'created_at' => optional($this->created_at)->toIso8601String(),
            'expires_at' => optional($this->expires_at)->toIso8601String(),
            'funded_at' => optional($this->funded_at)->toIso8601String(),
            'paid_at' => optional($this->paid_at)->toIso8601String(),
            'cancelled_at' => optional($this->cancelled_at)->toIso8601String(),
            'has_deposit_receipt' => $this->sender_receipt_number !== null,
            'has_withdrawal_receipt' => $this->status === self::STATUS_COMPLETED,
        ];
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * L'agent voit les opérations qu'il a envoyées ou payées ; l'admin voit tout.
     * Utilisé par l'API REST et par GraphQL.
     */
    public function scopeVisibleTo($query, $user = null)
    {
        // Lighthouse (GraphQL) passe ses arguments en tableau : on retombe alors sur l'utilisateur connecté.
        $user = $user instanceof User ? $user : auth()->user();
        if (!$user) {
            return $query->whereRaw('1 = 0');
        }

        if (!$user->isAdmin()) {
            $walletId = optional($user->wallet)->id ?? 0;
            $query->where(function ($q) use ($walletId) {
                $q->where('sender_wallet_id', $walletId)->orWhere('payout_wallet_id', $walletId);
            });
        }

        return $query->latest();
    }

    /** L'utilisateur a-t-il le droit de consulter cette transaction (et ses reçus) ? */
    public function isVisibleTo(User $user): bool
    {
        if ($user->isAdmin()) {
            return true;
        }
        $walletId = optional($user->wallet)->id;

        return $walletId !== null
            && ((int) $this->sender_wallet_id === (int) $walletId || (int) $this->payout_wallet_id === (int) $walletId);
    }
}
