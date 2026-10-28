<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Akun yang belum diberi role (mis. baru mendaftar) tidak boleh melihat data keuangan apa pun.
 */
class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless(
            $user && $user->roles()->exists(),
            403,
            'Akun kamu belum diberi role. Hubungi Admin HR. (Admin HR: php artisan sdm:user '.($user?->email ?? 'email@kamu').' --role=karyawan)'
        );

        return $next($request);
    }
}
