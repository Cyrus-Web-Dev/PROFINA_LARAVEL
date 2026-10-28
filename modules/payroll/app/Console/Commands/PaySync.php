<?php

namespace App\Console\Commands;

use App\Exceptions\IntegrationException;
use App\Services\SdmClient;
use App\Services\SdmSyncService;
use Illuminate\Console\Command;

class PaySync extends Command
{
    protected $signature = 'pay:sync {--attendance= : Tarik juga absensi bulan tertentu (YYYY-MM)} {--since= : Hanya karyawan yang berubah sejak tanggal (ISO)}';

    protected $description = 'Tarik data karyawan (dan opsional absensi) dari SDM ke PAYBRIDGE';

    public function handle(SdmClient $sdm, SdmSyncService $sync): int
    {
        if (! $sdm->configured()) {
            $this->warn('SDM_URL/SDM_TOKEN belum diatur; lewati.');

            return self::SUCCESS;
        }
        try {
            $r = $sync->syncEmployees($this->option('since'));
            $this->info("Karyawan: {$r['created']} baru, {$r['updated']} berubah, {$r['unchanged']} tetap.");
            if ($ym = $this->option('attendance')) {
                [$y, $m] = array_map('intval', explode('-', $ym));
                $this->info('Absensi: '.$sync->syncAttendance($y, $m).' baris.');
            }
        } catch (IntegrationException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
