<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class SmsService
{
    /**
     * API key Dexchange.
     */
    protected string $apiKey;

    /**
     * Signature de l'expéditeur (DEXCHANGE).
     */
    protected string $signature;

    /**
     * Endpoint de l'API Dexchange.
     */
    protected string $endpoint;

    public function __construct()
    {
        $this->apiKey = config('services.dexchange.api_key');
        $this->signature = config('services.dexchange.signature', 'DEXCHANGE');
        $this->endpoint = config('services.dexchange.endpoint', 'https://api.dexchange-sms.com/api/v1/send/sms');
    }

    /**
     * Envoie un SMS via Dexchange.
     *
     * @param string $phone     Numéro de téléphone (format international, ex: 221771234567)
     * @param string $message   Contenu du message
     * @param string|null $countryPhoneCode Indicatif pays (ex: +221, 221) pour préfixer si absent
     * @return array            Réponse de l'API
     * @throws Exception
     */
    public function sendSms(string $phone, string $message, ?string $countryPhoneCode = null): array
    {
        // Validation du numéro
        $phone = $this->formatPhone($phone, $countryPhoneCode);

        if (empty($this->apiKey))
        {
            Log::warning('[SmsService] Aucune clé API Dexchange configurée. SMS non envoyé.', [
                'phone'   => $phone,
                'message' => $message,
            ]);
            return [
                'success' => false,
                'message' => 'API key non configurée (DEXCHANGE_API_KEY)',
            ];
        }

        $payload = [
            'signature' => $this->signature,
            'content' => $message,
            'number' => [$phone],
        ];

        try
        {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post($this->endpoint, $payload);

            $body = $response->json();

            if ($response->successful())
            {
                Log::info('[SmsService] SMS envoyé avec succès', [
                    'phone' => $phone,
                    'response' => $body,
                ]);

                return [
                    'success' => true,
                    'data' => $body,
                ];
            }

            Log::error('[SmsService] Échec envoi SMS', [
                'phone' => $phone,
                'status' => $response->status(),
                'response' => $body,
            ]);

            return [
                'success' => false,
                'message' => $body['message'] ?? 'Erreur API Dexchange (HTTP ' . $response->status() . ')',
            ];
        }
        catch (Exception $e)
        {
            Log::error('[SmsService] Exception lors de l\'envoi SMS', [
                'phone' => $phone,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Erreur de connexion à Dexchange : ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Envoie le code de retrait au client.
     */
    public function sendWithdrawalCode(string $phone, string $code, float $amount, string $currency, ?string $countryPhoneCode = null): array
    {
        $message = "Votre transfert de {$amount} {$currency} est prêt. "
            . "Utilisez le code de retrait : {$code}. "
            . "Partagez ce code avec l'agent pour recevoir vos fonds. "
            . "Merci de votre confiance.";

        return $this->sendSms($phone, $message, $countryPhoneCode);
    }

    /**
     * Envoie la confirmation de paiement au client.
     */
    public function sendPaymentConfirmation(string $phone, string $reference, float $amount, string $currency, ?string $countryPhoneCode = null): array
    {
        $message = "Paiement effectué avec succès ! "
            . "Réf: {$reference}, Montant: {$amount} {$currency}. "
            . "Merci de votre confiance.";

        return $this->sendSms($phone, $message, $countryPhoneCode);
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
