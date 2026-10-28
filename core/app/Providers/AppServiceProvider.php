<?php

namespace App\Providers;

use App\Mail\Transport\GmailApiTransport;
use App\Services\GmailApiService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(GmailApiService::class);
    }

    public function boot(): void
    {
        Password::defaults(fn () => Password::min(8)->mixedCase()->numbers()->uncompromised());

        Mail::extend('gmail_api', fn () => new GmailApiTransport($this->app->make(GmailApiService::class)));
        if (! config('mail.mailers.gmail_api')) {
            config(['mail.mailers.gmail_api' => ['transport' => 'gmail_api']]);
        }

        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(60)->by($request->user()?->id ?: $request->ip()));
    }
}
