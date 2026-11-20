<?php

namespace App\Services\Sms;

/**
 * Prestataire d'envoi de SMS. Ajouter un prestataire = une classe + une entrée dans SMS_DRIVERS.
 */
interface SmsDriver
{
    public function name(): string;

    public function isConfigured(): bool;

    /**
     * @param string      $msisdn          numéro international, chiffres seulement (ex. 221771234567)
     * @param string|null $idempotencyKey  même clé = même message (pas de doublon en cas de nouvel essai)
     */
    public function send(string $msisdn, string $content, ?string $idempotencyKey = null): SmsResult;
}
