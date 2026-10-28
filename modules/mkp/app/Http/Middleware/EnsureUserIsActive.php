<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menendang akun yang statusnya bukan "active" (dinonaktifkan/ditolak/menunggu) pada request BERIKUTNYA,
 * sehingga penonaktifan berlaku segera walau orangnya masih dalam keadaan login.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->isActive()) {
            $message = match ($user->status) {
                'inactive' => 'Akun kamu dinonaktifkan. Hubungi Manager untuk mengaktifkannya kembali.',
                'rejected' => 'Pendaftaran kamu ditolak. Hubungi Manager bila ini keliru.',
                default => 'Akun kamu masih menunggu persetujuan Manager.',
            };

            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('status', $message);
        }

        return $next($request);
    }
}
