<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AccountMailer;
use App\Services\ActivityLogger;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Kelola pengguna (khusus Manager/admin; dijaga middleware role:admin di routes/web.php):
 * persetujuan pendaftar, atur role/posisi/hak akses, nonaktifkan, dan kirim pesan lewat Gmail.
 */
class UserManagementController extends Controller
{
    private const STATUSES = ['pending', 'active', 'inactive', 'rejected'];

    public function index(Request $request)
    {
        $status = (string) $request->query('status', '');
        if ($status !== 'all' && ! in_array($status, self::STATUSES, true)) {
            $status = User::where('status', 'pending')->exists() ? 'pending' : 'active';
        }
        $search = trim((string) $request->query('search', ''));

        $users = User::with(['roles', 'permissions'])
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")))
            ->orderBy($status === 'pending' ? 'created_at' : 'name')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'status' => $u->status ?? 'active',
                'role' => $u->roleName(),
                'role_label' => $u->roleLabel(),
                'position' => $u->position,
                'position_label' => $u->positionLabel(),
                'job_title' => $u->job_title,
                'phone' => $u->phone,
                'avatar_url' => $u->avatar_url,
                'permissions' => $u->permissions->pluck('name')->values(), // hak akses per orang
                'registered_at' => $u->created_at?->format('d/m/Y H:i'),
                'approved_at' => $u->approved_at?->format('d/m/Y H:i'),
                'status_note' => $u->status_note,
                'is_self' => $u->id === $request->user()->id,
            ]);

        $counts = User::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return Inertia::render('Admin/Users', [
            'users' => $users,
            'counts' => [
                'pending' => (int) ($counts['pending'] ?? 0),
                'active' => (int) ($counts['active'] ?? 0),
                'inactive' => (int) ($counts['inactive'] ?? 0),
                'rejected' => (int) ($counts['rejected'] ?? 0),
            ],
            'filters' => ['status' => $status, 'search' => $search],
            'roles' => collect(User::ROLE_LABELS)->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values(),
            'positions' => collect(config('mkp.positions'))->map(fn ($p, $key) => ['key' => $key, 'label' => $p['label'], 'permissions' => $p['permissions']])->values(),
            'permissionOptions' => collect(config('mkp.permission_labels'))->map(fn ($label, $name) => ['name' => $name, 'label' => $label])->values(),
        ]);
    }

    public function approve(Request $request, User $user, AccountMailer $mailer)
    {
        if (! in_array($user->status, ['pending', 'rejected'], true)) {
            throw ValidationException::withMessages(['general' => 'Hanya akun yang menunggu / ditolak yang bisa disetujui.']);
        }

        $data = $this->validateAccess($request);
        $this->applyAccess($user, $data);

        $user->forceFill([
            'status' => 'active',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
            'status_note' => null,
        ])->save();

        ActivityLogger::log('user.approved', "Menyetujui {$user->email} sebagai {$user->roleLabel()}".($user->positionLabel() ? " ({$user->positionLabel()})" : ''));

        [$sent, $error] = $mailer->approved($user, $data['note'] ?? null);

        return $this->respond("{$user->name} disetujui.", $sent, $error, 'Email pemberitahuan');
    }

    public function reject(Request $request, User $user, AccountMailer $mailer)
    {
        if ($user->status !== 'pending') {
            throw ValidationException::withMessages(['general' => 'Hanya pendaftar yang menunggu persetujuan yang bisa ditolak.']);
        }

        $data = $request->validate(['reason' => 'required|string|max:500'], [
            'reason.required' => 'Tulis alasan penolakan singkat (akan dikirim ke pendaftar).',
        ]);

        $user->forceFill(['status' => 'rejected', 'status_note' => $data['reason'], 'approved_by' => null, 'approved_at' => null])->save();

        ActivityLogger::log('user.rejected', "Menolak pendaftaran {$user->email}: {$data['reason']}");

        [$sent, $error] = $mailer->rejected($user, $data['reason']);

        return $this->respond("Pendaftaran {$user->name} ditolak.", $sent, $error, 'Email pemberitahuan');
    }

    /** Ubah role / posisi / hak akses orang yang sudah aktif. */
    public function updateAccess(Request $request, User $user)
    {
        $data = $this->validateAccess($request);

        if ($user->hasRole('admin') && $data['role'] !== 'admin') {
            $this->assertNotLastAdmin($user, 'mengubah role');
        }

        $this->applyAccess($user, $data);

        ActivityLogger::log('user.access_updated', "Mengubah akses {$user->email}: {$user->roleLabel()}".($user->positionLabel() ? " / {$user->positionLabel()}" : ''));

        return back()->with('success', "Akses {$user->name} diperbarui.");
    }

    public function deactivate(Request $request, User $user)
    {
        if ($user->id === $request->user()->id) {
            throw ValidationException::withMessages(['general' => 'Kamu tidak bisa menonaktifkan akunmu sendiri.']);
        }
        if ($user->status !== 'active') {
            throw ValidationException::withMessages(['general' => 'Hanya akun aktif yang bisa dinonaktifkan.']);
        }
        $this->assertNotLastAdmin($user, 'menonaktifkan');

        $data = $request->validate(['reason' => 'nullable|string|max:500']);

        // remember_token diganti agar cookie "ingat saya" perangkat lain tidak berlaku lagi;
        // sesi yang sedang berjalan ditendang oleh middleware EnsureUserIsActive di request berikutnya.
        $user->forceFill(['status' => 'inactive', 'status_note' => $data['reason'] ?? null, 'remember_token' => Str::random(60)])->save();

        ActivityLogger::log('user.deactivated', "Menonaktifkan akun {$user->email}");

        return back()->with('success', "Akun {$user->name} dinonaktifkan.");
    }

    public function activate(User $user)
    {
        if ($user->status !== 'inactive') {
            throw ValidationException::withMessages(['general' => 'Hanya akun nonaktif yang bisa diaktifkan kembali.']);
        }

        $user->forceFill(['status' => 'active', 'status_note' => null])->save();

        ActivityLogger::log('user.activated', "Mengaktifkan kembali akun {$user->email}");

        return back()->with('success', "Akun {$user->name} diaktifkan kembali.");
    }

    public function message(Request $request, User $user, AccountMailer $mailer)
    {
        $data = $request->validate([
            'subject' => 'required|string|max:150',
            'message' => 'required|string|max:3000',
        ], [
            'subject.required' => 'Subjek wajib diisi.',
            'message.required' => 'Isi pesan wajib diisi.',
        ]);

        [$sent, $error] = $mailer->custom($user, $data['subject'], $data['message'], $request->user()->name);

        if (! $sent) {
            throw ValidationException::withMessages(['general' => "Pesan gagal terkirim ke {$user->email}. {$error}"]);
        }

        ActivityLogger::log('user.messaged', "Mengirim pesan ke {$user->email}: {$data['subject']}");

        return back()->with('success', "Pesan terkirim ke {$user->email}.");
    }

    // ------------------------------------------------------------------

    private function validateAccess(Request $request): array
    {
        return $request->validate([
            'role' => ['required', Rule::in(array_keys(User::ROLE_LABELS))],
            'position' => ['nullable', Rule::in(array_keys(config('mkp.positions')))],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in(array_keys(config('mkp.permission_labels')))],
            'note' => ['nullable', 'string', 'max:500'],
        ], [
            'role.required' => 'Pilih role.',
        ]);
    }

    private function applyAccess(User $user, array $data): void
    {
        // Pastikan role staff/admin/viewer ada (mis. pada instalasi lama yang masih memakai "accountant").
        if (Role::whereIn('name', ['admin', 'staff', 'viewer'])->count() < 3) {
            (new RolePermissionSeeder())->run();
        }

        $user->syncRoles([$data['role']]);
        // Hak akses per orang hanya berlaku untuk staf; Manager punya semuanya, Viewer hanya melihat.
        $user->syncPermissions($data['role'] === 'staff' ? ($data['permissions'] ?? []) : []);
        $user->forceFill(['position' => $data['position'] ?? null, 'role_label' => $data['role']])->save();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $user->unsetRelation('roles')->unsetRelation('permissions'); // supaya label role/akses yang dibaca setelahnya sudah terbaru
    }

    private function assertNotLastAdmin(User $user, string $action): void
    {
        $activeAdmins = User::role('admin')->where('status', 'active')->count();

        if ($user->hasRole('admin') && $user->status === 'active' && $activeAdmins <= 1) {
            throw ValidationException::withMessages(['general' => "Tidak bisa {$action}: ini satu-satunya Manager aktif. Jadikan orang lain Manager dulu."]);
        }
    }

    /** Aksi utama sukses walau email gagal; tampilkan keduanya dengan jelas. */
    private function respond(string $message, bool $emailSent, ?string $emailError, string $emailLabel)
    {
        $redirect = back()->with('success', $message.($emailSent ? " {$emailLabel} terkirim." : ''));

        return $emailSent ? $redirect : $redirect->with('error', "{$emailLabel} gagal terkirim: {$emailError}");
    }
}
