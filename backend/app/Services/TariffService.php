<?php

namespace App\Services;

use App\Exceptions\TransferException;
use App\Models\Tariff;

class TariffService
{
    /**
     * Calcule les frais d'un envoi.
     *
     * On retient la tranche dont le minimum est le plus élevé tout en restant <= montant.
     * Un montant qui tombe "entre" deux tranches (ex. 5000,50 entre 0-5000 et 5001-10000)
     * prend donc la tranche inférieure. Au-delà de la dernière tranche plafonnée,
     * l'envoi est refusé au lieu d'être gratuit.
     *
     * @throws TransferException
     */
    public function calculateFee(float $amount, string $type = 'send'): float
    {
        $tariffs = Tariff::where('type', $type);

        if (!(clone $tariffs)->exists()) {
            if ($type === 'send') {
                throw new TransferException("Aucune grille tarifaire n'est configurée pour les envois.");
            }
            return 0.0;
        }

        $tariff = (clone $tariffs)
            ->where('min_amount', '<=', $amount)
            ->orderByDesc('min_amount')
            ->first();

        if (!$tariff) {
            throw new TransferException('Montant inférieur au minimum autorisé.');
        }

        if ($tariff->max_amount !== null && $amount > $tariff->max_amount) {
            $hasHigherTranche = (clone $tariffs)->where('min_amount', '>', $amount)->exists();
            if (!$hasHigherTranche) {
                throw new TransferException(
                    'Montant supérieur au plafond de la grille tarifaire (' . number_format($tariff->max_amount, 0, ',', ' ') . ').'
                );
            }
        }

        return round((float) $tariff->fee_amount, 2);
    }
}
