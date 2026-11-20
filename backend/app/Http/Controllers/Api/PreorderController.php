<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\TransferException;
use App\Http\Controllers\Controller;
use App\Services\TransactionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * Côté agent : encaissement des pré-envois créés par les clients dans l'application.
 */
class PreorderController extends Controller
{
    protected TransactionService $transactions;

    public function __construct(TransactionService $transactions)
    {
        $this->transactions = $transactions;
    }

    public function lookup(Request $request)
    {
        $request->validate(['payment_code' => 'required|string|max:12']);

        return $this->handle(function () use ($request) {
            $tx = $this->transactions->findPreorder($request->payment_code);

            return response()->json(['data' => [
                'uuid' => $tx->uuid,
                'reference' => $tx->reference,
                'payment_code' => $tx->payment_code,
                'client_name' => optional($tx->client)->name,
                'client_phone' => optional($tx->client)->phone,
                'client_has_id' => optional($tx->client)->id_number !== null,
                'recipient_name' => $tx->recipient_name,
                'recipient_phone' => $tx->recipient_phone,
                'destination' => optional($tx->destinationCountry)->name ?? $tx->destination,
                'amount' => $tx->amount,
                'fee' => $tx->fee_amount,
                'total' => $tx->total_amount,
                'currency' => $tx->currency,
                'created_at' => $tx->created_at->format('Y-m-d H:i'),
                'expires_at' => optional($tx->expires_at)->format('Y-m-d H:i'),
            ]]);
        });
    }

    public function confirm(Request $request)
    {
        $idTypes = array_keys(config('transfer.id_types'));
        $requireId = (bool) config('transfer.require_sender_id');

        $data = $request->validate([
            'payment_code' => 'required|string|max:12',
            'sender_id_type' => [$requireId ? 'required' : 'nullable', Rule::in($idTypes)],
            'sender_id_number' => [$requireId ? 'required' : 'nullable', 'required_with:sender_id_type', 'string', 'max:50'],
        ]);

        return $this->handle(function () use ($request, $data) {
            [$tx, $code] = $this->transactions->fundPreorder($request->user(), $data['payment_code'], $data);

            $response = [
                'message' => 'Pré-envoi encaissé. Le code de retrait a été envoyé par SMS au bénéficiaire.',
                'data' => $tx,
                'reference' => $tx->reference,
                'receipt_url' => url("/api/transactions/{$tx->uuid}/receipt/deposit"),
            ];
            if (config('transfer.show_code_to_agent')) {
                $response['code'] = $code;
            }

            return response()->json($response);
        });
    }

    private function handle(callable $action)
    {
        try {
            return $action();
        } catch (TransferException $e) {
            return response()->json(['message' => $e->getMessage()], $e->getStatus());
        } catch (\Throwable $e) {
            Log::error('[PreorderController] ' . $e->getMessage(), ['exception' => $e]);

            return response()->json(['message' => 'Erreur interne. Réessayez ou contactez le support.'], 500);
        }
    }
}
