<?php

use Illuminate\Support\Facades\Schedule;

// Aktifkan kontrak yang sudah disetujui & jatuh tempo efektif; tandai kontrak berakhir.
Schedule::command('pay:activate-contracts')->dailyAt('00:10');
// Tarik karyawan dari SDM (cadangan bila webhook terlewat).
Schedule::command('pay:sync')->hourly()->withoutOverlapping();
// Kirim ulang webhook yang gagal (backoff eksponensial).
Schedule::command('pay:webhooks-retry')->everyFiveMinutes()->withoutOverlapping();
// Verifikasi rantai audit tiap malam; kirim "jangkar" hash ke luar sistem.
Schedule::command('pay:audit-verify')->dailyAt('01:00');
Schedule::command('pay:audit-anchor')->dailyAt('01:30');
// Pengingat jatuh tempo setoran PPh 21 / BPJS.
Schedule::command('pay:remittance-reminders')->dailyAt('07:30');
// Backup database PostgreSQL.
Schedule::command('pay:backup-database')->dailyAt('02:00');
