<?php

namespace App\Services;

use App\Models\ModuleAssignment;
use App\Models\SsoSession;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Profina\Sso\Token;

class SsoIssuer
{
    /**
     * Buat sesi SSO + token sekali pakai untuk masuk ke modul, lalu kembalikan URL tujuan.
     * Penugasan DIPERIKSA di sini: tanpa penugasan, token tidak pernah dibuat.
     */
    public function urlFor(User $user, string $module, Request $request): string
    {
        $catalog = ModuleCatalog::find($module);
        abort_unless($catalog, 404, 'Modul tidak dikenal.');
        abort_unless((string) config('portal.master_key') !== '', 500, 'PROFINA_MASTER_KEY belum diisi. Jalankan: php artisan profina:provision');

        /** @var ModuleAssignment|null $assignment */
        $assignment = ModuleAssignment::where('user_id', $user->id)->where('module', $module)->first();
        abort_unless($assignment, 403, 'Kamu tidak memiliki penugasan ke modul ini.');

        $session = SsoSession::create([
            'user_id' => $user->id,
            'module' => $module,
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 250, ''),
            'last_seen_at' => now(),
        ]);

        $now = time();
        $claims = [
            'iss' => 'profina-core',
            'aud' => $module,
            'sub' => $user->email,
            'name' => $user->name,
            'nip' => $user->registration_nip,
            'role' => $assignment->role,
            'sid' => $session->id,
            'jti' => (string) Str::uuid(),
            'iat' => $now,
            'exp' => $now + (int) config('portal.token_ttl', 60),
        ];
        if (($perms = ModuleCatalog::permissionsClaim($module, $assignment->permissions)) !== null) {
            $claims['permissions'] = $perms;
        }

        $token = Token::sign($claims, ModuleCatalog::secretFor($module));

        ActivityLogger::log('sso.issue', "Masuk ke modul {$catalog['name']} sebagai {$assignment->role}", null, $user->id);

        return $catalog['url'].'/sso/consume?token='.$token;
    }

    public static function revokeUser(User $user, string $reason, ?string $module = null): int
    {
        return SsoSession::where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->when($module, fn ($q) => $q->where('module', $module))
            ->update(['revoked_at' => now(), 'revoked_reason' => $reason]);
    }
}
