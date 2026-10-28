<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\OtpMailer;
use App\Services\OtpService;
use App\Support\Registration;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class AuthenticatedSessionController extends Controller
{
    public function create()
    {
        return Inertia::render('Auth/Login', [
            'status' => session('status'),
            'canRegister' => Registration::allowed(),
        ]);
    }

    /**
     * Tahap 1: cek email+password. Kalau benar, JANGAN langsung login -
     * generate & kirim kode OTP dulu, simpan user_id di session sementara,
     * lalu arahkan ke halaman verifikasi 2FA (lihat TwoFactorChallengeController).
     */
    public function store(Request $request, OtpService $otpService, OtpMailer $otpMailer)
    {
        $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        $email = Str::lower($request->input('email'));
        $throttleKey = Str::transliterate($email.'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            event(new Lockout($request));
            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'email' => "Terlalu banyak percobaan login. Coba lagi dalam {$seconds} detik.",
            ]);
        }

        // Cek password TANPA membuat sesi login (login baru terjadi setelah OTP benar).
        $user = User::where('email', $email)->first();
        $passwordOk = Hash::check($request->input('password'), $user?->password ?? $this->dummyHash());

        if (! $user || ! $passwordOk) {
            RateLimiter::hit($throttleKey, 900); // kunci 15 menit setelah 5x gagal
            ActivityLogger::log('login.failed', "Percobaan login gagal untuk email: {$email}");
            throw ValidationException::withMessages(['email' => 'Email atau password salah.']);
        }

        // Migrasi bcrypt -> Argon2id: hash tidak bisa dikonversi tanpa password asli, jadi diganti
        // sekarang, saat password asli tersedia dan sudah terbukti benar. Juga dipakai bila
        // parameter Argon2id di config/hashing.php dinaikkan. Lewat query builder supaya
        // updated_at (pemicu cache foto profil) tidak ikut berubah.
        if (Hash::needsRehash($user->password)) {
            User::whereKey($user->id)->update(['password' => Hash::make($request->input('password'))]);
        }

        // Password benar, tetapi akun belum boleh masuk: jelaskan alasannya (dan JANGAN kirim OTP).
        if (! $user->isActive()) {
            RateLimiter::clear($throttleKey);

            throw ValidationException::withMessages(['email' => match ($user->status) {
                'rejected' => 'Pendaftaran kamu ditolak'.($user->status_note ? ": {$user->status_note}" : '.').' Hubungi Manager bila ini keliru.',
                'inactive' => 'Akun kamu dinonaktifkan. Hubungi Manager untuk mengaktifkannya kembali.',
                default => 'Akun kamu masih menunggu persetujuan Manager. Kamu akan menerima email begitu disetujui.',
            }]);
        }

        RateLimiter::clear($throttleKey);

        $otpKey = "login:{$user->id}";
        $code = $otpService->generate($otpKey);
        $otpService->markResent($otpKey);

        [$sent, $error, $note] = $otpMailer->send(
            $user->email,
            $user->name,
            'Kode Verifikasi Login - MKP',
            'Gunakan kode berikut untuk menyelesaikan proses login ke aplikasi MKP:',
            $code
        );

        if (! $sent) {
            $otpService->clear($otpKey);
            throw ValidationException::withMessages(['email' => "Password benar, tetapi kode verifikasi gagal dikirim. {$error}"]);
        }

        $request->session()->put('2fa.user_id', $user->id);
        $request->session()->put('2fa.remember', $request->boolean('remember'));

        return redirect()->route('two-factor.challenge')->with('status', $note);
    }

    /**
     * Hash tiruan untuk email yang tidak terdaftar, supaya Hash::check() tetap memakan waktu
     * yang sama dengan email terdaftar (mencegah tebakan email lewat selisih waktu respons).
     * Di-cache karena Argon2id memakai memori besar; kuncinya ikut berubah bila parameter hash berubah.
     */
    private function dummyHash(): string
    {
        $key = 'auth.dummy_hash.'.md5(json_encode([config('hashing.driver'), config('hashing.argon')]));

        return Cache::rememberForever($key, fn () => Hash::make(Str::random(40)));
    }

    public function destroy(Request $request)
    {
        ActivityLogger::log('logout', "Logout: {$request->user()?->name}", null, $request->user()?->id);
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
