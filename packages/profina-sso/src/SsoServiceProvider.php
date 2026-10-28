<?php

namespace Profina\Sso;

use Illuminate\Support\ServiceProvider;

class SsoServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/profina.php', 'profina');
    }

    public function boot(): void
    {
        $this->publishes([__DIR__.'/../config/profina.php' => config_path('profina.php')], 'profina-config');

        // Hanya aplikasi MODUL (PROFINA_MODULE terisi) yang punya pintu masuk SSO. Portal (Core) tidak.
        if (config('profina.module')) {
            $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        }
    }
}
