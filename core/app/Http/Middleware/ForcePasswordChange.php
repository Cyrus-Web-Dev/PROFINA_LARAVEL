<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Memaksa user dengan password sementara (dibuat oleh Admin PROFINA / hasil impor data lama) untuk
 * mengganti password SEBELUM bisa mengakses halaman lain. Tanpa middleware ini, flag
 * "must_change_password" di database tidak ada gunanya - cuma kolom kosong.
 */
class ForcePasswordChange
{
    /** Route yang tetap boleh diakses walau must_change_password masih true (hindari infinite redirect). */
    private const ALLOWED_ROUTES = ['profile.password', 'logout', 'profile.edit', 'profile.photo', 'profile.photo.upload', 'profile.photo.delete'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->must_change_password && ! in_array($request->route()?->getName(), self::ALLOWED_ROUTES, true)) {
            if ($request->header('X-Inertia')) {
                return Inertia::location(route('profile.edit'));
            }

            return redirect()->route('profile.edit')->with('error', 'Kamu memakai password sementara. Silakan ganti password terlebih dahulu sebelum melanjutkan.');
        }

        return $next($request);
    }
}
