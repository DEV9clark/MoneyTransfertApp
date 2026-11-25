<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Http;

/**
 * DexNotify : POST {DEXNOTIFY_URL}/v1/sms/messages, authentification par en-tête x-api-key.
 * L'en-tête Idempotency-Key évite tout doublon si un envoi est rejoué.
 */
class DexNotifyDriver implements SmsDriver
{
    public function name(): string
    {
        return 'dexnotify';
    }

    public function isConfigured(): bool
    {
        return (string) config('services.dexnotify.api_key') !== '';
    }

    public function send(string $msisdn, string $content, ?string $idempotencyKey = null): SmsResult
    {
        $headers = ['x-api-key' => (string) config('services.dexnotify.api_key')];
        if ($idempotencyKey) {
            $headers['Idempotency-Key'] = substr($idempotencyKey, 0, 255);
        }

        try {
            $response = Http::withHeaders($headers)
                ->acceptJson()
                ->timeout((int) config('services.dexnotify.timeout', 10))
                ->post($this->url('/v1/sms/messages'), [
                    'sender' => (string) config('services.dexnotify.sender', 'DEXCHANGE'),
                    'to' => '+' . $msisdn,
                    'content' => $content,
                ]);
        } catch (\Throwable $e) {
            return new SmsResult(false, $this->name(), null, 'Connexion impossible : ' . $e->getMessage());
        }

        $body = $response->json() ?? [];

        if ($response->successful()) {
            return new SmsResult(true, $this->name(), self::extractId($body), null, $response->status());
        }

        return new SmsResult(
            false,
            $this->name(),
            null,
            self::extractError($body, $response->status()),
            $response->status()
        );
    }

    /**
     * Message WhatsApp à partir d'un modèle validé par Meta (obligatoire pour écrire en premier à un client).
     */
    public function sendWhatsAppTemplate(string $msisdn, string $template, array $variables, ?string $idempotencyKey = null): SmsResult
    {
        $headers = ['x-api-key' => (string) config('services.dexnotify.api_key')];
        if ($idempotencyKey) {
            $headers['Idempotency-Key'] = substr($idempotencyKey, 0, 255);
        }

        try {
            $response = Http::withHeaders($headers)
                ->acceptJson()
                ->timeout((int) config('services.dexnotify.timeout', 10))
                ->post($this->url('/v1/whatsapp/messages'), [
                    'kind' => 'template',
                    'accountId' => (string) config('services.dexnotify.whatsapp_account_id'),
                    'to' => '+' . $msisdn,
                    'name' => $template,
                    'language' => (string) config('services.dexnotify.whatsapp_language', 'fr'),
                    'variables' => array_values(array_map('strval', $variables)),
                ]);
        } catch (\Throwable $e) {
            return new SmsResult(false, 'dexnotify-whatsapp', null, 'Connexion impossible : ' . $e->getMessage());
        }

        $body = $response->json() ?? [];

        return $response->successful()
            ? new SmsResult(true, 'dexnotify-whatsapp', self::extractId($body), null, $response->status())
            : new SmsResult(false, 'dexnotify-whatsapp', null, self::extractError($body, $response->status()), $response->status());
    }

    private function url(string $path): string
    {
        return rtrim((string) config('services.dexnotify.url', 'https://api.dexnotify.com/api'), '/') . $path;
    }

    private static function extractError($body, int $status): string
    {
        if (is_array($body)) {
            $err = $body['message'] ?? $body['error'] ?? null;
            if (is_string($err) && $err !== '') {
                return $err;
            }
            if (is_array($err) || is_object($err)) {
                return json_encode($err, JSON_UNESCAPED_UNICODE) ?: 'HTTP ' . $status;
            }
        }

        return 'HTTP ' . $status;
    }

    private static function extractId($body): ?string
    {
        if (!is_array($body)) {
            return null;
        }
        foreach ([$body, $body['data'] ?? []] as $node) {
            if (is_array($node)) {
                foreach (['id', '_id', 'messageId', 'message_id'] as $key) {
                    if (!empty($node[$key]) && is_scalar($node[$key])) {
                        return (string) $node[$key];
                    }
                }
            }
        }

        return null;
    }
}
