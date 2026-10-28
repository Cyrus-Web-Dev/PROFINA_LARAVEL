<?php

namespace App\Http\Controllers\Auth;

use App\Exceptions\GmailSendException;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class PasswordResetLinkController extends Controller
{
    public function create()
    {
        return Inertia::render('Auth/ForgotPassword', [
            'status' => session('status'),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        // Email dikirim lewat notifikasi bawaan Laravel -> mailer "gmail_api" (lihat config/mail.php).
        try {
            $status = Password::sendResetLink($request->only('email'));
        } catch (GmailSendException $e) {
            throw ValidationException::withMessages(['email' => "Link reset gagal dikirim. {$e->userMessage()}"]);
        } catch (\Throwable $e) {
            Log::error('[ResetPassword] '.$e->getMessage());
            throw ValidationException::withMessages(['email' => 'Link reset gagal dikirim. Cek storage/logs/laravel.log untuk detailnya.']);
        }

        if ($status === Password::RESET_THROTTLED) {
            throw ValidationException::withMessages(['email' => __($status)]);
        }

        // Jawaban SAMA untuk email terdaftar maupun tidak (mencegah orang menebak email yang terdaftar).
        return back()->with('status', 'Jika email tersebut terdaftar, link reset password sudah dikirim. Cek inbox atau folder spam.');
    }
}
