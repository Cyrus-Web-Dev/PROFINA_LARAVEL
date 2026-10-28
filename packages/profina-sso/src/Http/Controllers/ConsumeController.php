<?php

namespace Profina\Sso\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Profina\Sso\Token;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Menerima token dari Portal, memastikan token sah, lalu membuat sesi login lokal.
 * Tidak ada cara lain untuk login ke modul ini.
 */
class ConsumeController extends Controller
{
    public function __invoke(Request $request)
    {
        $module = (string) config('profina.module');
        $secret = (string) config('profina.secret');
        abort_if($module === '' || $secret === '', 500, 'PROFINA_MODULE / PROFINA_SSO_SECRET belum diisi di .env modul ini (jalankan `php artisan profina:provision` di Core).');

        $claims = Token::verify((string) $request->query('token'), $secret);
        if (! $claims) {
            return $this->deny('Token masuk tidak valid. Silakan masuk lewat Portal PROFINA.');
        }

        $now = time();
        if (($claims['aud'] ?? null) !== $module) {
            return $this->deny('Token ini bukan untuk modul ini.');
        }
        if (($claims['iat'] ?? 0) > $now + 30 || ($claims['exp'] ?? 0) < $now) {
            return $this->deny('Token masuk sudah kedaluwarsa. Silakan buka modul dari Portal lagi.');
        }
        if (empty($claims['jti']) || empty($claims['sid']) || empty($claims['sub']) || empty($claims['role'])) {
            return $this->deny('Token masuk tidak lengkap.');
        }
        // Sekali pakai: token yang sama tidak bisa diputar ulang.
        if (! Cache::add('profina:jti:'.$claims['jti'], 1, 300)) {
            return $this->deny('Token masuk sudah pernah dipakai.');
        }

        $guard = (string) config('profina.guard', 'web');
        $role = (string) $claims['role'];
        if (! Role::query()->where('name', $role)->where('guard_name', $guard)->exists()) {
            return $this->deny("Role \"{$role}\" belum ada di modul ini. Jalankan: php artisan db:seed --class=RolePermissionSeeder");
        }

        $user = $this->resolveUser($claims);
        $user->syncRoles([$role]);

        if (array_key_exists('permissions', $claims) && is_array($claims['permissions'])) {
            $valid = Permission::query()->where('guard_name', $guard)->whereIn('name', $claims['permissions'])->pluck('name')->all();
            $user->syncPermissions($valid);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Auth::guard($guard)->login($user);
        $request->session()->regenerate();
        $request->session()->put([
            'profina.sid' => $claims['sid'],
            'profina.checked_at' => $now,
            'profina.ok_at' => $now,
        ]);

        if (($hook = config('profina.after_login')) && class_exists($hook)) {
            (new $hook)($user, $claims);
        }

        return redirect()->to(config('profina.home', '/dashboard'));
    }

    private function resolveUser(array $claims)
    {
        $model = config('profina.user_model') ?: config('auth.providers.users.model');
        $email = Str::lower((string) $claims['sub']);

        $user = $model::query()->whereRaw('lower(email) = ?', [$email])->first() ?? new $model;
        $table = $user->getTable();
        $has = fn (string $column) => Schema::hasColumn($table, $column);

        if (! $user->exists) {
            $user->email = $email;
            $user->password = Str::random(64); // tidak dipakai: login hanya lewat Portal
        }
        $user->name = (string) ($claims['name'] ?? $email);
        if ($has('status')) {
            $user->status = 'active';
        }
        if ($has('must_change_password')) {
            $user->must_change_password = false;
        }
        if ($has('email_verified_at') && ! $user->email_verified_at) {
            $user->email_verified_at = now();
        }
        if ($has('registration_nip') && ! empty($claims['nip']) && ! $user->registration_nip) {
            $user->registration_nip = $claims['nip'];
        }
        if ($has('last_login_at')) {
            $user->last_login_at = now();
        }
        if ($has('last_login_ip')) {
            $user->last_login_ip = request()->ip();
        }
        $user->save();

        return $user;
    }

    private function deny(string $message)
    {
        return redirect()->away(config('profina.core_url').'/?sso_error='.urlencode($message));
    }
}
