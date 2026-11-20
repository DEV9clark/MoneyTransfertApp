<?php

namespace App\Services;

use App\Exceptions\TransferException;
use App\Models\AuditLog;
use App\Models\Transaction;
use App\Support\NumberToWordsFr;
use App\Support\TransferCode;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Reçus PDF pour la traçabilité :
 *  - "deposit"    : reçu d'ENVOI remis à l'expéditeur (au guichet de départ)
 *  - "withdrawal" : reçu de RETRAIT signé par le bénéficiaire (au guichet de paiement)
 *
 * Chaque PDF contient deux exemplaires (client + agence). Toute réimpression est
 * journalisée et marquée "DUPLICATA".
 */
class ReceiptService
{
    public const DEPOSIT = 'deposit';
    public const WITHDRAWAL = 'withdrawal';

    /**
     * @return array{filename: string, content: string}
     * @throws TransferException
     */
    public function render(Transaction $transaction, string $kind): array
    {
        if (!in_array($kind, [self::DEPOSIT, self::WITHDRAWAL], true)) {
            throw new TransferException('Type de reçu inconnu.', 404);
        }
        if ($transaction->type !== Transaction::TYPE_SEND) {
            throw new TransferException("Aucun reçu client pour ce type d'opération.", 404);
        }
        if ($kind === self::DEPOSIT && $transaction->sender_receipt_number === null) {
            throw new TransferException("Le reçu d'envoi sera disponible après le paiement en agence.", 409);
        }
        if ($kind === self::WITHDRAWAL && $transaction->status !== Transaction::STATUS_COMPLETED) {
            throw new TransferException("Le reçu de retrait n'est disponible qu'après le paiement.", 409);
        }

        $transaction->loadMissing(['client.country', 'destinationCountry', 'creator', 'payer']);

        $action = 'receipt.' . $kind;
        $previousPrints = AuditLog::where('action', $action)
            ->where('subject_type', Transaction::class)
            ->where('subject_id', $transaction->id)
            ->count();

        AuditLogger::log($action, $transaction, ['print' => $previousPrints + 1]);

        $number = $kind === self::DEPOSIT ? $transaction->sender_receipt_number : $transaction->payout_receipt_number;
        $amountForWords = $kind === self::DEPOSIT ? $transaction->total_amount : $transaction->amount;

        $data = [
            't' => $transaction,
            'kind' => $kind,
            'receiptNumber' => $number,
            'isDuplicate' => $previousPrints > 0,
            'printCount' => $previousPrints + 1,
            'company' => config('transfer.company'),
            'idTypes' => config('transfer.id_types'),
            'amountInWords' => NumberToWordsFr::convert($amountForWords, $transaction->currency),
            'fingerprint' => TransferCode::receiptFingerprint($transaction, $kind),
            'printedAt' => now(),
            'printedBy' => optional(auth()->user())->name,
        ];

        $pdf = Pdf::loadView('receipts.' . $kind, $data)->setPaper('a5', 'portrait');

        return [
            'filename' => ($kind === self::DEPOSIT ? 'recu-envoi-' : 'recu-retrait-') . $transaction->reference . '.pdf',
            'content' => $pdf->output(),
        ];
    }
}
