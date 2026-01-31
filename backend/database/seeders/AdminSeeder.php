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
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Admin user
        $admin = User::firstOrCreate(
            [   'email'           => 'admin@moneytransfer.com'],
            [
                'name'            => 'Super Admin',
                'password'        => Hash::make('password123'),
                'phone_number'    => '+1234567890',
                'country'         => 'USA',
                'role'            => 'admin',
                'email_verified_at' => now(),
            ]
        );

        // Create wallet for admin if not exists
        if (!$admin->wallet) {
            Wallet::create([
                'uuid'     => Str::uuid(),
                'user_id'  => $admin->id,
                'currency' => 'XOF',
                'balance'  => 1000000, // Initial balance for admin
            ]);
        }

        // Agent user
        $agent = User::firstOrCreate(
            [   'email'           => 'agent@moneytransfer.com'],
            [
                'name'            => 'Agent One',
                'password'        => Hash::make('password123'),
                'phone_number'    => '+0987654321',
                'country'         => 'USA',
                'role' => 'agent',
                'email_verified_at' => now(),
            ]
        );

         // Create wallet for agent if not exists
         if (!$agent->wallet) {
            Wallet::create([
                'uuid'     => Str::uuid(),
                'user_id'  => $agent->id,
                'currency' => 'XOF',
                'balance'  => 0,
            ]);
        }
    }
}
