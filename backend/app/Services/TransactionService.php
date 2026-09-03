<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Ledger;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Exception;

class TransactionService
{
    protected SmsService $smsService;

    public function __construct(SmsService $smsService)
    {
        $this->smsService = $smsService;
    }

    public function initiateTransfer(User $sender, array $data)
    {
        $senderWallet = $sender->wallet;

        if (!$senderWallet)
        {
            throw new Exception("Sender wallet not found.");
        }

        $amount = $data['amount'];
        $fee = $data['fee_amount'];
        $total = $data['total_amount'];

        if ($senderWallet->balance < $total)
        {
            throw new Exception("Insufficient funds. Total required: {$total}");
        }

        // Find or Create Client
        $client = Client::firstOrCreate(
            ['phone' => $data['client_phone']],
            ['name' => $data['client_name'], 'uuid' => Str::uuid()] // Add uuid if creating
        );

        $code = strtoupper(Str::random(6)); // SMS verification code

        return DB::transaction(function () use ($senderWallet, $client, $amount, $fee, $total, $code, $data, $sender)
        {
            // 1. Debit Sender Agent (Amount + Fee)
            $senderBalanceBefore = $senderWallet->balance;
            $senderWallet->decrement('balance', $total);

            // 2. Create Transaction (Pending, linked to Client)
            $transaction = Transaction::create([
                'uuid' => Str::uuid(),
                'sender_wallet_id' => $senderWallet->id,
                'client_id' => $client->id,
                'payout_wallet_id' => null, // Not yet known
                'amount' => $amount,
                'fee_amount' => $fee,
                'total_amount' => $total,
                'currency' => $senderWallet->currency,
                'destination' => $data['destination'] ?? null,
                'recipient_name' => $data['recipient_name'] ?? null,
                'recipient_phone' => $data['recipient_phone'] ?? null,
                'status' => 'pending',
                'type' => $data['type'] ?? 'send',
                'reference' => $code,
            ]);

            // 3. Ledger Entry for Sender
            Ledger::create([
                'transaction_id' => $transaction->id,
                'wallet_id' => $senderWallet->id,
                'amount' => -$total,
                'balance_after' => $senderBalanceBefore - $total,
                'type' => 'debit',
            ]);



            // 4. Send SMS to client with withdrawal code
            try
            {
                // Récupérer l'indicatif du pays du client depuis la relation
                $client->load('country');
                $countryPhoneCode = $client->country ? $client->country->phone_code : null;

                $this->smsService->sendWithdrawalCode(
                    $transaction->recipient_phone,
                    $code,
                    $amount,
                    $senderWallet->currency,
                    $countryPhoneCode
                );
            }
            catch (\Throwable $e)
            {
                // Ne pas bloquer la transaction si le SMS échoue
                Log::warning('[TransactionService] Échec envoi SMS au client', [
                    'client_phone' => $client->phone,
                    'transaction_id' => $transaction->id,
                    'error' => $e->getMessage(),
                ]);
            }

            return $transaction;
        });
    }

    public function completeTransfer(User $payoutAgent, string $code)
    {
        $transaction = Transaction::where('reference', $code)
            ->where('status', 'pending')
            ->firstOrFail();

        // No receiver_wallet check here anymore, as ANY agent can payout.

        return DB::transaction(function () use ($transaction, $payoutAgent)
        {
            $payoutWallet = $payoutAgent->wallet;
            $amount = $transaction->amount;

            // 1. Credit Payout Agent (Agent B)
            $payoutBalanceBefore = $payoutWallet->balance;
            $payoutWallet->increment('balance', $amount);

            // 2. Ledger Entry for Payout Agent
            Ledger::create([
                'transaction_id' => $transaction->id,
                'wallet_id' => $payoutWallet->id,
                'amount' => $amount,
                'balance_after' => $payoutBalanceBefore + $amount,
                'type' => 'credit',
            ]);

            // 3. Update Transaction Status & Payout Wallet
            $transaction->update([
                'status' => 'completed',
                'payout_wallet_id' => $payoutWallet->id
            ]);

            // 4. Send SMS confirmation to client
            try
            {
                $client = $transaction->client;
                if ($client && $client->phone)
                {
                    // Récupérer l'indicatif du pays du client
                    $client->load('country');
                    $countryPhoneCode = $client->country ? $client->country->phone_code : null;

                    $this->smsService->sendPaymentConfirmation(
                        $client->phone,
                        $transaction->reference,
                        $transaction->amount,
                        $transaction->currency,
                        $countryPhoneCode
                    );
                }
            }
            catch (\Throwable $e)
            {
                // Ne pas bloquer la transaction si le SMS échoue
                Log::warning('[TransactionService] Échec envoi SMS confirmation', [
                    'transaction_id' => $transaction->id,
                    'error' => $e->getMessage(),
                ]);
            }

            return $transaction;
        });
    }

    public function getBalance(User $user)
    {
        return $user->wallet ? $user->wallet->balance : 0;
    }

    public function getHistory(User $user)
    {
        $walletId = $user->wallet->id;
        // Logic update: Agents see transactions where they sent OR paid out.
        return Transaction::where('sender_wallet_id', $walletId)
            ->orWhere('payout_wallet_id', $walletId)
            ->with(['client']) // Load client details
            ->orderBy('created_at', 'desc')
            ->get();
    }
}
