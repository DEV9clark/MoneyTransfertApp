<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\TransferException;
use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Services\ReceiptService;
use Illuminate\Http\Request;

class ReceiptController extends Controller
{
    /**
     * GET /api/transactions/{uuid}/receipt/{deposit|withdrawal}
     * Accessible à l'agent expéditeur, à l'agent payeur et aux admins.
     */
    public function show(Request $request, Transaction $transaction, string $kind, ReceiptService $receipts)
    {
        if (!$transaction->isVisibleTo($request->user())) {
            return response()->json(['message' => 'Accès refusé à ce reçu.'], 403);
        }

        try {
            $pdf = $receipts->render($transaction, $kind);
        } catch (TransferException $e) {
            return response()->json(['message' => $e->getMessage()], $e->getStatus());
        }

        $disposition = $request->boolean('download') ? 'attachment' : 'inline';

        return response($pdf['content'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition . '; filename="' . $pdf['filename'] . '"',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
