<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\NotificationMailer;
use App\Services\OtpMailer;
use App\Services\OtpService;
use App\Support\Mask;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;

class RegisterVerifyController extends Controller
{
    public function create(Request $request, OtpService $otpService)
    {
        $pending = $request->session()->get('register.pending');
        if (! $pending) {
            return redirect()->route('register')->with('status', 'Sesi pendaftaran sudah berakhir. Silakan daftar ulang.');
        }

        return Inertia::render('Auth/RegisterVerify', [
            'maskedEmail' => Mask::email($pending['email']),
            'status' => session('status'),
            'resendIn' => $otpService->secondsUntilResendAllowed('register:'.$pending['email']),
            'codeLength' => (int) config('gmail.otp_length', 6),
        ]);
    }

    public function store(Request $request, OtpService $otpService, NotificationMailer $mailer)
    {
        $request->validate(['code' => 'required|string']);

        $pending = $request->session()->get('register.pending');
        if (! $pending) {
            return redirect()->route('register')->with('status', 'Sesi pendaftaran sudah berakhir. Silakan daftar ulang.');
        }

        [$valid, $message] = $otpService->verify('register:'.$pending['email'], $request->input('code'));
        if (! $valid) {
            throw ValidationException::withMessages(['code' => $message]);
        }

        if (User::where('email', $pending['email'])->exists()) {
            $request->session()->forget('register.pending');
            throw ValidationException::withMessages(['code' => 'Email ini sudah terdaftar. Silakan login.']);
        }

        // Pastikan role & permission tersedia (aman dijalankan berulang).
        if (Role::where('name', 'super-admin')->doesntExist()) {
            (new RolePermissionSeeder())->run();
        }

        $user = DB::transaction(function () use ($pending) {
            // Pendaftar PERTAMA otomatis super-admin (aktif). Pendaftar berikutnya MENUNGGU persetujuan Admin PROFINA.
            $isFirst = ! User::query()->lockForUpdate()->exists();

            $user = User::create([
                'name' => $pending['name'],
                'email' => $pending['email'],
                'registration_nip' => $pending['registration_nip'] ?? null,
                'password' => Str::random(32), // placeholder, ditimpa mentah di bawah - lihat komentar
                'email_verified_at' => now(),
                'status' => $isFirst ? 'active' : 'pending',
            ]);

            // KRITIS: password di $pending['password'] SUDAH di-hash sejak tahap 1 (RegisteredUserController).
            // Ditulis LANGSUNG lewat query builder (bukan User::create/update) supaya cast "hashed" di model
            // TIDAK menghash-ulang nilainya - kalau sampai ter-hash dua kali, pendaftar tidak akan pernah
            // bisa login dengan password yang dia buat sendiri.
            DB::table('users')->where('id', $user->id)->update(['password' => $pending['password']]);

            if ($isFirst) {
                $user->assignRole('super-admin');
            }

            return $user;
        });

        $request->session()->forget('register.pending');

        if (! $user->isActive()) {
            ActivityLogger::log('register.pending', "Pendaftar baru menunggu persetujuan: {$user->name} ({$user->email})", null, $user->id);
            $mailer->notifyAdminsOfRegistration($user);

            return redirect()->route('login')->with('status', 'Email kamu sudah terverifikasi. Pendaftaran menunggu persetujuan Admin PROFINA — kamu akan menerima email begitu disetujui.');
        }

        Auth::login($user);
        $request->session()->regenerate();

        ActivityLogger::log('register.success', "Akun super-admin pertama terdaftar: {$user->name} ({$user->email})", null, $user->id);

        return redirect()->route('portal');
    }

    public function resend(Request $request, OtpService $otpService, OtpMailer $otpMailer)
    {
        $pending = $request->session()->get('register.pending');
        if (! $pending) {
            return redirect()->route('register')->with('status', 'Sesi pendaftaran sudah berakhir. Silakan daftar ulang.');
        }

        $key = 'register:'.$pending['email'];
        if (! $otpService->canResend($key)) {
            $wait = $otpService->secondsUntilResendAllowed($key);

            return back()->withErrors(['code' => "Tunggu {$wait} detik lagi sebelum minta kode baru."]);
        }

        $code = $otpService->generate($key);
        $otpService->markResent($key);

        [$sent, $error, $note] = $otpMailer->send(
            $pending['email'],
            $pending['name'],
            'Verifikasi Email Pendaftaran - PROFINA',
            'Gunakan kode berikut untuk memverifikasi emailmu:',
            $code
        );

        if (! $sent) {
            return back()->withErrors(['code' => "Gagal mengirim kode. {$error}"]);
        }

        return back()->with('status', $note ?: 'Kode baru sudah dikirim ke email kamu.');
    }
}
