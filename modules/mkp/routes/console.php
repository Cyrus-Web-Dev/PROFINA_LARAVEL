<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Pelengkap webhook PROCURA: tarik pengajuan dana tiap 10 menit (butuh `php artisan schedule:work` atau cron `schedule:run`).
\Illuminate\Support\Facades\Schedule::command('procura:sync')->everyTenMinutes()->withoutOverlapping();

// Sinkron cadangan pengajuan dana gaji & setoran dari PAYBRIDGE bila webhook terlewat.
\Illuminate\Support\Facades\Schedule::command('payroll:sync')->everyTenMinutes()->withoutOverlapping();
