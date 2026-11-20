<?php

namespace Tests\Feature;

use App\Services\SmsService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Prestataires SMS : DexNotify en principal, Dexchange en secours.
 * Les appels HTTP sont simulés (Http::fake) : aucun SMS réel n'est envoyé.
 */
class SmsDriversTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.sms.drivers' => 'dexnotify,dexchange',
            'services.dexnotify.url' => 'https://dexnotify.test/api',
            'services.dexnotify.api_key' => 'dxn_test_key',
            'services.dexnotify.sender' => 'DEXCHANGE',
            'services.dexchange.api_key' => 'legacy_key',
            'services.dexchange.endpoint' => 'https://legacy.test/send',
        ]);
    }

    public function test_dexnotify_is_used_with_api_key_international_number_and_idempotency_key()
    {
        Http::fake(['dexnotify.test/*' => Http::response(['data' => ['id' => 'msg_123', 'status' => 'queued']], 201)]);

        $result = app(SmsService::class)->sendSms('77 123 45 67', 'Bonjour', '221', 'mt-otp-42');

        $this->assertTrue($result['success']);
        $this->assertSame('dexnotify', $result['driver']);
        $this->assertSame('msg_123', $result['message_id']);

        Http::assertSent(function (Request $r) {
            return $r->url() === 'https://dexnotify.test/api/v1/sms/messages'
                && $r->hasHeader('x-api-key', 'dxn_test_key')
                && $r->hasHeader('Idempotency-Key', 'mt-otp-42')
                && $r['to'] === '+221771234567'
                && $r['sender'] === 'DEXCHANGE'
                && $r['content'] === 'Bonjour';
        });
        Http::assertSentCount(1);
    }

    public function test_falls_back_to_dexchange_when_dexnotify_fails()
    {
        Http::fake([
            'dexnotify.test/*' => Http::response(['message' => 'Service indisponible'], 503),
            'legacy.test/*' => Http::response(['status' => 'ok'], 200),
        ]);

        $result = app(SmsService::class)->sendSms('771234567', 'Code 123456', '221');

        $this->assertTrue($result['success']);
        $this->assertSame('dexchange', $result['driver']);
        Http::assertSent(fn (Request $r) => $r->url() === 'https://legacy.test/send'
            && $r->hasHeader('Authorization', 'Bearer legacy_key')
            && $r['number'] === ['221771234567']);
    }

    public function test_reports_failure_when_every_provider_fails_or_none_is_configured()
    {
        Http::fake(['*' => Http::response(['message' => 'Clé invalide'], 401)]);
        $this->assertFalse(app(SmsService::class)->sendSms('771234567', 'x', '221')['success']);

        Http::fake();
        config(['services.dexnotify.api_key' => null, 'services.dexchange.api_key' => null]);
        $result = app(SmsService::class)->sendSms('771234567', 'x', '221');
        $this->assertFalse($result['success']);
        $this->assertSame('none', $result['driver']);
    }

    public function test_driver_order_is_configurable()
    {
        Http::fake(['*' => Http::response([], 200)]);
        config(['services.sms.drivers' => 'dexchange']);

        $this->assertSame('dexchange', app(SmsService::class)->sendSms('771234567', 'x', '221')['driver']);
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'dexnotify'));
    }

    public function test_whatsapp_copy_of_withdrawal_code_only_when_configured()
    {
        Http::fake(['dexnotify.test/*' => Http::response(['id' => 'wa_1'], 201)]);
        $sms = app(SmsService::class);

        $this->assertNull($sms->sendWithdrawalCodeWhatsApp('771234567', '12345678', 'MT-REF', 15000, 'XOF', 'Awa', '221'));
        Http::assertNothingSent();

        config([
            'services.dexnotify.whatsapp_account_id' => '6750abc',
            'services.dexnotify.whatsapp_withdrawal_template' => 'code_retrait',
        ]);
        $result = $sms->sendWithdrawalCodeWhatsApp('771234567', '12345678', 'MT-REF', 15000, 'XOF', 'Awa', '221', 'mt-wcode-x');

        $this->assertTrue($result['success']);
        Http::assertSent(fn (Request $r) => $r->url() === 'https://dexnotify.test/api/v1/whatsapp/messages'
            && $r['kind'] === 'template'
            && $r['accountId'] === '6750abc'
            && $r['name'] === 'code_retrait'
            && $r['to'] === '+221771234567'
            && $r['variables'] === ['15 000 XOF', 'Awa', 'MT-REF', '12345678']
            && $r->hasHeader('Idempotency-Key', 'wa-mt-wcode-x'));
    }
}
