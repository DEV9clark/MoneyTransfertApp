<?php

namespace App\Services;

use App\Exceptions\TransferException;
use App\Models\Client;
use App\Models\Country;
use App\Models\Ledger;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Support\Phone;
use App\Support\TransferCode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Logique financière des transferts.
 *
 * Règles de comptabilité (grand livre équilibré) :
 *  - Envoi     : agent expéditeur  -(montant + frais) ; compte commissions +frais ; montant "en transit"
 *  - Retrait   : agent payeur      +montant           ; le transit est soldé
 *  - Annulation/expiration : écritures inverses de l'envoi
 *  - Rechargement : agent +montant (approvisionnement externe validé par un admin)
 *
 * Tous les soldes sont modifiés sous verrou (SELECT ... FOR UPDATE) dans une
 * transaction SQL : pas de double dépense ni de double paiement en concurrence.
 */
class TransactionService
{
    protected SmsService $smsService;
    protected TariffService $tariffService;

    public function __construct(SmsService $smsService, TariffService $tariffService)
    {
        $this->smsService = $smsService;
        $this->tariffService = $tariffService;
    }

    /**
     * Envoi d'argent par un agent pour le compte d'un client.
     *
     * @return array{0: Transaction, 1: string} la transaction et le code de retrait en clair
     *                                           (à n'utiliser que pour l'envoi du SMS)
     * @throws TransferException
     */
    public function initiateTransfer(User $agent, array $data): array
    {
        $agentWallet = $agent->wallet;
        if (!$agentWallet) {
            throw new TransferException("Cet agent n'a pas de portefeuille.");
        }

        $amount = round((float) $data['amount'], 2);
        $this->checkLimits($amount);
        $fee = $this->tariffService->calculateFee($amount, Transaction::TYPE_SEND);
        $total = round($amount + $fee, 2);

        $destination = $this->resolveCountry($data['destination_country_id'] ?? null, $data['destination'] ?? null);
        if (!$destination) {
            throw new TransferException('Pays de destination inconnu.');
        }

        $code = TransferCode::generate();

        $transaction = DB::transaction(function () use ($agent, $agentWallet, $data, $amount, $fee, $total, $destination, $code) {
            /** @var Wallet $wallet */
            $wallet = Wallet::whereKey($agentWallet->id)->lockForUpdate()->first();

            if ($wallet->balance < $total) {
                throw new TransferException(
                    'Solde insuffisant. Requis : ' . $this->money($total, $wallet->currency)
                    . ', disponible : ' . $this->money($wallet->balance, $wallet->currency) . '.'
                );
            }

            $client = $this->findOrCreateClient($data);
            $this->checkClientDailyLimit($client, $amount);

            $wallet->balance = round($wallet->balance - $total, 2);
            $wallet->save();

            $transaction = Transaction::create([
                'uuid' => (string) Str::uuid(),
                'sender_wallet_id' => $wallet->id,
                'client_id' => $client->id,
                'created_by' => $agent->id,
                'amount' => $amount,
                'fee_amount' => $fee,
                'total_amount' => $total,
                'currency' => $wallet->currency,
                'destination' => $destination->name,
                'destination_country_id' => $destination->id,
                'recipient_name' => $data['recipient_name'],
                'recipient_phone' => $data['recipient_phone'],
                'sender_id_type' => $data['sender_id_type'] ?? $client->id_type,
                'sender_id_number' => $data['sender_id_number'] ?? $client->id_number,
                'status' => Transaction::STATUS_PENDING,
                'type' => Transaction::TYPE_SEND,
                'reference' => TransferCode::reference(),
                'withdrawal_code_hash' => TransferCode::hash($code),
                'expires_at' => now()->addDays((int) config('transfer.code_ttl_days', 30)),
            ]);

            $transaction->sender_receipt_number = TransferCode::receiptNumber('DEP', $transaction);
            $transaction->save();

            $this->bookSendEntries($transaction, $wallet);

            return $transaction;
        });

        AuditLogger::log('transfer.created', $transaction, ['amount' => $amount, 'fee' => $fee]);

        // SMS envoyés APRÈS validation en base : un incident réseau ne bloque ni ne fausse la transaction.
        $this->notifyCreated($transaction, $code);

        return [$transaction->fresh(['client', 'destinationCountry', 'creator']), $code];
    }

    /**
     * Vérifie un code de retrait sans rien payer (étape 1 du retrait).
     *
     * @throws TransferException
     */
    public function findPayableByCode(string $code): Transaction
    {
        $transaction = Transaction::where('withdrawal_code_hash', TransferCode::hash($code))
            ->where('type', Transaction::TYPE_SEND)
            ->with(['client', 'destinationCountry', 'creator'])
            ->first();

        $this->assertPayable($transaction);

        return $transaction;
    }

    /**
     * Paiement au bénéficiaire (étape 2 du retrait).
     *
     * @param array $identity ['recipient_id_type' => ..., 'recipient_id_number' => ...]
     * @throws TransferException
     */
    public function completeTransfer(User $payoutAgent, string $code, array $identity): Transaction
    {
        if (!$payoutAgent->wallet) {
            throw new TransferException("Cet agent n'a pas de portefeuille.");
        }

        $transaction = DB::transaction(function () use ($payoutAgent, $code, $identity) {
            /** @var Transaction|null $transaction */
            $transaction = Transaction::where('withdrawal_code_hash', TransferCode::hash($code))
                ->where('type', Transaction::TYPE_SEND)
                ->lockForUpdate()
                ->first();

            $this->assertPayable($transaction);

            $wallet = Wallet::whereKey($payoutAgent->wallet->id)->lockForUpdate()->first();

            if ($wallet->currency !== $transaction->currency) {
                throw new TransferException(
                    "Devise incompatible : ce transfert est en {$transaction->currency}, votre portefeuille en {$wallet->currency}."
                );
            }

            $wallet->balance = round($wallet->balance + $transaction->amount, 2);
            $wallet->save();

            Ledger::create([
                'transaction_id' => $transaction->id,
                'wallet_id' => $wallet->id,
                'amount' => $transaction->amount,
                'balance_after' => $wallet->balance,
                'type' => 'credit',
            ]);

            $transaction->fill([
                'status' => Transaction::STATUS_COMPLETED,
                'payout_wallet_id' => $wallet->id,
                'paid_by' => $payoutAgent->id,
                'paid_at' => now(),
                'recipient_id_type' => $identity['recipient_id_type'],
                'recipient_id_number' => $identity['recipient_id_number'],
                // Code à usage unique : on efface l'empreinte une fois payé.
                'withdrawal_code_hash' => null,
            ]);
            $transaction->payout_receipt_number = TransferCode::receiptNumber('RET', $transaction);
            $transaction->save();

            return $transaction;
        });

        AuditLogger::log('transfer.paid', $transaction, ['amount' => $transaction->amount]);
        $this->notifyPaid($transaction);

        return $transaction->fresh(['client', 'destinationCountry', 'creator', 'payer']);
    }

    /**
     * Annulation d'un envoi non retiré (ou expiration automatique) : remboursement intégral.
     *
     * @throws TransferException
     */
    public function cancel(Transaction $transaction, ?User $actor, string $reason, string $status = Transaction::STATUS_CANCELLED): Transaction
    {
        $transaction = DB::transaction(function () use ($transaction, $actor, $reason, $status) {
            $tx = Transaction::whereKey($transaction->id)->lockForUpdate()->first();

            if ($tx->type !== Transaction::TYPE_SEND || $tx->status !== Transaction::STATUS_PENDING) {
                throw new TransferException('Seul un envoi en attente peut être annulé.');
            }

            $wallet = Wallet::whereKey($tx->sender_wallet_id)->lockForUpdate()->first();
            $wallet->balance = round($wallet->balance + $tx->total_amount, 2);
            $wallet->save();

            Ledger::create([
                'transaction_id' => $tx->id,
                'wallet_id' => $wallet->id,
                'amount' => $tx->total_amount,
                'balance_after' => $wallet->balance,
                'type' => 'refund',
            ]);

            if ($tx->fee_amount > 0) {
                $revenue = Wallet::whereKey(Wallet::revenue($tx->currency)->id)->lockForUpdate()->first();
                $revenue->balance = round($revenue->balance - $tx->fee_amount, 2);
                $revenue->save();

                Ledger::create([
                    'transaction_id' => $tx->id,
                    'wallet_id' => $revenue->id,
                    'amount' => -$tx->fee_amount,
                    'balance_after' => $revenue->balance,
                    'type' => 'fee_refund',
                ]);
            }

            $tx->fill([
                'status' => $status,
                'cancelled_at' => now(),
                'cancelled_by' => optional($actor)->id,
                'cancel_reason' => $reason,
                'withdrawal_code_hash' => null,
            ])->save();

            return $tx;
        });

        AuditLogger::log('transfer.' . $status, $transaction, ['reason' => $reason], optional($actor)->id);
        $this->notifyCancelled($transaction);

        return $transaction;
    }

    /**
     * Expire les envois non retirés après le délai de validité. Lancé par le planificateur.
     */
    public function expirePending(): int
    {
        // Pré-envois jamais payés : aucun argent n'a bougé, simple expiration.
        $count = Transaction::where('status', Transaction::STATUS_AWAITING_PAYMENT)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->update(['status' => Transaction::STATUS_EXPIRED, 'updated_at' => now()]);

        Transaction::where('type', Transaction::TYPE_SEND)
            ->where('status', Transaction::STATUS_PENDING)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->orderBy('id')
            ->each(function (Transaction $tx) use (&$count) {
                try {
                    $this->cancel($tx, null, 'Délai de retrait dépassé', Transaction::STATUS_EXPIRED);
                    $count++;
                } catch (\Throwable $e) {
                    Log::error('[Transfers] échec expiration', ['id' => $tx->id, 'error' => $e->getMessage()]);
                }
            });

        return $count;
    }

    /**
     * Génère un nouveau code et le renvoie au bénéficiaire (l'ancien devient invalide).
     *
     * @throws TransferException
     */
    public function resendCode(Transaction $transaction, User $actor): Transaction
    {
        $this->assertPayable($transaction);

        $code = TransferCode::generate();
        $transaction->withdrawal_code_hash = TransferCode::hash($code);
        $transaction->save();

        AuditLogger::log('transfer.code_resent', $transaction, [], $actor->id);
        $this->sendCodeSms($transaction, $code);

        return $transaction;
    }

    /**
     * Rechargement du float d'un agent par un administrateur.
     *
     * @throws TransferException
     */
    public function topup(User $admin, User $agent, float $amount, ?string $note = null): Transaction
    {
        if (!$agent->wallet) {
            throw new TransferException("Cet agent n'a pas de portefeuille.");
        }
        $amount = round($amount, 2);

        $transaction = DB::transaction(function () use ($admin, $agent, $amount, $note) {
            $wallet = Wallet::whereKey($agent->wallet->id)->lockForUpdate()->first();
            $wallet->balance = round($wallet->balance + $amount, 2);
            $wallet->save();

            $tx = Transaction::create([
                'uuid' => (string) Str::uuid(),
                'sender_wallet_id' => null,
                'client_id' => null,
                'payout_wallet_id' => $wallet->id,
                'created_by' => $admin->id,
                'amount' => $amount,
                'fee_amount' => 0,
                'total_amount' => $amount,
                'currency' => $wallet->currency,
                'status' => Transaction::STATUS_COMPLETED,
                'type' => Transaction::TYPE_TOPUP,
                'reference' => TransferCode::reference(),
                'paid_at' => now(),
                'notes' => $note,
            ]);

            Ledger::create([
                'transaction_id' => $tx->id,
                'wallet_id' => $wallet->id,
                'amount' => $amount,
                'balance_after' => $wallet->balance,
                'type' => 'topup',
            ]);

            return $tx;
        });

        AuditLogger::log('wallet.topup', $transaction, ['agent_id' => $agent->id, 'amount' => $amount], $admin->id);

        return $transaction;
    }

    // ---------------------------------------------------------------------
    // Pré-envois de l'application client (payés ensuite en agence)
    // ---------------------------------------------------------------------

    /**
     * Le client prépare son envoi dans l'application : frais calculés et figés, aucun argent ne bouge.
     * Il présente ensuite le code de paiement à un agent, qui encaisse et valide.
     *
     * @throws TransferException
     */
    public function createPreorder(Client $client, array $data): Transaction
    {
        $amount = round((float) $data['amount'], 2);
        $this->checkLimits($amount);
        $fee = $this->tariffService->calculateFee($amount, Transaction::TYPE_SEND);

        $destination = Country::find($data['destination_country_id']);
        if (!$destination) {
            throw new TransferException('Pays de destination inconnu.');
        }

        $open = Transaction::where('client_id', $client->id)
            ->where('status', Transaction::STATUS_AWAITING_PAYMENT)
            ->count();
        $maxOpen = (int) config('transfer.max_open_preorders', 5);
        if ($open >= $maxOpen) {
            throw new TransferException("Vous avez déjà {$open} envoi(s) à payer. Payez-les ou annulez-les avant d'en créer un autre.");
        }

        $this->checkClientDailyLimit($client, $amount);

        $transaction = Transaction::create([
            'uuid' => (string) Str::uuid(),
            'client_id' => $client->id,
            'amount' => $amount,
            'fee_amount' => $fee,
            'total_amount' => round($amount + $fee, 2),
            'currency' => config('transfer.default_currency', 'XOF'),
            'destination' => $destination->name,
            'destination_country_id' => $destination->id,
            'recipient_name' => trim($data['recipient_name']),
            'recipient_phone' => Phone::normalize($data['recipient_phone'], $destination),
            'sender_id_type' => $client->id_type,
            'sender_id_number' => $client->id_number,
            'status' => Transaction::STATUS_AWAITING_PAYMENT,
            'type' => Transaction::TYPE_SEND,
            'channel' => Transaction::CHANNEL_CLIENT_APP,
            'reference' => TransferCode::reference(),
            'payment_code' => TransferCode::paymentCode(),
            'expires_at' => now()->addHours((int) config('transfer.preorder_ttl_hours', 48)),
        ]);

        AuditLogger::log('preorder.created', $transaction, ['amount' => $amount, 'fee' => $fee]);

        return $transaction->fresh(['destinationCountry']);
    }

    /**
     * Recherche d'un pré-envoi par l'agent (avant encaissement).
     *
     * @throws TransferException
     */
    public function findPreorder(string $paymentCode): Transaction
    {
        $transaction = Transaction::where('payment_code', TransferCode::normalize($paymentCode))
            ->with(['client', 'destinationCountry'])
            ->first();

        $this->assertPreorderPayable($transaction);

        return $transaction;
    }

    /**
     * L'agent a encaissé le montant + frais en espèces : son float est débité, le code de retrait
     * est généré et envoyé au bénéficiaire. Le transfert devient un envoi normal "en attente de retrait".
     *
     * @return array{0: Transaction, 1: string}
     * @throws TransferException
     */
    public function fundPreorder(User $agent, string $paymentCode, array $identity = []): array
    {
        if (!$agent->wallet) {
            throw new TransferException("Cet agent n'a pas de portefeuille.");
        }

        $code = TransferCode::generate();

        $transaction = DB::transaction(function () use ($agent, $paymentCode, $identity, $code) {
            /** @var Transaction|null $tx */
            $tx = Transaction::where('payment_code', TransferCode::normalize($paymentCode))->lockForUpdate()->first();
            $this->assertPreorderPayable($tx);

            $client = Client::find($tx->client_id);
            $this->checkClientDailyLimit($client, $tx->amount, $tx->id);

            $wallet = Wallet::whereKey($agent->wallet->id)->lockForUpdate()->first();
            if ($wallet->currency !== $tx->currency) {
                throw new TransferException("Devise incompatible : ce pré-envoi est en {$tx->currency}, votre portefeuille en {$wallet->currency}.");
            }
            if ($wallet->balance < $tx->total_amount) {
                throw new TransferException(
                    'Solde insuffisant. Requis : ' . $this->money($tx->total_amount, $wallet->currency)
                    . ', disponible : ' . $this->money($wallet->balance, $wallet->currency) . '.'
                );
            }

            $wallet->balance = round($wallet->balance - $tx->total_amount, 2);
            $wallet->save();

            // Pièce d'identité contrôlée au guichet : on complète aussi la fiche client.
            if (!empty($identity['sender_id_type']) && !empty($identity['sender_id_number'])) {
                $client->update(['id_type' => $identity['sender_id_type'], 'id_number' => $identity['sender_id_number']]);
            }

            $tx->fill([
                'sender_wallet_id' => $wallet->id,
                'created_by' => $agent->id,
                'status' => Transaction::STATUS_PENDING,
                'withdrawal_code_hash' => TransferCode::hash($code),
                'funded_at' => now(),
                'expires_at' => now()->addDays((int) config('transfer.code_ttl_days', 30)),
                'sender_id_type' => $identity['sender_id_type'] ?? $client->id_type,
                'sender_id_number' => $identity['sender_id_number'] ?? $client->id_number,
            ]);
            $tx->sender_receipt_number = TransferCode::receiptNumber('DEP', $tx);
            $tx->save();

            $this->bookSendEntries($tx, $wallet);

            return $tx;
        });

        AuditLogger::log('preorder.funded', $transaction, ['amount' => $transaction->amount]);
        $this->notifyCreated($transaction, $code);

        return [$transaction->fresh(['client', 'destinationCountry', 'creator']), $code];
    }

    /**
     * Le client annule un pré-envoi non payé (aucun mouvement d'argent).
     *
     * @throws TransferException
     */
    public function cancelPreorder(Transaction $transaction, Client $client): Transaction
    {
        $transaction = DB::transaction(function () use ($transaction, $client) {
            $tx = Transaction::whereKey($transaction->id)->lockForUpdate()->first();
            if ((int) $tx->client_id !== (int) $client->id) {
                throw new TransferException('Transfert introuvable.', 404);
            }
            if ($tx->status !== Transaction::STATUS_AWAITING_PAYMENT) {
                throw new TransferException(
                    'Ce transfert a déjà été payé en agence. Pour l\'annuler, adressez-vous à l\'agent qui l\'a encaissé.',
                    409
                );
            }
            $tx->fill([
                'status' => Transaction::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'cancel_reason' => 'Annulé par le client dans l\'application',
            ])->save();

            return $tx;
        });

        AuditLogger::log('preorder.cancelled', $transaction);

        return $transaction;
    }

    /** Bénéficiaires récents du client (pour ne pas tout ressaisir). */
    public function recentBeneficiaries(Client $client, int $limit = 10): array
    {
        return Transaction::where('client_id', $client->id)
            ->where('type', Transaction::TYPE_SEND)
            ->whereNotNull('recipient_phone')
            ->with('destinationCountry')
            ->latest()
            ->limit(100)
            ->get()
            ->unique(fn ($t) => $t->destination_country_id . '|' . $t->recipient_phone)
            ->take($limit)
            ->map(fn ($t) => [
                'name' => $t->recipient_name,
                'phone' => $t->recipient_phone,
                'country_id' => $t->destination_country_id,
                'country' => optional($t->destinationCountry)->name ?? $t->destination,
            ])
            ->values()
            ->all();
    }

    /** @throws TransferException */
    private function assertPreorderPayable(?Transaction $transaction): void
    {
        if (!$transaction) {
            throw new TransferException('Code de paiement inconnu.', 404);
        }
        if ($transaction->status !== Transaction::STATUS_AWAITING_PAYMENT) {
            throw new TransferException('Ce pré-envoi a déjà été traité (' . $transaction->status . ').', 409);
        }
        if ($transaction->isExpired()) {
            throw new TransferException('Ce pré-envoi a expiré. Le client doit en créer un nouveau.', 410);
        }
    }

    public function getBalance(User $user): array
    {
        $wallet = $user->wallet;

        return [
            'balance' => $wallet ? $wallet->balance : 0,
            'currency' => $wallet ? $wallet->currency : null,
        ];
    }

    public function getHistory(User $user, int $perPage = 30)
    {
        return Transaction::visibleTo($user)
            ->with(['client', 'destinationCountry'])
            ->paginate(min(max($perPage, 1), 100));
    }

    // ---------------------------------------------------------------------
    // Règles internes
    // ---------------------------------------------------------------------

    /**
     * Écritures d'un envoi déjà débité du portefeuille de l'agent : débit agent + frais en commissions.
     * À appeler dans une transaction SQL, portefeuille agent déjà verrouillé et débité.
     */
    private function bookSendEntries(Transaction $transaction, Wallet $wallet): void
    {
        Ledger::create([
            'transaction_id' => $transaction->id,
            'wallet_id' => $wallet->id,
            'amount' => -$transaction->total_amount,
            'balance_after' => $wallet->balance,
            'type' => 'debit',
        ]);

        if ($transaction->fee_amount > 0) {
            $revenue = Wallet::whereKey(Wallet::revenue($wallet->currency)->id)->lockForUpdate()->first();
            $revenue->balance = round($revenue->balance + $transaction->fee_amount, 2);
            $revenue->save();

            Ledger::create([
                'transaction_id' => $transaction->id,
                'wallet_id' => $revenue->id,
                'amount' => $transaction->fee_amount,
                'balance_after' => $revenue->balance,
                'type' => 'fee',
            ]);
        }
    }

    /** @throws TransferException */
    private function assertPayable(?Transaction $transaction): void
    {
        if (!$transaction) {
            throw new TransferException('Code invalide ou déjà utilisé.', 404);
        }
        if ($transaction->status !== Transaction::STATUS_PENDING) {
            throw new TransferException('Ce transfert a déjà été traité (' . $transaction->status . ').', 409);
        }
        if ($transaction->isExpired()) {
            throw new TransferException('Ce transfert a expiré. Il sera remboursé à l\'expéditeur.', 410);
        }
    }

    /** @throws TransferException */
    private function checkLimits(float $amount): void
    {
        $max = (float) config('transfer.max_amount_per_transfer', 0);
        if ($max > 0 && $amount > $max) {
            throw new TransferException('Montant supérieur au plafond par transfert (' . number_format($max, 0, ',', ' ') . ').');
        }
    }

    /** @throws TransferException */
    private function checkClientDailyLimit(Client $client, float $amount, ?int $ignoreTransactionId = null): void
    {
        $limit = (float) config('transfer.daily_limit_per_client', 0);
        if ($limit <= 0) {
            return;
        }

        $today = Transaction::where('client_id', $client->id)
            ->when($ignoreTransactionId, fn ($q) => $q->where('id', '!=', $ignoreTransactionId))
            ->where('type', Transaction::TYPE_SEND)
            ->whereIn('status', [Transaction::STATUS_AWAITING_PAYMENT, Transaction::STATUS_PENDING, Transaction::STATUS_COMPLETED])
            ->where('created_at', '>=', now()->startOfDay())
            ->sum('amount');

        if ($today + $amount > $limit) {
            throw new TransferException(
                'Plafond journalier du client dépassé (' . number_format($limit, 0, ',', ' ') . ' par jour).'
            );
        }
    }

    private function findOrCreateClient(array $data): Client
    {
        $raw = trim($data['client_phone']);
        $country = !empty($data['client_country_id']) ? Country::find($data['client_country_id']) : null;
        $phone = Phone::normalize($raw, $country) ?: $raw;
        // Même client que dans l'application mobile (numéro national en chiffres) ou saisie historique.
        $client = Client::where('phone', $phone)->orWhere('phone', $raw)->first();

        if (!$client) {
            return Client::create([
                'uuid' => (string) Str::uuid(),
                'name' => $data['client_name'],
                'phone' => $phone,
                'country_id' => $data['client_country_id'] ?? null,
                'id_type' => $data['sender_id_type'] ?? null,
                'id_number' => $data['sender_id_number'] ?? null,
            ]);
        }

        // Compléter la fiche client si de nouvelles informations sont fournies.
        $updates = array_filter([
            'country_id' => $client->country_id ? null : ($data['client_country_id'] ?? null),
            'id_type' => $data['sender_id_type'] ?? null,
            'id_number' => $data['sender_id_number'] ?? null,
        ]);
        if ($updates) {
            $client->update($updates);
        }

        return $client;
    }

    private function resolveCountry($id, ?string $name): ?Country
    {
        if ($id) {
            return Country::find($id);
        }
        if ($name) {
            return Country::where('name', $name)->orWhere('code', strtoupper($name))->first();
        }

        return null;
    }

    private function money(float $amount, string $currency): string
    {
        return number_format($amount, 0, ',', ' ') . ' ' . $currency;
    }

    // ---------------------------------------------------------------------
    // Notifications SMS (ne bloquent jamais l'opération)
    // ---------------------------------------------------------------------

    private function notifyCreated(Transaction $transaction, string $code): void
    {
        $this->sendCodeSms($transaction, $code);

        $this->safeSms('confirmation expéditeur', $transaction, function () use ($transaction) {
            $client = $transaction->client()->with('country')->first();
            if ($client && $client->phone) {
                $this->smsService->sendTransferConfirmation(
                    $client->phone,
                    $transaction->reference,
                    $transaction->amount,
                    $transaction->fee_amount,
                    $transaction->currency,
                    (string) $transaction->recipient_name,
                    optional($client->country)->phone_code,
                    'mt-confirm-' . $transaction->uuid
                );
            }
        });
    }

    private function sendCodeSms(Transaction $transaction, string $code): void
    {
        $this->safeSms('code de retrait', $transaction, function () use ($transaction, $code) {
            $transaction->loadMissing(['client', 'destinationCountry']);
            $this->smsService->sendWithdrawalCode(
                (string) $transaction->recipient_phone,
                $code,
                $transaction->reference,
                $transaction->amount,
                $transaction->currency,
                optional($transaction->client)->name ?? 'un client',
                optional($transaction->expires_at)->format('d/m/Y'),
                // Indicatif du pays de DESTINATION (et non celui de l'expéditeur).
                optional($transaction->destinationCountry)->phone_code,
                // Nouvelle clé à chaque nouveau code (renvoi) ; même clé si l'envoi est rejoué.
                'mt-wcode-' . $transaction->uuid . '-' . substr((string) $transaction->withdrawal_code_hash, 0, 12)
            );

            $this->smsService->sendWithdrawalCodeWhatsApp(
                (string) $transaction->recipient_phone,
                $code,
                $transaction->reference,
                $transaction->amount,
                $transaction->currency,
                optional($transaction->client)->name ?? 'un client',
                optional($transaction->destinationCountry)->phone_code,
                'mt-wcode-' . $transaction->uuid . '-' . substr((string) $transaction->withdrawal_code_hash, 0, 12)
            );
        });
    }

    private function notifyPaid(Transaction $transaction): void
    {
        $this->safeSms('confirmation paiement', $transaction, function () use ($transaction) {
            $client = $transaction->client()->with('country')->first();
            if ($client && $client->phone) {
                $this->smsService->sendPaymentConfirmation(
                    $client->phone,
                    $transaction->reference,
                    $transaction->amount,
                    $transaction->currency,
                    optional($client->country)->phone_code,
                    'mt-paid-' . $transaction->uuid
                );
            }
        });
    }

    private function notifyCancelled(Transaction $transaction): void
    {
        $this->safeSms('annulation', $transaction, function () use ($transaction) {
            $client = $transaction->client()->with('country')->first();
            if ($client && $client->phone) {
                $this->smsService->sendCancellationNotice(
                    $client->phone,
                    $transaction->reference,
                    $transaction->total_amount,
                    $transaction->currency,
                    optional($client->country)->phone_code,
                    'mt-cancel-' . $transaction->uuid
                );
            }
        });
    }

    private function safeSms(string $label, Transaction $transaction, callable $send): void
    {
        try {
            $send();
        } catch (\Throwable $e) {
            Log::warning("[TransactionService] Échec SMS ({$label})", [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
