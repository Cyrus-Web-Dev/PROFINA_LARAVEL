<?php

namespace App\Http\Middleware;

use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Data yang dikirim ke SETIAP halaman Vue ($page.props.*).
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'avatar_url' => $user->avatar_url,
                    'roles' => $user->getRoleNames()->values(),
                    'permissions' => $user->getAllPermissions()->pluck('name')->values(),
                    'employee' => $user->employee ? [
                        'id' => $user->employee->id,
                        'nip' => $user->employee->nip,
                        'full_name' => $user->employee->full_name,
                        'position_title' => $user->employee->position_title,
                        'department_name' => $user->employee->department?->name,
                    ] : null,
                ] : null,
            ],
            // Jumlah user menunggu persetujuan (lencana menu "Kelola Pengguna", hanya utk yg punya izin users.manage)
            'pending_users' => fn () => $user && $user->can('users.manage') ? User::where('status', 'pending')->count() : 0,
            // Kunci publik Turnstile (aman dikirim ke browser; secret key TIDAK pernah dikirim).
            'turnstile_site_key' => config('pay.security.turnstile.site_key'),
            'registration_open' => fn () => \App\Support\Registration::allowed(),
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
}
