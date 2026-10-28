<?php

use Illuminate\Support\Facades\Schedule;

// Bersihkan sesi SSO lama agar tabel tidak membengkak.
Schedule::call(fn () => \App\Models\SsoSession::where('created_at', '<', now()->subDays(14))->delete())->daily()->name('profina-prune-sso-sessions');
