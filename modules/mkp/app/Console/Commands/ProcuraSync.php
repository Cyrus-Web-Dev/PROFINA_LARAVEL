<?php

namespace App\Console\Commands;

use App\Exceptions\ProcuraException;
use App\Services\ProcuraClient;
use App\Services\ProcurementFundingService;
use Illuminate\Console\Command;

class ProcuraSync extends Command
{
    protected $signature = 'procura:sync';

    protected $description = 'Tarik pengajuan dana & perubahan status terbaru dari PROCURA (pelengkap webhook).';

    public function handle(ProcurementFundingService $svc, ProcuraClient $client): int
    {
        if (! $client->configured()) {
            $this->warn('PROCURA_URL / PROCURA_TOKEN belum diisi. Dilewati.');

            return self::SUCCESS;
        }

        try {
            $this->info('Sinkron selesai: '.$svc->syncAll().' pengajuan diproses.');
        } catch (ProcuraException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
