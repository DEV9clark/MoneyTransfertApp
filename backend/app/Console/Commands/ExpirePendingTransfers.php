<?php

namespace App\Console\Commands;

use App\Services\TransactionService;
use Illuminate\Console\Command;

class ExpirePendingTransfers extends Command
{
    protected $signature = 'transfers:expire';

    protected $description = "Expire les envois non retirés après le délai de validité et rembourse l'agent expéditeur";

    public function handle(TransactionService $service)
    {
        $count = $service->expirePending();
        $this->info("{$count} transfert(s) expiré(s) et remboursé(s).");

        return 0;
    }
}
