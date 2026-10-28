<?php

namespace Profina\Sso\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Semua halaman login/daftar/lupa-password lama di modul diarahkan ke Portal.
 * Invokable (bukan closure) supaya `php artisan route:cache` tetap bisa dipakai di production.
 */
class RedirectToCoreController extends Controller
{
    public function __invoke(Request $request)
    {
        $url = config('profina.core_url').'/login';

        // XHR Inertia (sesi habis saat halaman terbuka) tidak boleh di-redirect lintas-origin.
        if ($request->header('X-Inertia')) {
            return response('', 409, ['X-Inertia-Location' => $url]);
        }

        return redirect()->away($url);
    }
}
