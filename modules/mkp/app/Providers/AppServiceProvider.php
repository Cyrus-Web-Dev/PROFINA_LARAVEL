<?php

namespace App\Providers;

use App\Mail\Transport\GmailApiTransport;
use App\Services\GmailApiService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(GmailApiService::class);
    }

    public function boot(): void
    {
        // Daftarkan driver "gmail_api" (dipakai MAIL_MAILER=gmail_api).
        Mail::extend('gmail_api', fn () => new GmailApiTransport($this->app->make(GmailApiService::class)));

        // Jaga-jaga: kalau config/mail.php tertimpa versi lain yang tidak punya entri ini,
        // daftarkan di sini agar tidak muncul error "Mailer [gmail_api] is not defined".
        if (! config('mail.mailers.gmail_api')) {
            config(['mail.mailers.gmail_api' => ['transport' => 'gmail_api']]);
        }
    }
}
