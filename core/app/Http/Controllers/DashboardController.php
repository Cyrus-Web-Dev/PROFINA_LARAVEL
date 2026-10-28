<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\ModuleAssignment;
use App\Models\SsoSession;
use App\Models\User;
use App\Services\ModuleHealth;
use Illuminate\Http\Request;
use Inertia\Inertia;

/** Modul Inti: pantau semua modul. */
class DashboardController extends Controller
{
    public function index(Request $request, ModuleHealth $health)
    {
        $user = $request->user();
        abort_unless($user->hasCoreAccess(), 403, 'Kamu tidak memiliki akses ke modul Inti.');
        $canMonitor = $user->can('monitor.view');

        $modules = $canMonitor ? array_values($health->all($request->boolean('fresh'))) : [];

        $activeSessions = SsoSession::query()->whereNull('revoked_at')->where('last_seen_at', '>=', now()->subMinutes(10))
            ->with('user:id,name,email')->latest('last_seen_at')->limit(30)->get()
            ->map(fn ($s) => ['id' => $s->id, 'user' => $s->user?->name, 'email' => $s->user?->email, 'module' => $s->module, 'ip' => $s->ip_address, 'last_seen' => $s->last_seen_at?->diffForHumans()]);

        return Inertia::render('Dashboard/Index', [
            'modules' => $modules,
            'canMonitor' => $canMonitor,
            'stats' => [
                'users_active' => User::where('status', 'active')->count(),
                'users_pending' => User::where('status', 'pending')->count(),
                'assignments' => ModuleAssignment::count(),
                'sessions_active' => $activeSessions->count(),
            ],
            'perModule' => ModuleAssignment::query()->selectRaw('module, count(*) as total')->groupBy('module')->pluck('total', 'module'),
            'sessions' => $canMonitor ? $activeSessions : [],
            'recent' => $user->can('audit.view')
                ? ActivityLog::with('user:id,name')->latest('created_at')->limit(8)->get()->map(fn ($l) => ['at' => $l->created_at?->format('d/m H:i'), 'user' => $l->user?->name, 'action' => $l->action, 'description' => $l->description])
                : [],
        ]);
    }

    public function revokeSession(Request $request, SsoSession $session)
    {
        $session->update(['revoked_at' => now(), 'revoked_reason' => 'dicabut admin']);
        \App\Services\ActivityLogger::log('sso.revoke', "Mencabut sesi {$session->module} milik user #{$session->user_id}");

        return back()->with('success', 'Sesi dicabut. Modul akan menendangnya dalam hitungan detik.');
    }
}
