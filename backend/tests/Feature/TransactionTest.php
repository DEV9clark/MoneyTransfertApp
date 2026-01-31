<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Wallet;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TransactionTest extends TestCase
{
    use RefreshDatabase;

    public function test_agent_client_transaction_flow()
    {
        // 1. Create Agents (Sender and Payout)
        $senderAgent = User::create([
            'name' => 'Sender Agent',
            'email' => 'sender@agent.com',
            'password' => bcrypt('password'),
        ]);
        $senderAgent->wallet()->create([
            'uuid' => Str::uuid(),
            'currency' => 'USD',
            'balance' => 1000.00,
        ]);

        $payoutAgent = User::create([
            'name' => 'Payout Agent',
            'email' => 'payout@agent.com',
            'password' => bcrypt('password'),
        ]);
        $payoutAgent->wallet()->create([
            'uuid' => Str::uuid(),
            'currency' => 'USD',
            'balance' => 500.00,
        ]);

        // 2. Login as Sender Agent
        $response = $this->postJson('/api/login', [
            'email' => 'sender@agent.com',
            'password' => 'password',
        ]);
        $response->assertStatus(200);
        $tokenSender = $response->json('access_token');

        // 3. Initiate Transfer (Agent -> Client)
        $response = $this->withHeader('Authorization', 'Bearer ' . $tokenSender)
            ->postJson('/api/transfer', [
                'client_name' => 'John Doe',
                'client_phone' => '+1234567890',
                'amount' => 100.00,
            ]);
        
        $response->assertStatus(200);
        $code = $response->json('code');

        // Verify Client Created
        $this->assertDatabaseHas('clients', [
            'phone' => '+1234567890',
            'name' => 'John Doe',
        ]);

        // Verify Sender Balance Deducted
        $this->assertEquals(900.00, $senderAgent->wallet->fresh()->balance);

        // 4. Login as Payout Agent
        $response = $this->postJson('/api/login', [
            'email' => 'payout@agent.com',
            'password' => 'password',
        ]);
        $tokenPayout = $response->json('access_token');

        // 5. Withdraw (Client -> Agent B)
        $response = $this->actingAs($payoutAgent)
            ->postJson('/api/withdraw', [
                'code' => $code,
            ]);
        
        $response->assertStatus(200);

        // Verify Payout Agent Balance Credited
        $this->assertEquals(600.00, $payoutAgent->wallet->fresh()->balance);

        // Verify Transaction Completed and Linked to Payout Wallet
        $this->assertDatabaseHas('transactions', [
            'reference' => $code,
            'status' => 'completed',
            'payout_wallet_id' => $payoutAgent->wallet->id,
        ]);
    }
}
