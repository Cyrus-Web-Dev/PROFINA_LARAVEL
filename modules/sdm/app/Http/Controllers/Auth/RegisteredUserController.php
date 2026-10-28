<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\OtpMailer;
use App\Services\OtpService;
use App\Services\Turnstile;
use App\Support\Registration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class RegisteredUserController extends Controller
{
    public function create()
    {
        if (! Registration::allowed()) {
            return redirect()->route('login')->with('status', 'Pendaftaran mandiri ditutup. Minta Admin HR membuatkan akun untukmu.');
        }

        return Inertia::render('Auth/Register', [
            'isFirstUser' => ! User::query()->exists(),
        ]);
    }

    /**
     * Tahap 1: validasi data, TAPI JANGAN buat user dulu. Data disimpan sementara di session, kode verifikasi
     * dikirim ke email, lalu diarahkan ke halaman verifikasi. User baru dibuat di RegisterVerifyController
     * setelah kode dikonfirmasi (membuktikan pendaftar benar-benar pemilik email tsb).
     */
    public function store(Request $request, OtpService $otpService, OtpMailer $otpMailer, Turnstile $turnstile)
    {
        abort_unless(Registration::allowed(), 403, 'Pendaftaran mandiri ditutup.');

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:'.User::class,
            'registration_nip' => ['nullable', 'string', 'max:30', 'regex:/^[A-Za-z0-9._\-\/]+$/'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ], ['registration_nip.regex' => 'NIP hanya boleh berisi huruf, angka, titik, garis, dan garis miring.']);

        $turnstile->assertPassed($request, 'email');

        // Hitung percobaan SETELAH validasi lolos, supaya salah ketik form tidak menghabiskan jatah.
        $throttleKey = 'register|'.$request->ip();
        if (RateLimiter::tooManyAttempts($throttleKey, 8)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            throw ValidationException::withMessages(['email' => "Terlalu banyak percobaan pendaftaran. Coba lagi dalam {$seconds} detik."]);
        }
        RateLimiter::hit($throttleKey, 3600);

        $email = $request->input('email');
        $name = $request->input('name');
        $key = 'register:'.$email;
        $code = $otpService->generate($key);
        $otpService->markResent($key);

        [$sent, $error, $note] = $otpMailer->send(
            $email,
            $name,
            'Verifikasi Email Pendaftaran - SDM HRIS',
            'Terima kasih sudah mendaftar di SDM HRIS. Gunakan kode berikut untuk memverifikasi emailmu:',
            $code
        );

        if (! $sent) {
            $otpService->clear($key);
            throw ValidationException::withMessages(['email' => "Gagal mengirim kode verifikasi. {$error}"]);
        }

        $request->session()->put('register.pending', [
            'name' => $name,
            'email' => $email,
            'registration_nip' => $request->input('registration_nip'),
            'password' => Hash::make($request->input('password')),
        ]);

        return redirect()->route('register.verify')->with('status', $note);
    }
}
