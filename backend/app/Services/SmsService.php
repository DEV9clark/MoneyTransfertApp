<?php

namespace App\Services;

use App\Services\Sms\DexchangeDriver;
use App\Services\Sms\DexNotifyDriver;
use App\Services\Sms\SmsDriver;
use App\Services\Sms\SmsResult;
use Illuminate\Support\Facades\Log;

/**
 * Envoi des SMS de l'application.
 *
 * Les prestataires sont essayés dans l'ordre de SMS_DRIVERS (par défaut : dexnotify, puis dexchange
 * en secours). Le contenu des messages n'est jamais journalisé : il peut contenir un code secret.
 */
class SmsService
{
    /** @var array<string, class-string<SmsDriver>> */
    protected const DRIVERS = [
        'dexnotify' => DexNotifyDriver::class,
        'dexchange' => DexchangeDriver::class,
    ];

    /**
     * @param string      $phone             numéro national ou international
     * @param string|null $countryPhoneCode  indicatif à ajouter s'il manque (ex. 221)
     * @param string|null $idempotencyKey    identifiant unique du message (anti-doublon)
     */
    public function sendSms(string $phone, string $message, ?string $countryPhoneCode = null, ?string $idempotencyKey = null): array
    {
        $msisdn = $this->formatPhone($phone, $countryPhoneCode);
        $tried = [];

        foreach ($this->drivers() as $driver) {
            if (!$driver->isConfigured()) {
                continue;
            }

            $result = $driver->send($msisdn, $message, $idempotencyKey);
            $tried[] = $driver->name();

            if ($result->success) {
                Log::info('[SMS] envoyé', ['driver' => $result->driver, 'phone' => $msisdn, 'id' => $result->messageId]);

                return $result->toArray();
            }

            Log::warning('[SMS] échec, prestataire suivant', [
                'driver' => $result->driver, 'phone' => $msisdn, 'status' => $result->status, 'error' => $result->error,
            ]);
        }

        if (!$tried) {
            Log::warning('[SMS] aucun prestataire configuré (DEXNOTIFY_API_KEY / DEXCHANGE_API_KEY). SMS non envoyé.', ['phone' => $msisdn]);

            return (new SmsResult(false, 'none', null, 'Aucun prestataire SMS configuré'))->toArray();
        }

        Log::error('[SMS] tous les prestataires ont échoué', ['phone' => $msisdn, 'drivers' => $tried]);

        return (new SmsResult(false, end($tried), null, 'Envoi impossible (' . implode(', ', $tried) . ')'))->toArray();
    }

    /**
     * Copie WhatsApp du code de retrait, si DEXNOTIFY_WHATSAPP_ACCOUNT_ID et le modèle sont configurés.
     * Le modèle Meta attend 4 variables : montant, expéditeur, référence, code.
     */
    public function sendWithdrawalCodeWhatsApp(string $phone, string $code, string $reference, float $amount, string $currency, string $senderName, ?string $countryPhoneCode = null, ?string $idempotencyKey = null): ?array
    {
        $template = (string) config('services.dexnotify.whatsapp_withdrawal_template');
        if ($template === '' || (string) config('services.dexnotify.whatsapp_account_id') === '' || !(new DexNotifyDriver())->isConfigured()) {
            return null;
        }

        $result = (new DexNotifyDriver())->sendWhatsAppTemplate(
            $this->formatPhone($phone, $countryPhoneCode),
            $template,
            [$this->money($amount, $currency), $senderName, $reference, $code],
            $idempotencyKey ? 'wa-' . $idempotencyKey : null
        );

        if (!$result->success) {
            Log::warning('[WhatsApp] échec envoi code de retrait', ['status' => $result->status, 'error' => $result->error]);
        }

        return $result->toArray();
    }

    /** @return SmsDriver[] */
    protected function drivers(): array
    {
        $names = array_filter(array_map('trim', explode(',', (string) config('services.sms.drivers', 'dexnotify,dexchange'))));

        return array_values(array_filter(array_map(
            fn ($name) => isset(static::DRIVERS[$name]) ? app(static::DRIVERS[$name]) : null,
            $names
        )));
    }

    /**
     * Code de retrait envoyé au BÉNÉFICIAIRE uniquement.
     */
    public function sendWithdrawalCode(string $phone, string $code, string $reference, float $amount, string $currency, string $senderName, ?string $expiresAt = null, ?string $countryPhoneCode = null, ?string $idempotencyKey = null): array
    {
        $message = "Vous avez recu " . $this->money($amount, $currency) . " de {$senderName}. "
            . "Ref: {$reference}. Code de retrait: {$code}. "
            . ($expiresAt ? "Valable jusqu'au {$expiresAt}. " : '')
            . "Presentez ce code et votre piece d'identite a l'agent. Ne le communiquez a personne d'autre.";

        return $this->sendSms($phone, $message, $countryPhoneCode, $idempotencyKey);
    }

    /**
     * Confirmation d'envoi à l'EXPÉDITEUR (sans le code de retrait).
     */
    public function sendTransferConfirmation(string $phone, string $reference, float $amount, float $fee, string $currency, string $recipientName, ?string $countryPhoneCode = null, ?string $idempotencyKey = null): array
    {
        $message = "Votre envoi de " . $this->money($amount, $currency) . " a {$recipientName} est enregistre. "
            . "Ref: {$reference}. Frais: " . $this->money($fee, $currency) . ". "
            . "Le code de retrait a ete envoye au beneficiaire. Merci de votre confiance.";

        return $this->sendSms($phone, $message, $countryPhoneCode, $idempotencyKey);
    }

    /**
     * Confirmation de paiement (retrait effectué).
     */
    public function sendPaymentConfirmation(string $phone, string $reference, float $amount, string $currency, ?string $countryPhoneCode = null, ?string $idempotencyKey = null): array
    {
        $message = "Le transfert Ref: {$reference} de " . $this->money($amount, $currency) . " a ete paye au beneficiaire. "
            . "Merci de votre confiance.";

        return $this->sendSms($phone, $message, $countryPhoneCode, $idempotencyKey);
    }

    /**
     * Annulation / expiration : l'expéditeur est informé du remboursement.
     */
    public function sendCancellationNotice(string $phone, string $reference, float $amount, string $currency, ?string $countryPhoneCode = null, ?string $idempotencyKey = null): array
    {
        $message = "Le transfert Ref: {$reference} de " . $this->money($amount, $currency) . " a ete annule. "
            . "Presentez-vous a votre agent pour le remboursement.";

        return $this->sendSms($phone, $message, $countryPhoneCode, $idempotencyKey);
    }

    private function money(float $amount, string $currency): string
    {
        return number_format($amount, $amount == floor($amount) ? 0 : 2, ',', ' ') . ' ' . $currency;
    }

    /**
     * Formate le numéro de téléphone au format international (sans +).
     * Ex: 781370871 + indicatif +221 → 221781370871
     * Ex: +221781370871 → 221781370871
     *
     * @param string $phone
     * @param string|null $countryPhoneCode Indicatif pays (ex: +221, +33, 221)
     * @return string
     */
    protected function formatPhone(string $phone, ?string $countryPhoneCode = null): string
    {
        // Nettoyer : garder uniquement les chiffres
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // Nettoyer l'indicatif : supprimer le + et garder les chiffres
        if ($countryPhoneCode)
        {
            $countryPhoneCode = preg_replace('/[^0-9]/', '', $countryPhoneCode);
        }

        // Si le numéro commence par 00 (format international), enlever le 00
        if (str_starts_with($phone, '00'))
        {
            $phone = substr($phone, 2);
        }

        // Si le numéro commence par 0, supprimer le 0
        if (str_starts_with($phone, '0'))
        {
            $phone = substr($phone, 1);
        }

        // Si le numéro commence déjà par l'indicatif, il est déjà complet
        if ($countryPhoneCode && str_starts_with($phone, $countryPhoneCode))
        {
            return $phone;
        }

        // Sinon, préfixer avec l'indicatif du pays
        if ($countryPhoneCode)
        {
            $phone = $countryPhoneCode . $phone;
        }

        return $phone;
    }
}
