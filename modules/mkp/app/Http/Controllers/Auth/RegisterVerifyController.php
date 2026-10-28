<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AccountMailer;
use App\Services\ActivityLogger;
use App\Services\OtpMailer;
use App\Services\OtpService;
use App\Support\Mask;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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

    public function store(Request $request, OtpService $otpService, AccountMailer $mailer)
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
        if (Role::where('name', 'admin')->doesntExist()) {
            (new RolePermissionSeeder())->run();
        }

        $user = DB::transaction(function () use ($pending) {
            // Pendaftar PERTAMA otomatis Manager (aktif). Pendaftar berikutnya MENUNGGU persetujuan Manager.
            $isFirst = ! User::query()->exists();

            $user = User::create([
                'name' => $pending['name'],
                'email' => $pending['email'],
                'password' => $pending['password'], // sudah di-hash sejak tahap 1
                'email_verified_at' => now(),
                'status' => $isFirst ? 'active' : 'pending',
                'role_label' => $isFirst ? 'admin' : 'pending',
            ]);

            if ($isFirst) {
                $user->assignRole('admin');
            }

            return $user;
        });

        $request->session()->forget('register.pending');

        if (! $user->isActive()) {
            ActivityLogger::log('register.pending', "Pendaftar baru menunggu persetujuan: {$user->name} ({$user->email})", null, $user->id);
            $mailer->notifyAdminsOfRegistration($user);

            return redirect()->route('login')->with('status', 'Email kamu sudah terverifikasi. Pendaftaran menunggu persetujuan Manager — kamu akan menerima email begitu disetujui.');
        }

        Auth::login($user);
        $request->session()->regenerate();

        ActivityLogger::log('register.success', "Akun Manager pertama terdaftar: {$user->name} ({$user->email})", null, $user->id);

        return redirect()->route('dashboard');
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
            'Verifikasi Email Pendaftaran - MKP',
            'Gunakan kode berikut untuk mengaktifkan akunmu:',
            $code
        );

        if (! $sent) {
            return back()->withErrors(['code' => "Gagal mengirim kode. {$error}"]);
        }

        return back()->with('status', $note ?: 'Kode baru sudah dikirim ke email kamu.');
    }
}
