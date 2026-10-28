<?php

namespace App\Console\Commands;

use App\Exceptions\PayrollException;
use App\Services\PayrollClient;
use App\Services\PayrollFundingService;
use Illuminate\Console\Command;

class PayrollSync extends Command
{
    protected $signature = 'payroll:sync {--since= : Hanya yang berubah sejak waktu (ISO)} {--budget : Tarik & terapkan anggaran SDM aktif}';

    protected $description = 'Sinkron pengajuan dana gaji & setoran dari PAYBRIDGE (cadangan bila webhook terlewat)';

    public function handle(PayrollClient $client, PayrollFundingService $svc): int
    {
        if (! $client->configured()) {
            $this->warn('PAYROLL_URL/PAYROLL_TOKEN belum diatur; lewati.');

            return self::SUCCESS;
        }
        try {
            $this->info('Pengajuan diperbarui: '.$svc->syncAll($this->option('since') ?: now()->subMinutes((int) config('payroll.sync_stale_minutes'))->toIso8601String()));
            if ($this->option('budget')) {
                $b = $client->activeBudget();
                $this->info('Anggaran diterapkan: '.$svc->applyBudget($b).' baris');
            }
        } catch (PayrollException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
