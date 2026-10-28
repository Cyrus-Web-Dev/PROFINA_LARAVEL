<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ModuleAssignment;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\ModuleCatalog;
use App\Services\NotificationMailer;
use App\Services\SsoIssuer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Spatie\Permission\PermissionRegistrar;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $users = User::query()->with(['assignments', 'roles'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('name', 'ilike', '%'.$request->input('q').'%')->orWhere('email', 'ilike', '%'.$request->input('q').'%')))
            ->orderByRaw("case status when 'pending' then 0 else 1 end")->orderBy('name')
            ->paginate(15)->withQueryString()
            ->through(fn (User $u) => [
                'id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'status' => $u->status,
                'status_note' => $u->status_note, 'nip' => $u->registration_nip,
                'last_login_at' => $u->last_login_at?->format('d/m/Y H:i'),
                'core_role' => $u->roles->first()?->name,
                'assignments' => $u->assignments->map(fn ($a) => ['module' => $a->module, 'role' => $a->role, 'permissions' => $a->permissions])->values(),
            ]);

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'filters' => $request->only('q', 'status'),
            'catalog' => ModuleCatalog::forUi(),
            'coreRoles' => collect(config('portal.core_roles'))->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'registration_nip' => ['nullable', 'string', 'max:30'],
        ]);

        $temp = Str::password(12, symbols: false);
        $user = User::create([
            'name' => $data['name'], 'email' => Str::lower($data['email']), 'registration_nip' => $data['registration_nip'] ?? null,
            'password' => $temp, 'status' => 'active', 'must_change_password' => true, 'email_verified_at' => now(),
            'approved_by' => $request->user()->id, 'approved_at' => now(),
        ]);
        [$sent] = app(NotificationMailer::class)->accountCreated($user->email, $user->name, $temp);
        ActivityLogger::log('user.created', "Membuat akun {$user->email}", $user);

        return back()->with('success', $sent
            ? 'Akun dibuat & password sementara dikirim ke email. Sekarang beri penugasan modul.'
            : "Akun dibuat, tetapi email gagal terkirim. Password sementara: {$temp} (catat sekarang, tidak ditampilkan lagi).");
    }

    public function approve(Request $request, User $user)
    {
        $user->update(['status' => 'active', 'approved_by' => $request->user()->id, 'approved_at' => now(), 'status_note' => null]);
        app(NotificationMailer::class)->accountApproved($user);
        ActivityLogger::log('user.approved', "Menyetujui akun {$user->email}", $user);

        return back()->with('success', 'Akun disetujui. Jangan lupa beri penugasan modul — tanpa itu dia belum bisa masuk ke modul mana pun.');
    }

    public function reject(Request $request, User $user)
    {
        $data = $request->validate(['note' => 'nullable|string|max:500']);
        $user->update(['status' => 'rejected', 'status_note' => $data['note'] ?? null]);
        SsoIssuer::revokeUser($user, 'ditolak');
        app(NotificationMailer::class)->accountRejected($user, $data['note'] ?? null);
        ActivityLogger::log('user.rejected', "Menolak akun {$user->email}", $user);

        return back()->with('success', 'Pendaftaran ditolak.');
    }

    public function toggleActive(Request $request, User $user)
    {
        abort_if($user->id === $request->user()->id, 422, 'Tidak bisa menonaktifkan akun sendiri.');
        $this->guardLastSuperAdmin($user);

        $new = $user->status === 'active' ? 'inactive' : 'active';
        $user->update(['status' => $new]);
        if ($new === 'inactive') {
            SsoIssuer::revokeUser($user, 'dinonaktifkan');
        }
        ActivityLogger::log('user.'.($new === 'active' ? 'activated' : 'deactivated'), ($new === 'active' ? 'Mengaktifkan' : 'Menonaktifkan')." akun {$user->email}", $user);

        return back()->with('success', $new === 'active' ? 'Akun diaktifkan.' : 'Akun dinonaktifkan; semua sesi modulnya dicabut.');
    }

    /** Ganti seluruh penugasan: peran Portal (opsional) + daftar modul/role. */
    public function updateAccess(Request $request, User $user)
    {
        $data = $request->validate([
            'core_role' => ['nullable', Rule::in(array_keys(config('portal.core_roles')))],
            'assignments' => 'array',
            'assignments.*.module' => ['required', Rule::in(array_keys(ModuleCatalog::all()))],
            'assignments.*.role' => 'required|string',
            'assignments.*.permissions' => 'nullable|array',
            'assignments.*.permissions.*' => 'string',
        ]);

        $rows = [];
        foreach ($data['assignments'] ?? [] as $a) {
            $m = ModuleCatalog::find($a['module']);
            abort_unless(array_key_exists($a['role'], $m['roles']), 422, "Role \"{$a['role']}\" tidak ada di modul {$m['name']}.");
            abort_if(isset($rows[$a['module']]), 422, "Modul {$m['name']} diisi dua kali. Satu user hanya boleh punya SATU role per modul.");

            $perms = null;
            if (array_key_exists('permissions', $a) && $a['permissions'] !== null && isset($m['permissions'])) {
                $perms = array_values(array_intersect($a['permissions'], array_keys($m['permissions'])));
            }
            $rows[$a['module']] = ['role' => $a['role'], 'permissions' => $perms];
        }

        if ($user->id === $request->user()->id && ($data['core_role'] ?? null) !== 'super-admin' && $user->hasRole('super-admin')) {
            abort(422, 'Kamu tidak bisa mencabut peran Super Admin dari akunmu sendiri.');
        }
        if (($data['core_role'] ?? null) !== 'super-admin') {
            $this->guardLastSuperAdmin($user);
        }
        abort_if(($data['core_role'] ?? null) === 'super-admin' && ! $request->user()->hasRole('super-admin'), 403, 'Hanya Super Admin yang boleh memberi peran Super Admin.');

        DB::transaction(function () use ($user, $rows, $data, $request) {
            $before = $user->assignments()->get()->keyBy('module');

            foreach ($before as $module => $old) {
                if (! isset($rows[$module])) {
                    $old->delete();
                    SsoIssuer::revokeUser($user, 'penugasan dicabut', $module);
                }
            }
            foreach ($rows as $module => $row) {
                ModuleAssignment::updateOrCreate(['user_id' => $user->id, 'module' => $module], $row + ['granted_by' => $request->user()->id]);
            }

            $user->syncRoles(array_filter([$data['core_role'] ?? null]));
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        });

        $summary = collect($rows)->map(fn ($r, $m) => "{$m}={$r['role']}")->implode(', ') ?: 'tanpa modul';
        ActivityLogger::log('access.updated', "Mengubah penugasan {$user->email}: {$summary}".(($data['core_role'] ?? null) ? "; core={$data['core_role']}" : ''), $user);

        return back()->with('success', 'Penugasan disimpan. Perubahan role berlaku di modul dalam ±1 menit; modul yang dicabut langsung menendang user.');
    }

    public function resetPassword(Request $request, User $user)
    {
        $temp = Str::password(12, symbols: false);
        $user->forceFill(['password' => $temp, 'must_change_password' => true])->save();
        SsoIssuer::revokeUser($user, 'reset password');
        [$sent] = app(NotificationMailer::class)->accountCreated($user->email, $user->name, $temp);
        ActivityLogger::log('user.password-reset', "Reset password {$user->email}", $user);

        return back()->with('success', $sent ? 'Password sementara dikirim ke email user.' : "Email gagal. Password sementara: {$temp}");
    }

    private function guardLastSuperAdmin(User $target): void
    {
        if ($target->hasRole('super-admin')) {
            $others = User::role('super-admin')->where('status', 'active')->where('id', '!=', $target->id)->count();
            abort_if($others === 0, 422, 'Ini Super Admin aktif terakhir; tidak boleh dinonaktifkan/dicabut.');
        }
    }
}
