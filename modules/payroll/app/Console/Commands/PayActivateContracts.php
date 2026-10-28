<?php

namespace App\Console\Commands;

use App\Services\ContractService;
use Illuminate\Console\Command;

class PayActivateContracts extends Command
{
    protected $signature = 'pay:activate-contracts';

    protected $description = 'Aktifkan kontrak yang sudah disetujui dan mulai berlaku hari ini; tandai kontrak berakhir';

    public function handle(ContractService $svc): int
    {
        $r = $svc->sweep();
        $this->info("Diaktifkan: {$r['activated']}, kedaluwarsa: {$r['expired']}.");

        return self::SUCCESS;
    }
}
