<?php

namespace App\Support;

use App\Models\Transaction;
use Illuminate\Support\Str;

/**
 * Codes de retrait et numéros de suivi.
 *
 * Le code de retrait est secret : il n'est jamais stocké en clair, uniquement son
 * empreinte HMAC-SHA256 (clé = APP_KEY). Le numéro de référence, lui, est public
 * et figure sur les reçus.
 */
class TransferCode
{
    public static function generate(): string
    {
        $length = max(6, (int) config('transfer.code_length', 8));

        do {
            $code = '';
            for ($i = 0; $i < $length; $i++) {
                $code .= random_int(0, 9);
            }
        } while (Transaction::where('withdrawal_code_hash', self::hash($code))->exists());

        return $code;
    }

    public static function normalize(string $code): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code));
    }

    public static function hash(string $code): string
    {
        return hash_hmac('sha256', self::normalize($code), (string) config('app.key'));
    }

    public static function reference(): string
    {
        // Alphabet sans caractères ambigus (0/O, 1/I/L).
        $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

        do {
            $suffix = '';
            for ($i = 0; $i < 6; $i++) {
                $suffix .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
            $reference = 'MT' . now()->format('ymd') . '-' . $suffix;
        } while (Transaction::where('reference', $reference)->exists());

        return $reference;
    }

    /** Code de paiement d'un pré-envoi, à présenter à l'agent (6 caractères, sans caractères ambigus). */
    public static function paymentCode(): string
    {
        $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
        do {
            $code = '';
            for ($i = 0; $i < 6; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
        } while (Transaction::where('payment_code', $code)->exists());

        return $code;
    }

    public static function receiptNumber(string $prefix, Transaction $transaction): string
    {
        return $prefix . '-' . now()->format('Ymd') . '-' . str_pad((string) $transaction->id, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Empreinte courte imprimée sur les reçus : permet de détecter un reçu falsifié
     * (montant ou référence modifiés) en la recalculant côté serveur.
     */
    public static function receiptFingerprint(Transaction $t, string $kind): string
    {
        $payload = implode('|', [
            $kind, $t->uuid, $t->reference, number_format((float) $t->amount, 2, '.', ''),
            number_format((float) $t->fee_amount, 2, '.', ''), $t->currency,
            optional($t->created_at)->toIso8601String(),
        ]);

        return strtoupper(substr(hash_hmac('sha256', $payload, (string) config('app.key')), 0, 16));
    }
}
