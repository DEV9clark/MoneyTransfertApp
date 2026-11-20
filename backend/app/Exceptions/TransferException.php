<?php

namespace App\Exceptions;

use Exception;

/**
 * Erreur métier affichable à l'utilisateur (solde insuffisant, code invalide, plafond...).
 * Les autres exceptions sont journalisées et masquées derrière un message générique.
 */
class TransferException extends Exception
{
    protected int $status;

    public function __construct(string $message, int $status = 422)
    {
        parent::__construct($message);
        $this->status = $status;
    }

    public function getStatus(): int
    {
        return $this->status;
    }
}
