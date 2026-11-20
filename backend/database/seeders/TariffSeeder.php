<?php

namespace Database\Seeders;

use App\Models\Tariff;
use Illuminate\Database\Seeder;

class TariffSeeder extends Seeder
{
    /**
     * Grille par défaut (XOF). À adapter dans le back-office "Tarifs".
     * N'écrase pas une grille existante.
     */
    public function run()
    {
        if (Tariff::where('type', 'send')->exists()) {
            return;
        }

        $tariffs = [
            ['min_amount' => 1, 'max_amount' => 5000, 'fee_amount' => 500],
            ['min_amount' => 5001, 'max_amount' => 10000, 'fee_amount' => 1000],
            ['min_amount' => 10001, 'max_amount' => 50000, 'fee_amount' => 2500],
            ['min_amount' => 50001, 'max_amount' => 100000, 'fee_amount' => 4500],
            ['min_amount' => 100001, 'max_amount' => 500000, 'fee_amount' => 9000],
            ['min_amount' => 500001, 'max_amount' => 2000000, 'fee_amount' => 15000],
        ];

        foreach ($tariffs as $tariff) {
            Tariff::create($tariff + ['type' => 'send']);
        }
    }
}
