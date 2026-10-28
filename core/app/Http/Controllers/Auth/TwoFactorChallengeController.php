<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\OtpMailer;
use App\Services\OtpService;
use App\Support\Mask;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class TwoFactorChallengeController extends Controller
{
    public function create(Request $request, OtpService $otpService)
    {
        $user = ($id = $request->session()->get('2fa.user_id')) ? User::find($id) : null;
        if (! $user) {
            return redirect()->route('login')->with('status', 'Sesi verifikasi sudah berakhir. Silakan login ulang.');
        }

        return Inertia::render('Auth/TwoFactorChallenge', [
            'maskedEmail' => Mask::email($user->email),
            'status' => session('status'),
            'resendIn' => $otpService->secondsUntilResendAllowed("login:{$user->id}"),
            'codeLength' => (int) config('gmail.otp_length', 6),
        ]);
    }

    public function store(Request $request, OtpService $otpService)
    {
        $request->validate(['code' => 'required|string']);

        $userId = $request->session()->get('2fa.user_id');
        if (! $userId) {
            return redirect()->route('login')->with('status', 'Sesi verifikasi sudah berakhir. Silakan login ulang.');
        }

        [$valid, $message] = $otpService->verify("login:{$userId}", $request->input('code'));
        if (! $valid) {
            throw ValidationException::withMessages(['code' => $message]);
        }

        $user = User::findOrFail($userId);

        if (! $user->isActive()) {
            $request->session()->forget(['2fa.user_id', '2fa.remember']);

            return redirect()->route('login')->with('status', 'Akun kamu tidak aktif. Hubungi Admin PROFINA.');
        }
        $remember = (bool) $request->session()->get('2fa.remember', false);

        Auth::login($user, $remember);
        $request->session()->regenerate(); // cegah session fixation
        $request->session()->forget(['2fa.user_id', '2fa.remember']);

        ActivityLogger::log('login.success', "Login berhasil: {$user->name}", null, $user->id);

        return redirect()->intended(route('portal'));
    }

    public function resend(Request $request, OtpService $otpService, OtpMailer $otpMailer)
    {
        $user = ($id = $request->session()->get('2fa.user_id')) ? User::find($id) : null;
        if (! $user) {
            return redirect()->route('login')->with('status', 'Sesi verifikasi sudah berakhir. Silakan login ulang.');
        }

        $key = "login:{$user->id}";
        if (! $otpService->canResend($key)) {
            $wait = $otpService->secondsUntilResendAllowed($key);

            return back()->withErrors(['code' => "Tunggu {$wait} detik lagi sebelum minta kode baru."]);
        }

        $code = $otpService->generate($key);
        $otpService->markResent($key);

        [$sent, $error, $note] = $otpMailer->send(
            $user->email,
            $user->name,
            'Kode Verifikasi Login - PROFINA',
            'Gunakan kode berikut untuk menyelesaikan proses login ke aplikasi PROFINA:',
            $code
        );

        if (! $sent) {
            return back()->withErrors(['code' => "Gagal mengirim kode. {$error}"]);
        }

        return back()->with('status', $note ?: 'Kode baru sudah dikirim ke email kamu.');
    }
}
