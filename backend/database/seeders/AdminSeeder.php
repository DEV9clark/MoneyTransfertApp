<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminSeeder extends Seeder
{
    /**
     * Crée le compte administrateur (et un agent de démo hors production).
     * Mot de passe : variable ADMIN_DEFAULT_PASSWORD, sinon un mot de passe aléatoire affiché une seule fois.
     */
    public function run()
    {
        $password = env('ADMIN_DEFAULT_PASSWORD') ?: Str::random(16);
        $created = false;

        $admin = User::firstOrCreate(
            ['email' => env('ADMIN_DEFAULT_EMAIL', 'admin@moneytransfer.com')],
            [
                'name' => 'Super Admin',
                'password' => Hash::make($password),
                'phone_number' => '+221700000000',
                'country' => 'Senegal',
                'role' => 'admin',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
        $created = $admin->wasRecentlyCreated;

        if (!$admin->wallet) {
            Wallet::create([
                'uuid' => (string) Str::uuid(),
                'user_id' => $admin->id,
                'type' => Wallet::TYPE_AGENT,
                'currency' => 'XOF',
                'balance' => 0, // rechargez via POST /api/admin/agents/{id}/topup
            ]);
        }

        if ($created && !env('ADMIN_DEFAULT_PASSWORD') && $this->command) {
            $this->command->warn("Admin créé : {$admin->email} / mot de passe : {$password}  (à changer immédiatement)");
        }

        if (app()->environment('production')) {
            return;
        }

        $agent = User::firstOrCreate(
            ['email' => 'agent@moneytransfer.com'],
            [
                'name' => 'Agent Démo',
                'password' => Hash::make($password),
                'phone_number' => '+221700000001',
                'country' => 'Senegal',
                'role' => 'agent',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        if (!$agent->wallet) {
            Wallet::create([
                'uuid' => (string) Str::uuid(),
                'user_id' => $agent->id,
                'type' => Wallet::TYPE_AGENT,
                'currency' => 'XOF',
                'balance' => 0,
            ]);
        }
    }
}
