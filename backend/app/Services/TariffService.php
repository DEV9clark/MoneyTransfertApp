<?php

namespace App\Services;

use App\Models\Tariff;

class TariffService
{
    /**
     * Calculate the fee for a given amount and transaction type.
     *
     * @param float $amount
     * @param string $type
     * @return float
     */
    public function calculateFee(float $amount, string $type = 'send'): float
    {
        $tariff = Tariff::where('type', $type)
            ->where('min_amount', '<=', $amount)
            ->where(function ($query) use ($amount) {
                $query->where('max_amount', '>=', $amount)
                      ->orWhereNull('max_amount');
            })
            ->first();

        // If no tariff found, maybe default to 0
        return $tariff ? $tariff->fee_amount : 0;
    }
}
