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
        // Tabel approvals menyimpan nama kelas pendek (class_basename); petakan agar relasi morphMany() menemukannya.
        \Illuminate\Database\Eloquent\Relations\Relation::morphMap([
            'CompensationContract' => \App\Models\CompensationContract::class,
            'PayrollRun' => \App\Models\PayrollRun::class,
            'BudgetPlan' => \App\Models\BudgetPlan::class,
        ]);

        // Kebijakan password minimum di SELURUH aplikasi (registrasi, reset, ganti password):
        // minimal 8 karakter, campuran huruf besar/kecil, ada angka, dan dicek terhadap daftar
        // password yang pernah bocor (Have I Been Pwned, lewat k-anonymity - tidak mengirim
        // password asli ke layanan luar).
        Password::defaults(function () {
            return Password::min(8)->mixedCase()->numbers()->uncompromised();
        });

        // Daftarkan driver "gmail_api" (dipakai MAIL_MAILER=gmail_api).
        Mail::extend('gmail_api', fn () => new GmailApiTransport($this->app->make(GmailApiService::class)));

        if (! config('mail.mailers.gmail_api')) {
            config(['mail.mailers.gmail_api' => ['transport' => 'gmail_api']]);
        }

        RateLimiter::for('pay-api', fn (Request $r) => Limit::perMinute((int) config('pay.integrations.api.rate_limit', 120))->by(($r->bearerToken() ? substr(hash('sha256', $r->bearerToken()), 0, 16) : $r->ip())));

        // Batas laju default untuk seluruh routes/api.php (60 req/menit per user login, atau per IP bila belum login).
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });
    }
}
