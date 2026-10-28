<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Cek dokumen yang akan kedaluwarsa (kontrak, sertifikat) setiap hari jam 07:00 WIB,
// kirim email pengingat ke pemilik dokumen & Admin HR.
Schedule::command('sdm:check-expiring-documents')->dailyAt('07:00');

// Backup database PostgreSQL setiap hari jam 02:00 WIB (lihat app/Console/Commands/BackupDatabase.php).
Schedule::command('sdm:backup-database')->dailyAt('02:00');

// Kirim ulang webhook ke PAYBRIDGE yang tertunda.
Schedule::command('sdm:integration-retry')->everyFiveMinutes()->withoutOverlapping();
