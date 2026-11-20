<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\Ledger;
use App\Models\Tariff;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Services\SmsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TransactionTest extends TestCase
{
    use RefreshDatabase;

    protected FakeSms $sms;
    protected User $admin;
    protected User $sender;
    protected User $payer;
    protected Country $senegal;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sms = new FakeSms();
        $this->app->instance(SmsService::class, $this->sms);

        $this->admin = $this->makeUser('admin@test.com', 'admin', 0);
        $this->sender = $this->makeUser('sender@test.com', 'agent', 100000);
        $this->payer = $this->makeUser('payer@test.com', 'agent', 0);

        $this->senegal = Country::create(['name' => 'Senegal', 'code' => 'SN', 'currency' => 'XOF', 'phone_code' => '221']);

        Tariff::create(['type' => 'send', 'min_amount' => 1, 'max_amount' => 5000, 'fee_amount' => 500]);
        Tariff::create(['type' => 'send', 'min_amount' => 5001, 'max_amount' => 50000, 'fee_amount' => 1000]);
    }

    protected function makeUser(string $email, string $role, float $balance): User
    {
        $user = User::create([
            'name' => ucfirst(strtok($email, '@')),
            'email' => $email,
            'password' => bcrypt('password'),
            'role' => $role,
        ]);
        Wallet::create([
            'uuid' => (string) Str::uuid(), 'user_id' => $user->id, 'type' => 'agent',
            'currency' => 'XOF', 'balance' => $balance,
        ]);

        return $user->fresh();
    }

    protected function sendPayload(array $overrides = []): array
    {
        return array_merge([
            'client_name' => 'Awa Diop',
            'client_phone' => '771112233',
            'sender_id_type' => 'cni',
            'sender_id_number' => '1234567890123',
            'amount' => 10000,
            'destination_country_id' => $this->senegal->id,
            'recipient_name' => 'Moussa Fall',
            'recipient_phone' => '781370871',
        ], $overrides);
    }

    protected function send(array $overrides = [])
    {
        Sanctum::actingAs($this->sender);

        return $this->postJson('/api/transfer', $this->sendPayload($overrides));
    }

    public function test_full_flow_send_verify_withdraw_with_balanced_ledger_and_receipts()
    {
        $res = $this->send()->assertStatus(201);
        $res->assertJsonMissing(['code']);
        $this->assertArrayNotHasKey('withdrawal_code_hash', $res->json('data'));

        $uuid = $res->json('data.uuid');
        $tx = Transaction::where('uuid', $uuid)->first();

        // Agent expéditeur débité du montant + frais, frais au compte de commissions.
        $this->assertEquals(100000 - 11000, $this->sender->wallet->fresh()->balance);
        $this->assertEquals(1000, Wallet::where('type', 'revenue')->where('currency', 'XOF')->first()->balance);
        $this->assertStringStartsWith('DEP-', $tx->sender_receipt_number);

        // Code envoyé au bénéficiaire avec l'indicatif du pays de destination.
        $code = $this->sms->lastCode();
        $this->assertNotNull($code);
        $this->assertSame(8, strlen($code));
        $this->assertSame('221781370871', $this->sms->sent[0]['phone']);
        $this->assertNotSame($code, $tx->reference);

        // Reçu d'envoi (PDF), puis duplicata journalisé.
        $pdf = $this->get("/api/transactions/{$uuid}/receipt/deposit");
        $pdf->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
        $this->get("/api/transactions/{$uuid}/receipt/deposit")->assertOk();
        $this->assertSame(2, \App\Models\AuditLog::where('action', 'receipt.deposit')->count());

        // Pas de reçu de retrait avant paiement.
        $this->get("/api/transactions/{$uuid}/receipt/withdrawal")->assertStatus(409);

        // Retrait : vérification puis paiement avec pièce d'identité.
        Sanctum::actingAs($this->payer);
        $this->postJson('/api/transactions/verify', ['code' => $code])
            ->assertOk()->assertJsonPath('data.recipient_name', 'Moussa Fall');

        $this->postJson('/api/withdraw', ['code' => $code])->assertStatus(422); // pièce obligatoire

        $paid = $this->postJson('/api/withdraw', [
            'code' => $code, 'recipient_id_type' => 'passport', 'recipient_id_number' => 'A01234567',
        ])->assertOk();

        $this->assertEquals(10000, $this->payer->wallet->fresh()->balance);
        $tx->refresh();
        $this->assertSame('completed', $tx->status);
        $this->assertNull($tx->withdrawal_code_hash);
        $this->assertStringStartsWith('RET-', $tx->payout_receipt_number);
        $this->assertSame('A01234567', $tx->recipient_id_number);

        // Grand livre équilibré : -11000 (expéditeur) + 1000 (frais) + 10000 (payeur) = 0
        $this->assertEquals(0, Ledger::where('transaction_id', $tx->id)->sum('amount'));

        $pdf = $this->get("/api/transactions/{$uuid}/receipt/withdrawal");
        $pdf->assertOk();
        $this->assertStringStartsWith('%PDF', $pdf->getContent());

        // Le code ne peut pas servir deux fois.
        $this->postJson('/api/withdraw', [
            'code' => $code, 'recipient_id_type' => 'cni', 'recipient_id_number' => 'X',
        ])->assertStatus(404);
    }

    public function test_insufficient_funds_is_rejected_without_side_effects()
    {
        $this->send(['amount' => 50000])->assertStatus(201);
        $this->send(['amount' => 50000])->assertStatus(422)->assertJsonFragment(['message' => 'Solde insuffisant. Requis : 51 000 XOF, disponible : 49 000 XOF.']);
        $this->assertSame(1, Transaction::count());
    }

    public function test_amount_above_tariff_ceiling_is_refused_and_gap_uses_lower_tranche()
    {
        $this->send(['amount' => 60000])->assertStatus(422);

        Sanctum::actingAs($this->sender);
        $this->postJson('/api/transactions/calculate-fees', ['amount' => 5000.5])
            ->assertOk()->assertJson(['fee' => 500]);
    }

    public function test_wrong_codes_are_rate_limited()
    {
        $this->send()->assertStatus(201);
        Sanctum::actingAs($this->payer);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/transactions/verify', ['code' => '00000000'])->assertStatus(404);
        }
        $this->postJson('/api/transactions/verify', ['code' => $this->sms->lastCode()])->assertStatus(429);
    }

    public function test_cancel_refunds_sender_and_fee()
    {
        $uuid = $this->send()->json('data.uuid');

        Sanctum::actingAs($this->payer);
        $this->postJson("/api/transactions/{$uuid}/cancel", ['reason' => 'test'])->assertStatus(403);

        Sanctum::actingAs($this->sender);
        $this->postJson("/api/transactions/{$uuid}/cancel", ['reason' => 'Erreur de saisie'])->assertOk();

        $this->assertEquals(100000, $this->sender->wallet->fresh()->balance);
        $this->assertEquals(0, Wallet::where('type', 'revenue')->first()->balance);

        Sanctum::actingAs($this->payer);
        $this->postJson('/api/transactions/verify', ['code' => $this->sms->lastCode()])->assertStatus(404);
    }

    public function test_expired_transfers_are_refunded_by_command()
    {
        $uuid = $this->send()->json('data.uuid');
        $code = $this->sms->lastCode();
        Transaction::where('uuid', $uuid)->update(['expires_at' => now()->subDay()]);

        Sanctum::actingAs($this->payer);
        $this->postJson('/api/transactions/verify', ['code' => $code])->assertStatus(410);

        $this->artisan('transfers:expire')->assertExitCode(0);
        $this->assertSame('expired', Transaction::where('uuid', $uuid)->value('status'));
        $this->assertEquals(100000, $this->sender->wallet->fresh()->balance);
    }

    public function test_agents_only_see_their_own_transactions_and_receipts()
    {
        $uuid = $this->send()->json('data.uuid');
        $other = $this->makeUser('other@test.com', 'agent', 0);

        Sanctum::actingAs($other);
        $this->getJson('/api/transactions')->assertOk()->assertJsonCount(0, 'data');
        $this->get("/api/transactions/{$uuid}/receipt/deposit")->assertStatus(403);
        $this->getJson("/api/transactions/{$uuid}")->assertStatus(404);

        Sanctum::actingAs($this->admin);
        $this->get("/api/transactions/{$uuid}/receipt/deposit")->assertOk();
    }

    public function test_admin_topup_and_agent_management()
    {
        Sanctum::actingAs($this->sender);
        $this->postJson("/api/admin/agents/{$this->payer->id}/topup", ['amount' => 5000])->assertStatus(403);

        Sanctum::actingAs($this->admin);
        $this->postJson("/api/admin/agents/{$this->payer->id}/topup", ['amount' => 5000])->assertOk();
        $this->assertEquals(5000, $this->payer->wallet->fresh()->balance);

        $this->postJson('/api/admin/agents', [
            'name' => 'Nouvel Agent', 'email' => 'new@test.com', 'password' => 'secret123',
        ])->assertStatus(201)->assertJsonPath('wallet.currency', 'XOF');

        $this->patchJson("/api/admin/agents/{$this->sender->id}/status", ['is_active' => false])->assertOk();
        $this->postJson('/api/login', ['email' => 'sender@test.com', 'password' => 'password'])->assertStatus(403);
    }

    public function test_dangerous_public_endpoints_are_closed()
    {
        $this->getJson('/api/debug-data')->assertStatus(404);
        $this->getJson('/api/fix-fees')->assertStatus(404);
        $this->postJson('/api/register', ['name' => 'x', 'email' => 'x@x.com', 'password' => '12345678'])->assertStatus(404);
        $this->getJson('/api/transactions')->assertStatus(401);

        $gql = $this->postJson('/graphql', ['query' => '{ transactions { reference } }']);
        $this->assertNotEmpty($gql->json('errors'));
        $this->assertNull($gql->json('data.transactions'));
    }

    public function test_login_is_throttled()
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/login', ['email' => 'sender@test.com', 'password' => 'bad'])->assertStatus(401);
        }
        $this->postJson('/api/login', ['email' => 'sender@test.com', 'password' => 'password'])->assertStatus(429);
    }

    public function test_daily_limit_per_client()
    {
        config(['transfer.daily_limit_per_client' => 15000]);
        $this->send(['amount' => 10000])->assertStatus(201);
        $this->send(['amount' => 10000])->assertStatus(422);
    }
}
