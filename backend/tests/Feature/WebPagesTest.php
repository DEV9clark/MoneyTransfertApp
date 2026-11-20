<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class WebPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_back_office_pages_render_for_logged_in_agent()
    {
        $user = User::create(['name' => 'Agent', 'email' => 'a@test.com', 'password' => bcrypt('password'), 'role' => 'agent']);
        Wallet::create(['uuid' => (string) Str::uuid(), 'user_id' => $user->id, 'currency' => 'XOF', 'balance' => 0]);

        $this->actingAs($user);
        foreach (['/dashboard', '/transactions', '/clients', '/tariffs'] as $page) {
            $this->get($page)->assertOk();
        }

        // Modification des tarifs réservée à l'admin
        $this->postJson('/tariffs', ['type' => 'send', 'min_amount' => 0, 'max_amount' => 10, 'fee_amount' => 1])->assertStatus(403);

        // GraphQL via la session web : un agent ne voit que lui-même
        $res = $this->postJson('/graphql', ['query' => '{ users { email } countries { name } }']);
        $res->assertOk()->assertJsonPath('data.users.0.email', 'a@test.com')->assertJsonCount(1, 'data.users');
    }

    public function test_deactivated_user_cannot_log_in_on_web()
    {
        User::create(['name' => 'Off', 'email' => 'off@test.com', 'password' => bcrypt('password'), 'role' => 'agent', 'is_active' => false]);
        $this->postJson('/login', ['email' => 'off@test.com', 'password' => 'password'])->assertStatus(401);
    }
}
