<?php

return [
    'home' => '/dashboard',

    // Login password langsung ke API mobile (/api/v1/login). Default MATI: satu pintu masuk = Portal.
    'allow_api_login' => (bool) env('PROFINA_ALLOW_API_LOGIN', false),
    'metrics' => \App\Support\ProfinaMetrics::class,
    'after_login' => \App\Support\ProfinaAfterLogin::class,
];
