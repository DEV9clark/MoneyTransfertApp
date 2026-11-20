<?php

namespace Tests\Feature;

use App\Services\SmsService;

/** SMS factice : enregistre les messages au lieu d'appeler Dexchange. */
class FakeSms extends SmsService
{
    public array $sent = [];

    public function sendSms(string $phone, string $message, ?string $countryPhoneCode = null, ?string $idempotencyKey = null): array
    {
        $this->sent[] = ['phone' => $this->formatPhone($phone, $countryPhoneCode), 'message' => $message, 'key' => $idempotencyKey];

        return ['success' => true];
    }

    public function lastCode(): ?string
    {
        foreach (array_reverse($this->sent) as $sms) {
            if (preg_match('/Code de retrait: (\d+)/', $sms['message'], $m)) {
                return $m[1];
            }
        }

        return null;
    }

    public function lastOtp(): ?string
    {
        foreach (array_reverse($this->sent) as $sms) {
            if (preg_match('/verification Money Transfer : (\d{6})/', $sms['message'], $m)) {
                return $m[1];
            }
        }

        return null;
    }
}
