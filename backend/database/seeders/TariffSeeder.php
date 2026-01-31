<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Tariff;

class TariffSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Example: 0-5000 -> 500 FCFA
        // 5001-10000 -> 1000 FCFA
        // 10001-50000 -> 1500 FCFA

        $tariffs = [
            ['min_amount' => 0, 'max_amount' => 5000, 'fee_amount' => 500, 'type' => 'send'],
            ['min_amount' => 5001, 'max_amount' => 10000, 'fee_amount' => 1000, 'type' => 'send'],
            ['min_amount' => 10001, 'max_amount' => 50000, 'fee_amount' => 2500, 'type' => 'send'],
            ['min_amount' => 50001, 'max_amount' => 100000, 'fee_amount' => 4500, 'type' => 'send'],
            
            // Withdrawals could be different, using same structure for now
            ['min_amount' => 0, 'max_amount' => 1000000, 'fee_amount' => 0, 'type' => 'withdraw'], // Free withdrawal? or different fee
        ];

        foreach ($tariffs as $tariff) {
            Tariff::create($tariff);
        }
    }
}
