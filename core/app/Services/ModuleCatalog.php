<?php

namespace App\Services;

use App\Models\User;
use Profina\Sso\Token;

class ModuleCatalog
{
    public static function all(): array
    {
        return config('portal.modules', []);
    }

    public static function find(string $code): ?array
    {
        return self::all()[$code] ?? null;
    }

    /**
     * Hak langsung (per orang) yang dikirim ke modul. Hanya modul yang punya katalog "permissions" (MKP)
     * memakainya; untuk modul itu SELALU dikirim (kosong = cabut semua hak langsung), supaya sisa hak lama
     * tidak menempel saat role berganti (mis. staff -> viewer). Modul lain: null = jangan disentuh.
     */
    public static function permissionsClaim(string $module, ?array $assigned): ?array
    {
        return isset(self::find($module)['permissions']) ? array_values($assigned ?? []) : null;
    }

    public static function secretFor(string $code): string
    {
        return Token::deriveSecret((string) config('portal.master_key'), $code);
    }

    /** Katalog siap kirim ke Vue (tanpa rahasia). */
    public static function forUi(): array
    {
        $out = [];
        foreach (self::all() as $code => $m) {
            $out[] = [
                'code' => $code,
                'name' => $m['name'],
                'description' => $m['description'],
                'color' => $m['color'] ?? '#334155',
                'url' => $m['url'],
                'roles' => collect($m['roles'])->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values(),
                'permissions' => collect($m['permissions'] ?? [])->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values(),
            ];
        }

        return $out;
    }

    /** Pintu yang boleh dimasuki user: penugasan modul + "core" bila punya peran Portal. */
    public static function doorsFor(User $user): array
    {
        $doors = [];
        if ($user->hasCoreAccess()) {
            $doors[] = ['code' => 'core', 'name' => 'Inti — Pengelola & Pemantau', 'description' => 'Kelola pengguna, penugasan modul, pantau kesehatan semua modul, dan audit.', 'color' => '#0f172a', 'role' => $user->getRoleNames()->first()];
        }
        $user->loadMissing('assignments');
        foreach ($user->assignments as $a) {
            if ($m = self::find($a->module)) {
                $doors[] = ['code' => $a->module, 'name' => $m['name'], 'description' => $m['description'], 'color' => $m['color'] ?? '#334155', 'role' => $m['roles'][$a->role] ?? $a->role];
            }
        }

        return $doors;
    }
}
