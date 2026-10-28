<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Paksa HTTPS di production kalau PROFINA_FORCE_HTTPS=true. TIDAK aktif di local (biar tidak mengunci
 * localhost yang belum punya sertifikat HTTPS). Route /up (health check) dikecualikan supaya
 * load balancer/monitoring yang memeriksa lewat HTTP polos tidak ikut kena redirect.
 */
class ForceHttps
{
    public function handle(Request $request, Closure $next): Response
    {
        if (
            config('portal.security.force_https')
            && ! $request->secure()
            && ! app()->environment('local', 'testing')
            && $request->path() !== 'up'
        ) {
            return redirect()->secure($request->getRequestUri());
        }

        return $next($request);
    }
}
