<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Country;
use App\Models\Ledger;
use App\Models\Tariff;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Services\SmsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Parcours de l'application client : inscription par SMS + PIN, pré-envoi,
 * encaissement en agence, suivi et reçus. Les requêtes utilisent de VRAIS jetons
 * Bearer (pas Sanctum::actingAs) pour vérifier aussi la séparation client / agent.
 */
class ClientAppTest extends TestCase
{
    use RefreshDatabase;

    protected FakeSms $sms;
    protected Country $senegal;
    protected User $agent;
    protected User $payer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sms = new FakeSms();
        $this->app->instance(SmsService::class, $this->sms);

        $this->senegal = Country::create(['name' => 'Senegal', 'code' => 'SN', 'currency' => 'XOF', 'phone_code' => '221']);
        User::create(['name' => 'Admin', 'email' => 'admin@test.com', 'password' => bcrypt('password'), 'role' => 'admin']);
        $this->agent = $this->makeAgent('agent@test.com', 100000);
        $this->payer = $this->makeAgent('payer@test.com', 0);

        Tariff::create(['type' => 'send', 'min_amount' => 1, 'max_amount' => 50000, 'fee_amount' => 1000]);
    }

    private function makeAgent(string $email, float $balance): User
    {
        $u = User::create(['name' => strtok($email, '@'), 'email' => $email, 'password' => bcrypt('password'), 'role' => 'agent']);
        Wallet::create(['uuid' => (string) Str::uuid(), 'user_id' => $u->id, 'type' => 'agent', 'currency' => 'XOF', 'balance' => $balance]);

        return $u->fresh();
    }

    private function bearer(string $token): array
    {
        // Le garde d'authentification garde l'utilisateur en mémoire entre deux requêtes d'un même test.
        $this->app['auth']->forgetGuards();

        return ['Authorization' => 'Bearer ' . $token, 'Accept' => 'application/json'];
    }

    private function agentToken(string $email): string
    {
        $this->app['auth']->forgetGuards();

        return $this->postJson('/api/login', ['email' => $email, 'password' => 'password'])->json('access_token');
    }

    private function registerClient(string $phone = '+221 77 111 22 33', string $pin = '2468'): string
    {
        $this->postJson('/api/client/check', ['country_id' => $this->senegal->id, 'phone' => $phone])
            ->assertOk()->assertJson(['registered' => false]);

        $this->postJson('/api/client/otp', ['country_id' => $this->senegal->id, 'phone' => $phone, 'purpose' => 'register'])
            ->assertOk()->assertJsonMissing(['debug_code']);
        $otp = $this->sms->lastOtp();
        $this->assertNotNull($otp);

        return $this->postJson('/api/client/register', [
            'country_id' => $this->senegal->id, 'phone' => $phone, 'otp' => $otp,
            'name' => 'Awa Diop', 'pin' => $pin, 'pin_confirmation' => $pin,
        ])->assertStatus(201)->json('access_token');
    }

    public function test_full_client_journey_preorder_paid_at_agent_then_withdrawn()
    {
        $token = $this->registerClient();
        $client = Client::first();
        $this->assertSame('771112233', $client->phone); // numéro national normalisé
        $this->assertNotSame('2468', $client->pin_hash);

        // Devis et pré-envoi
        $this->postJson('/api/client/quote', ['amount' => 20000], $this->bearer($token))
            ->assertOk()->assertJson(['fee' => 1000, 'total' => 21000]);

        $res = $this->postJson('/api/client/transfers', [
            'amount' => 20000, 'destination_country_id' => $this->senegal->id,
            'recipient_name' => 'Moussa Fall', 'recipient_phone' => '78 137 08 71',
        ], $this->bearer($token))->assertStatus(201);

        $uuid = $res->json('data.uuid');
        $paymentCode = $res->json('data.payment_code');
        $this->assertSame('awaiting_payment', $res->json('data.status'));
        $this->assertSame(6, strlen($paymentCode));
        $this->assertEquals(100000, $this->agent->wallet->fresh()->balance); // aucun argent n'a bougé
        $this->assertNull($this->sms->lastCode()); // pas encore de code de retrait

        // Pas de reçu avant paiement
        $this->get("/api/client/transfers/{$uuid}/receipt/deposit", $this->bearer($token))->assertStatus(409);

        // Agent : recherche puis encaissement
        $agentToken = $this->agentToken('agent@test.com');
        $this->postJson('/api/preorders/lookup', ['payment_code' => strtolower($paymentCode)], $this->bearer($agentToken))
            ->assertOk()->assertJsonPath('data.total', 21000)->assertJsonPath('data.client_name', 'Awa Diop');

        $this->postJson('/api/preorders/confirm', [
            'payment_code' => $paymentCode, 'sender_id_type' => 'cni', 'sender_id_number' => '1234567890123',
        ], $this->bearer($agentToken))->assertOk();

        $this->assertEquals(79000, $this->agent->wallet->fresh()->balance);
        $tx = Transaction::where('uuid', $uuid)->first();
        $this->assertSame('pending', $tx->status);
        $this->assertSame('client_app', $tx->channel);
        $this->assertSame('1234567890123', $client->fresh()->id_number);
        $code = $this->sms->lastCode();
        $this->assertNotNull($code);
        $this->assertContains('221781370871', array_column($this->sms->sent, 'phone'));

        // Chaque SMS porte une clé anti-doublon (Idempotency-Key DexNotify)
        $keys = array_column($this->sms->sent, 'key');
        $this->assertNotEmpty(preg_grep('/^mt-otp-\d+$/', $keys));
        $this->assertNotEmpty(preg_grep('/^mt-wcode-' . $uuid . '-[0-9a-f]{12}$/', $keys));
        $this->assertContains('mt-confirm-' . $uuid, $keys);

        // Encaisser deux fois est impossible
        $this->postJson('/api/preorders/confirm', ['payment_code' => $paymentCode], $this->bearer($agentToken))->assertStatus(409);

        // Le client voit le statut et son reçu d'envoi
        $this->getJson("/api/client/transfers/{$uuid}", $this->bearer($token))
            ->assertOk()->assertJsonPath('data.status', 'pending')->assertJsonPath('data.payment_code', null);
        $pdf = $this->get("/api/client/transfers/{$uuid}/receipt/deposit", $this->bearer($token));
        $pdf->assertOk();
        $this->assertStringStartsWith('%PDF', $pdf->getContent());

        // Retrait au guichet par un autre agent
        $payerToken = $this->agentToken('payer@test.com');
        $this->postJson('/api/withdraw', [
            'code' => $code, 'recipient_id_type' => 'passport', 'recipient_id_number' => 'A0001',
        ], $this->bearer($payerToken))->assertOk();

        $this->getJson('/api/client/transfers', $this->bearer($token))
            ->assertOk()->assertJsonPath('data.0.status', 'completed')->assertJsonPath('data.0.has_withdrawal_receipt', true);
        $this->assertEquals(0, Ledger::where('transaction_id', $tx->id)->sum('amount'));

        // Bénéficiaires récents
        $this->getJson('/api/client/beneficiaries', $this->bearer($token))
            ->assertOk()->assertJsonPath('data.0.phone', '781370871');
    }

    public function test_tokens_are_not_interchangeable_between_clients_and_agents()
    {
        $clientToken = $this->registerClient();
        $agentToken = $this->agentToken('agent@test.com');

        // Un client ne peut pas utiliser les routes agents…
        $this->getJson('/api/balance', $this->bearer($clientToken))->assertStatus(403);
        $this->getJson('/api/transactions', $this->bearer($clientToken))->assertStatus(403);
        $this->postJson('/api/preorders/lookup', ['payment_code' => 'X'], $this->bearer($clientToken))->assertStatus(403);
        // …ni un agent les routes clients.
        $this->getJson('/api/client/transfers', $this->bearer($agentToken))->assertStatus(403);

        // Les routes agents fonctionnent bien avec un vrai jeton Bearer (plus écrasées par les routes web).
        $this->getJson('/api/transactions', $this->bearer($agentToken))->assertOk();
        $this->getJson('/api/clients/search?query=Awa', $this->bearer($agentToken))->assertOk();
    }

    public function test_pin_login_lockout_and_reset()
    {
        $this->registerClient('771112233', '2468');
        $login = ['country_id' => $this->senegal->id, 'phone' => '771112233'];

        $this->postJson('/api/client/login', $login + ['pin' => '2468'])->assertOk()->assertJsonStructure(['access_token']);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/client/login', $login + ['pin' => '9753'])->assertStatus(401);
        }
        $this->postJson('/api/client/login', $login + ['pin' => '2468'])->assertStatus(423);
        $this->app['cache']->flush(); // réinitialise la limite de requêtes (le verrou PIN, lui, est en base)

        // PIN oublié : code SMS puis nouveau PIN
        $this->postJson('/api/client/otp', $login + ['purpose' => 'reset_pin'])->assertOk();
        $this->postJson('/api/client/pin/reset', $login + [
            'otp' => $this->sms->lastOtp(), 'pin' => '1357', 'pin_confirmation' => '1357',
        ])->assertOk();

        $this->postJson('/api/client/login', $login + ['pin' => '1357'])->assertOk();
    }

    public function test_registration_rules()
    {
        $base = ['country_id' => $this->senegal->id, 'phone' => '770000001'];

        // Mauvais code SMS
        $this->postJson('/api/client/otp', $base + ['purpose' => 'register'])->assertOk();
        $this->postJson('/api/client/register', $base + [
            'otp' => '000000', 'name' => 'Test', 'pin' => '2468', 'pin_confirmation' => '2468',
        ])->assertStatus(422);

        // PIN trop simple
        $this->postJson('/api/client/register', $base + [
            'otp' => $this->sms->lastOtp(), 'name' => 'Test', 'pin' => '1234', 'pin_confirmation' => '1234',
        ])->assertStatus(422)->assertJsonFragment(['message' => 'Code PIN trop simple. Choisissez-en un autre.']);

        // Un client créé au guichet récupère son compte (et son historique) en s'inscrivant
        $walkIn = Client::create(['uuid' => (string) Str::uuid(), 'name' => 'Client Guichet', 'phone' => '779998877']);
        $this->postJson('/api/client/otp', ['country_id' => $this->senegal->id, 'phone' => '779998877', 'purpose' => 'register'])->assertOk();
        $this->postJson('/api/client/register', [
            'country_id' => $this->senegal->id, 'phone' => '779998877', 'otp' => $this->sms->lastOtp(),
            'name' => 'Autre nom', 'pin' => '8642', 'pin_confirmation' => '8642',
        ])->assertStatus(201)->assertJsonPath('client.uuid', $walkIn->uuid)->assertJsonPath('client.name', 'Client Guichet');
        $this->assertSame(1, Client::where('phone', '779998877')->count());

        // Plus d'inscription possible une fois le compte créé
        $this->postJson('/api/client/otp', ['country_id' => $this->senegal->id, 'phone' => '779998877', 'purpose' => 'register'])->assertStatus(409);
    }

    public function test_client_can_cancel_unpaid_preorder_only_and_expired_preorders_cannot_be_paid()
    {
        $token = $this->registerClient();
        $payload = ['amount' => 5000, 'destination_country_id' => $this->senegal->id, 'recipient_name' => 'Fatou', 'recipient_phone' => '776667788'];

        $first = $this->postJson('/api/client/transfers', $payload, $this->bearer($token))->json('data');
        $this->postJson("/api/client/transfers/{$first['uuid']}/cancel", [], $this->bearer($token))
            ->assertOk()->assertJsonPath('data.status', 'cancelled');

        $second = $this->postJson('/api/client/transfers', $payload, $this->bearer($token))->json('data');
        Transaction::where('uuid', $second['uuid'])->update(['expires_at' => now()->subHour()]);

        $agentToken = $this->agentToken('agent@test.com');
        $this->postJson('/api/preorders/confirm', ['payment_code' => $second['payment_code']], $this->bearer($agentToken))->assertStatus(410);

        $this->artisan('transfers:expire')->assertExitCode(0);
        $this->assertSame('expired', Transaction::where('uuid', $second['uuid'])->value('status'));

        // Un client ne voit pas les transferts d'un autre
        $other = $this->registerClient('770001122', '8642');
        $this->getJson("/api/client/transfers/{$first['uuid']}", $this->bearer($other))->assertStatus(404);
    }

    public function test_open_preorders_are_capped()
    {
        config(['transfer.max_open_preorders' => 2]);
        $token = $this->registerClient();
        $payload = ['amount' => 1000, 'destination_country_id' => $this->senegal->id, 'recipient_name' => 'Fatou', 'recipient_phone' => '776667788'];

        $this->postJson('/api/client/transfers', $payload, $this->bearer($token))->assertStatus(201);
        $this->postJson('/api/client/transfers', $payload, $this->bearer($token))->assertStatus(201);
        $this->postJson('/api/client/transfers', $payload, $this->bearer($token))->assertStatus(422);
    }
}
