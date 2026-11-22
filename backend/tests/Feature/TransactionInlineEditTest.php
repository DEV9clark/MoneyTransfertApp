<?php

namespace Tests\Feature;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionInlineEditTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $transaction;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Créer un utilisateur pour l'authentification
        $this->user = User::factory()->create();
        
        // Créer une transaction de test
        $this->transaction = Transaction::factory()->create([
            'reference' => 'TXN001',
            'type' => 'envoi',
            'amount' => 50000,
            'currency' => 'XOF',
            'status' => 'pending'
        ]);
    }

    /** @test */
    public function it_can_update_a_transaction_field()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->patchJson("/api/transactions/{$this->transaction->id}", [
                'reference' => 'TXN002'
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Transaction mise à jour avec succès'
            ]);

        $this->assertDatabaseHas('transactions', [
            'id' => $this->transaction->id,
            'reference' => 'TXN002'
        ]);
    }

    /** @test */
    public function it_validates_field_data()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->patchJson("/api/transactions/{$this->transaction->id}", [
                'amount' => -100 // Montant négatif invalide
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false
            ]);
    }

    /** @test */
    public function it_returns_404_for_non_existent_transaction()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->patchJson("/api/transactions/99999", [
                'reference' => 'TXN003'
            ]);

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Transaction non trouvée'
            ]);
    }

    /** @test */
    public function it_requires_authentication()
    {
        $response = $this->patchJson("/api/transactions/{$this->transaction->id}", [
            'reference' => 'TXN004'
        ]);

        $response->assertStatus(401);
    }

    /** @test */
    public function it_can_update_nested_client_field()
    {
        // Créer un client associé
        $client = \App\Models\Client::factory()->create([
            'name' => 'Jean Dupont'
        ]);
        
        $this->transaction->update(['client_id' => $client->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->patchJson("/api/transactions/{$this->transaction->id}", [
                'client.name' => 'Marie Martin'
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('clients', [
            'id' => $client->id,
            'name' => 'Marie Martin'
        ]);
    }

    /** @test */
    public function it_can_bulk_update_transactions()
    {
        $transaction2 = Transaction::factory()->create();

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/transactions/bulk-update', [
                'updates' => [
                    [
                        'id' => $this->transaction->id,
                        'field' => 'status',
                        'value' => 'completed'
                    ],
                    [
                        'id' => $transaction2->id,
                        'field' => 'status',
                        'value' => 'completed'
                    ]
                ]
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true
            ]);

        $this->assertDatabaseHas('transactions', [
            'id' => $this->transaction->id,
            'status' => 'completed'
        ]);

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction2->id,
            'status' => 'completed'
        ]);
    }
}
