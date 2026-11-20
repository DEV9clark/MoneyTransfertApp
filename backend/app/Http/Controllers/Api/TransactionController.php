<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\TransferException;
use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Services\AuditLogger;
use App\Services\TariffService;
use App\Services\TransactionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;

class TransactionController extends Controller
{
    protected TransactionService $transactionService;
    protected TariffService $tariffService;

    public function __construct(TransactionService $transactionService, TariffService $tariffService)
    {
        $this->transactionService = $transactionService;
        $this->tariffService = $tariffService;
    }

    /**
     * Envoi d'argent. Le code de retrait part par SMS au bénéficiaire et n'est PAS renvoyé à l'agent
     * (sauf TRANSFER_SHOW_CODE_TO_AGENT=true, réservé au développement).
     */
    public function transfer(Request $request)
    {
        $idTypes = array_keys(config('transfer.id_types'));
        $requireSenderId = (bool) config('transfer.require_sender_id');

        $data = $request->validate([
            'type' => 'nullable|in:send',
            'client_name' => 'required|string|max:255',
            'client_phone' => 'required|string|max:20',
            'client_country_id' => 'nullable|exists:countries,id',
            'sender_id_type' => [$requireSenderId ? 'required' : 'nullable', Rule::in($idTypes)],
            'sender_id_number' => [$requireSenderId ? 'required' : 'nullable', 'required_with:sender_id_type', 'string', 'max:50'],
            'amount' => 'required|numeric|min:1',
            'destination_country_id' => 'required_without:destination|nullable|exists:countries,id',
            'destination' => 'required_without:destination_country_id|nullable|string|max:100',
            'recipient_name' => 'required|string|max:255',
            'recipient_phone' => 'required|string|max:20',
        ]);

        return $this->handle(function () use ($request, $data) {
            [$transaction, $code] = $this->transactionService->initiateTransfer($request->user(), $data);

            $response = [
                'message' => 'Transfert enregistré. Le code de retrait a été envoyé par SMS au bénéficiaire.',
                'data' => $transaction,
                'reference' => $transaction->reference,
                'receipt_url' => url("/api/transactions/{$transaction->uuid}/receipt/deposit"),
            ];

            if (config('transfer.show_code_to_agent')) {
                $response['code'] = $code;
            }

            return response()->json($response, 201);
        });
    }

    /**
     * Étape 1 du retrait : vérifier le code et afficher les détails à contrôler (identité du bénéficiaire).
     */
    public function verifyCode(Request $request)
    {
        $request->validate(['code' => 'required|string|max:20']);

        if ($blocked = $this->tooManyAttempts($request)) {
            return $blocked;
        }

        return $this->handle(function () use ($request) {
            try {
                $transaction = $this->transactionService->findPayableByCode($request->code);
            } catch (TransferException $e) {
                $this->recordFailedAttempt($request);
                throw $e;
            }

            RateLimiter::clear($this->attemptKey($request));
            AuditLogger::log('transfer.code_verified', $transaction);

            return response()->json(['data' => [
                'uuid' => $transaction->uuid,
                'reference' => $transaction->reference,
                'amount' => $transaction->amount,
                'currency' => $transaction->currency,
                'sender' => optional($transaction->client)->name,
                'sender_phone' => optional($transaction->client)->phone,
                'recipient_name' => $transaction->recipient_name,
                'recipient_phone' => $transaction->recipient_phone,
                'destination' => optional($transaction->destinationCountry)->name ?? $transaction->destination,
                'created_at' => $transaction->created_at->format('Y-m-d H:i'),
                'expires_at' => optional($transaction->expires_at)->format('Y-m-d'),
            ]]);
        });
    }

    /**
     * Étape 2 du retrait : paiement, après contrôle de la pièce d'identité du bénéficiaire.
     */
    public function withdraw(Request $request)
    {
        $data = $request->validate([
            'code' => 'required|string|max:20',
            'recipient_id_type' => ['required', Rule::in(array_keys(config('transfer.id_types')))],
            'recipient_id_number' => 'required|string|max:50',
        ]);

        if ($blocked = $this->tooManyAttempts($request)) {
            return $blocked;
        }

        return $this->handle(function () use ($request, $data) {
            try {
                $transaction = $this->transactionService->completeTransfer($request->user(), $data['code'], $data);
            } catch (TransferException $e) {
                if ($e->getStatus() === 404) {
                    $this->recordFailedAttempt($request);
                }
                throw $e;
            }

            RateLimiter::clear($this->attemptKey($request));

            return response()->json([
                'message' => 'Paiement effectué.',
                'data' => $transaction,
                'receipt_url' => url("/api/transactions/{$transaction->uuid}/receipt/withdrawal"),
            ]);
        });
    }

    public function show(Request $request, Transaction $transaction)
    {
        if (!$transaction->isVisibleTo($request->user())) {
            return response()->json(['message' => 'Transaction introuvable.'], 404);
        }

        return response()->json(['data' => $transaction->load(['client', 'destinationCountry', 'creator', 'payer'])]);
    }

    public function cancel(Request $request, Transaction $transaction)
    {
        $data = $request->validate(['reason' => 'required|string|max:255']);
        $user = $request->user();

        $isSender = $user->wallet && (int) $transaction->sender_wallet_id === (int) $user->wallet->id;
        if (!$user->isAdmin() && !$isSender) {
            return response()->json(['message' => "Seul l'agent expéditeur ou un admin peut annuler."], 403);
        }

        return $this->handle(function () use ($transaction, $user, $data) {
            $transaction = $this->transactionService->cancel($transaction, $user, $data['reason']);

            return response()->json(['message' => 'Transfert annulé et remboursé.', 'data' => $transaction]);
        });
    }

    public function resendCode(Request $request, Transaction $transaction)
    {
        $user = $request->user();
        $isSender = $user->wallet && (int) $transaction->sender_wallet_id === (int) $user->wallet->id;
        if (!$user->isAdmin() && !$isSender) {
            return response()->json(['message' => 'Action non autorisée.'], 403);
        }

        return $this->handle(function () use ($transaction, $user) {
            $this->transactionService->resendCode($transaction, $user);

            return response()->json(['message' => 'Un nouveau code a été envoyé au bénéficiaire.']);
        });
    }

    public function balance(Request $request)
    {
        return response()->json($this->transactionService->getBalance($request->user()));
    }

    public function history(Request $request)
    {
        return response()->json(
            $this->transactionService->getHistory($request->user(), (int) $request->get('per_page', 30))
        );
    }

    public function calculateFees(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0',
            'type' => 'nullable|string',
        ]);

        $amount = (float) $request->amount;

        return $this->handle(function () use ($amount) {
            $fee = $this->tariffService->calculateFee($amount, 'send');

            return response()->json([
                'amount' => $amount,
                'fee' => $fee,
                'total' => round($amount + $fee, 2),
            ]);
        });
    }

    /** Envois en attente de retrait : ceux de l'agent, ou tout le réseau pour un admin. */
    public function pendingCount(Request $request)
    {
        $count = Transaction::visibleTo($request->user())
            ->where('status', Transaction::STATUS_PENDING)
            ->where('type', Transaction::TYPE_SEND)
            ->count();

        return response()->json(['count' => $count]);
    }

    public function stats(Request $request)
    {
        $user = $request->user();
        $filter = $request->get('filter', 'day');
        $currency = $request->get('currency') ?: optional($user->wallet)->currency ?: 'XOF';

        $base = Transaction::visibleTo($user)
            ->reorder()
            ->where('type', Transaction::TYPE_SEND)
            ->where('currency', $currency);

        switch ($filter) {
            case 'week':
                $base->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
                break;
            case 'month':
                $base->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()]);
                break;
            case 'year':
                $base->whereBetween('created_at', [now()->startOfYear(), now()->endOfYear()]);
                break;
            default:
                $filter = 'day';
                $base->whereBetween('created_at', [now()->startOfDay(), now()->endOfDay()]);
        }

        $valid = (clone $base)->whereIn('status', [Transaction::STATUS_PENDING, Transaction::STATUS_COMPLETED]);

        return response()->json([
            'volume' => (float) (clone $valid)->sum('amount'),
            'count' => (clone $valid)->count(),
            'pending' => (clone $base)->where('status', Transaction::STATUS_PENDING)->count(),
            'completed' => (clone $base)->where('status', Transaction::STATUS_COMPLETED)->count(),
            'fees' => $user->isAdmin() ? (float) (clone $valid)->sum('fee_amount') : 0,
            'currency' => $currency,
            'filter' => $filter,
        ]);
    }

    // ---------------------------------------------------------------------

    /**
     * Exécute une action métier : erreurs métier => message clair, erreurs techniques => message générique
     * (le détail est journalisé, jamais exposé au client).
     */
    private function handle(callable $action)
    {
        try {
            return $action();
        } catch (TransferException $e) {
            return response()->json(['message' => $e->getMessage()], $e->getStatus());
        } catch (\Throwable $e) {
            Log::error('[TransactionController] ' . $e->getMessage(), ['exception' => $e]);

            return response()->json(['message' => 'Erreur interne. Réessayez ou contactez le support.'], 500);
        }
    }

    private function attemptKey(Request $request): string
    {
        return 'withdrawal-code:' . optional($request->user())->id . ':' . $request->ip();
    }

    private function tooManyAttempts(Request $request)
    {
        $key = $this->attemptKey($request);
        if (RateLimiter::tooManyAttempts($key, (int) config('transfer.max_code_attempts', 5))) {
            $minutes = (int) ceil(RateLimiter::availableIn($key) / 60);
            AuditLogger::log('transfer.code_blocked');

            return response()->json([
                'message' => "Trop de codes erronés. Réessayez dans {$minutes} minute(s).",
            ], 429);
        }

        return null;
    }

    private function recordFailedAttempt(Request $request): void
    {
        RateLimiter::hit($this->attemptKey($request), 60 * (int) config('transfer.code_attempts_decay_minutes', 15));
        AuditLogger::log('transfer.code_failed');
    }
}
