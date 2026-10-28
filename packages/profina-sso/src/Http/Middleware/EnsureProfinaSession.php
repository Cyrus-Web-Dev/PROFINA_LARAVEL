<?php

namespace Profina\Sso\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Profina\Sso\Token;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Menjaga sesi modul tetap terikat ke Portal:
 *  - sesi tanpa jejak SSO (mis. sisa login lama) ditolak;
 *  - berkala menanyakan ke Portal apakah akses user masih berlaku (dicabut/dinonaktifkan/logout di Portal);
 *  - role di modul disinkronkan bila Admin mengubah penugasan.
 */
class EnsureProfinaSession
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('profina.module') || $request->is('sso/*', 'profina/*', 'up')) {
            return $next($request);
        }

        $guard = (string) config('profina.guard', 'web');
        $user = Auth::guard($guard)->user();
        if (! $user) {
            return $next($request);
        }

        $session = $request->session();
        $sid = $session->get('profina.sid');
        if (! $sid) {
            return $this->kick($request, 'Masuk ke modul hanya lewat Portal PROFINA.');
        }

        $now = time();
        if ($now - (int) $session->get('profina.checked_at', 0) >= (int) config('profina.revalidate_seconds', 60)) {
            $result = $this->introspect((string) $sid, (string) $user->email);

            if ($result === null) {
                // Portal tidak terjangkau: beri kelonggaran singkat, lalu putus.
                if ($now - (int) $session->get('profina.ok_at', 0) > (int) config('profina.grace_seconds', 300)) {
                    return $this->kick($request, 'Portal PROFINA tidak dapat dihubungi. Silakan masuk ulang.');
                }
            } elseif (! ($result['active'] ?? false)) {
                return $this->kick($request, (string) ($result['reason'] ?? 'Akses kamu ke modul ini sudah dicabut.'));
            } else {
                $this->syncAccess($user, $result);
                $session->put(['profina.checked_at' => $now, 'profina.ok_at' => $now]);
            }
        }

        return $next($request);
    }

    /** @return array<string,mixed>|null */
    private function introspect(string $sid, string $email): ?array
    {
        $module = (string) config('profina.module');
        $secret = (string) config('profina.secret');
        $ts = (string) time();

        try {
            $response = Http::timeout(4)->acceptJson()->withHeaders([
                'X-Profina-Module' => $module,
                'X-Profina-Timestamp' => $ts,
                'X-Profina-Signature' => Token::requestSignature($secret, $module, $ts, $sid.'|'.$email),
            ])->post(config('profina.core_internal_url').'/api/sso/introspect', ['sid' => $sid, 'email' => $email]);
        } catch (Throwable) {
            return null;
        }

        return $response->successful() && is_array($response->json()) ? $response->json() : null;
    }

    private function syncAccess($user, array $result): void
    {
        $changed = false;

        if (! empty($result['role']) && (! $user->hasRole($result['role']) || $user->roles->count() !== 1)) {
            $user->syncRoles([$result['role']]);
            $changed = true;
        }

        if (array_key_exists('permissions', $result) && is_array($result['permissions'])) {
            $guard = (string) config('profina.guard', 'web');
            $wanted = \Spatie\Permission\Models\Permission::query()->where('guard_name', $guard)
                ->whereIn('name', $result['permissions'])->pluck('name')->sort()->values()->all();
            $current = $user->getDirectPermissions()->pluck('name')->sort()->values()->all();

            if ($wanted !== $current) {
                $user->syncPermissions($wanted);
                $changed = true;
            }
        }

        if ($changed) {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    private function kick(Request $request, string $reason): Response
    {
        Auth::guard(config('profina.guard', 'web'))->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $url = config('profina.core_url').'/login?notice='.urlencode($reason);

        if ($request->header('X-Inertia')) {
            return response('', 409, ['X-Inertia-Location' => $url]);
        }

        return redirect()->away($url);
    }
}
