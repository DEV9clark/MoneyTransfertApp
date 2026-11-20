<?php

namespace App\Http\Controllers\Api\Client;

use App\Exceptions\TransferException;
use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\Transaction;
use App\Services\ReceiptService;
use App\Services\TariffService;
use App\Services\TransactionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Transferts vus par le client : il prépare l'envoi, le paie en agence avec le code,
 * suit son statut et télécharge ses reçus.
 */
class TransferController extends Controller
{
    protected TransactionService $transactions;

    public function __construct(TransactionService $transactions)
    {
        $this->transactions = $transactions;
    }

    public function countries()
    {
        return response()->json(Country::orderBy('name')->get(['id', 'name', 'code', 'currency', 'phone_code']));
    }

    public function quote(Request $request, TariffService $tariffs)
    {
        $data = $request->validate(['amount' => 'required|numeric|min:1']);

        return $this->handle(function () use ($data, $tariffs) {
            $amount = round((float) $data['amount'], 2);
            $fee = $tariffs->calculateFee($amount, 'send');

            return response()->json([
                'amount' => $amount,
                'fee' => $fee,
                'total' => round($amount + $fee, 2),
                'currency' => config('transfer.default_currency', 'XOF'),
            ]);
        });
    }

    public function beneficiaries(Request $request)
    {
        return response()->json(['data' => $this->transactions->recentBeneficiaries($request->user())]);
    }

    public function index(Request $request)
    {
        $page = Transaction::where('client_id', $request->user()->id)
            ->where('type', Transaction::TYPE_SEND)
            ->with('destinationCountry')
            ->latest()
            ->paginate(20);

        return response()->json([
            'data' => collect($page->items())->map->toClientArray()->values(),
            'current_page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'amount' => 'required|numeric|min:1',
            'destination_country_id' => 'required|exists:countries,id',
            'recipient_name' => 'required|string|min:2|max:255',
            'recipient_phone' => 'required|string|max:20',
        ]);

        return $this->handle(function () use ($request, $data) {
            $tx = $this->transactions->createPreorder($request->user(), $data);

            return response()->json([
                'message' => "Envoi préparé. Présentez le code {$tx->payment_code} à un agent pour payer.",
                'data' => $tx->toClientArray(),
            ], 201);
        });
    }

    public function show(Request $request, Transaction $transaction)
    {
        $this->authorizeOwner($request, $transaction);

        return response()->json(['data' => $transaction->toClientArray()]);
    }

    public function cancel(Request $request, Transaction $transaction)
    {
        $this->authorizeOwner($request, $transaction);

        return $this->handle(function () use ($request, $transaction) {
            $tx = $this->transactions->cancelPreorder($transaction, $request->user());

            return response()->json(['message' => 'Envoi annulé.', 'data' => $tx->toClientArray()]);
        });
    }

    public function receipt(Request $request, Transaction $transaction, string $kind, ReceiptService $receipts)
    {
        $this->authorizeOwner($request, $transaction);

        return $this->handle(function () use ($transaction, $kind, $receipts) {
            $pdf = $receipts->render($transaction, $kind);

            return response($pdf['content'], 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="' . $pdf['filename'] . '"',
                'Cache-Control' => 'private, no-store',
            ]);
        });
    }

    private function authorizeOwner(Request $request, Transaction $transaction): void
    {
        if ((int) $transaction->client_id !== (int) $request->user()->id || $transaction->type !== Transaction::TYPE_SEND) {
            abort(response()->json(['message' => 'Transfert introuvable.'], 404));
        }
    }

    private function handle(callable $action)
    {
        try {
            return $action();
        } catch (TransferException $e) {
            return response()->json(['message' => $e->getMessage()], $e->getStatus());
        } catch (\Throwable $e) {
            Log::error('[Client\\TransferController] ' . $e->getMessage(), ['exception' => $e]);

            return response()->json(['message' => 'Erreur interne. Réessayez plus tard.'], 500);
        }
    }
}
