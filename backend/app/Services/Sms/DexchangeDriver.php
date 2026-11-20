<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Http;

/**
 * Ancienne API Dexchange SMS (conservée comme prestataire de secours).
 */
class DexchangeDriver implements SmsDriver
{
    public function name(): string
    {
        return 'dexchange';
    }

    public function isConfigured(): bool
    {
        return (string) config('services.dexchange.api_key') !== '';
    }

    public function send(string $msisdn, string $content, ?string $idempotencyKey = null): SmsResult
    {
        try {
            $response = Http::withToken((string) config('services.dexchange.api_key'))
                ->acceptJson()
                ->timeout(10)
                ->post((string) config('services.dexchange.endpoint', 'https://api.dexchange-sms.com/api/v1/send/sms'), [
                    'signature' => (string) config('services.dexchange.signature', 'DEXCHANGE'),
                    'content' => $content,
                    'number' => [$msisdn],
                ]);
        } catch (\Throwable $e) {
            return new SmsResult(false, $this->name(), null, 'Connexion impossible : ' . $e->getMessage());
        }

        $body = $response->json() ?? [];

        return $response->successful()
            ? new SmsResult(true, $this->name(), null, null, $response->status())
            : new SmsResult(false, $this->name(), null, (is_array($body) ? ($body['message'] ?? null) : null) ?? 'HTTP ' . $response->status(), $response->status());
    }
}
