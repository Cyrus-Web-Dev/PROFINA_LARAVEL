<?php

namespace App\Console\Commands;

use App\Services\ActivityLogger;
use App\Services\DatabaseBackupService;
use Illuminate\Console\Command;
use Throwable;

class BackupDatabase extends Command
{
    protected $signature = 'pay:backup-database';

    protected $description = 'Buat backup database ke storage/app/backups (bisa dijadwalkan lewat cron/Task Scheduler)';

    public function handle(DatabaseBackupService $backup): int
    {
        try {
            $result = $backup->create();
        } catch (Throwable $e) {
            $this->error('Backup gagal: '.$e->getMessage());

            return self::FAILURE;
        }

        if ($result['note']) {
            $this->warn($result['note']);
        }
        $this->info("Backup dibuat: storage/app/backups/{$result['file']}");
        ActivityLogger::log('backup.created', "Backup terjadwal/CLI: {$result['file']}", null, null);

        return self::SUCCESS;
    }
}
