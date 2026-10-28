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
                    'job_title' => $user->job_title,
                    'avatar_url' => $user->avatar_url,
                    'role_label' => $user->roleLabel(),
                    'position' => $user->positionLabel(),
                    'roles' => $user->getRoleNames()->values(),
                    'permissions' => $user->getAllPermissions()->pluck('name')->values(),
                ] : null,
            ],
            // Jumlah pendaftar yang menunggu persetujuan (lencana di menu Kelola Pengguna; hanya untuk Manager)
            'pending_users' => fn () => $user && $user->hasRole('admin') ? User::where('status', 'pending')->count() : 0,
            // Lencana menu Dana Pengadaan: pengajuan menunggu keputusan + laporan belum dibaca.
            'procurement_badge' => function () use ($user) {
                if (! $user) {
                    return 0;
                }
                try {
                    return \App\Models\ProcurementFunding::where('status', 'submitted')->count() + \App\Models\ProcurementReport::unread();
                } catch (\Throwable $e) {
                    return 0;
                }
            },
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
}
