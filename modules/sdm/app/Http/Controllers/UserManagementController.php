<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\NotificationMailer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;

class UserManagementController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:users.view')->only(['index']);
        $this->middleware('permission:users.manage')->except(['index']);
    }

    public function index(Request $request)
    {
        $query = User::query()->with('employee:id,user_id,full_name,nip');

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        return Inertia::render('Users/Index', [
            'users' => $query->orderByRaw("case when status = 'pending' then 0 else 1 end")->orderByDesc('created_at')
                ->paginate(15)->withQueryString()->through(fn (User $u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'registration_nip' => $u->registration_nip,
                    'status' => $u->status,
                    'roles' => $u->getRoleNames(),
                    'employee_id' => $u->employee?->id,
                    'employee_name' => $u->employee?->full_name,
                    'created_at' => $u->created_at->format('d/m/Y H:i'),
                ]),
            'roles' => $this->assignableRoles($request->user()),
            'unlinkedEmployees' => Employee::whereNull('user_id')->where('employment_status', 'active')->orderBy('full_name')->get(['id', 'full_name', 'nip']),
            'filters' => $request->only('status'),
        ]);
    }

    /** Setujui pendaftar: aktifkan akun, beri role, dan (disarankan) hubungkan ke data karyawan. */
    public function approve(Request $request, User $user)
    {
        abort_unless($user->status === 'pending', 422, 'Akun ini tidak sedang menunggu persetujuan.');

        $data = $request->validate([
            'employee_id' => ['nullable', Rule::exists('employees', 'id')->whereNull('user_id')->whereNull('deleted_at')],
            'role' => ['nullable', Rule::in($this->assignableRoles($request->user()))],
        ]);

        DB::transaction(function () use ($user, $data, $request) {
            $user->update(['status' => 'active', 'approved_by' => $request->user()->id, 'approved_at' => now()]);
            $user->syncRoles([$data['role'] ?? 'karyawan']);

            if (! empty($data['employee_id'])) {
                Employee::whereKey($data['employee_id'])->update(['user_id' => $user->id]);
            }
        });

        ActivityLogger::log('user.approved', "Menyetujui akun: {$user->email} (role: ".($data['role'] ?? 'karyawan').')', $user, $request->user()->id);

        app(NotificationMailer::class)->accountApproved($user);

        return back()->with('success', 'Akun berhasil disetujui.'.(empty($data['employee_id']) ? ' Ingat: akun belum dihubungkan ke data karyawan.' : ''));
    }

    public function reject(Request $request, User $user)
    {
        abort_unless($user->status === 'pending', 422, 'Akun ini tidak sedang menunggu persetujuan.');

        $data = $request->validate(['status_note' => 'nullable|string|max:500']);
        $user->update(['status' => 'rejected', 'status_note' => $data['status_note'] ?? null]);

        ActivityLogger::log('user.rejected', "Menolak akun: {$user->email}", $user, $request->user()->id);
        app(NotificationMailer::class)->accountRejected($user, $data['status_note'] ?? null);

        return back()->with('success', 'Akun ditolak.');
    }

    /** Hubungkan (atau lepas, bila employee_id kosong) akun dengan data karyawan. */
    public function linkEmployee(Request $request, User $user)
    {
        $data = $request->validate([
            'employee_id' => ['nullable', Rule::exists('employees', 'id')->where(fn ($q) => $q->whereNull('deleted_at')->where(fn ($w) => $w->whereNull('user_id')->orWhere('user_id', $user->id)))],
        ]);

        DB::transaction(function () use ($user, $data) {
            Employee::where('user_id', $user->id)->update(['user_id' => null]);
            if (! empty($data['employee_id'])) {
                Employee::whereKey($data['employee_id'])->update(['user_id' => $user->id]);
            }
        });

        ActivityLogger::log('user.employee_linked', "Mengubah hubungan akun {$user->email} dengan data karyawan", $user, $request->user()->id);

        return back()->with('success', 'Hubungan akun dengan data karyawan diperbarui.');
    }

    public function toggleActive(Request $request, User $user)
    {
        abort_if($user->id === $request->user()->id, 403, 'Tidak bisa menonaktifkan akun sendiri.');
        abort_if($user->hasRole('super-admin') && ! $request->user()->hasRole('super-admin'), 403, 'Hanya super-admin yang boleh mengubah status super-admin.');

        $user->update(['status' => $user->status === 'active' ? 'inactive' : 'active']);
        if ($user->status !== 'active') {
            $user->tokens()->delete(); // akun nonaktif tidak boleh tetap punya token API aktif
        }
        ActivityLogger::log('user.status_changed', "Mengubah status akun {$user->email} menjadi {$user->status}", $user, $request->user()->id);

        return back()->with('success', 'Status akun diperbarui.');
    }

    public function updateRoles(Request $request, User $user)
    {
        $actor = $request->user();

        $data = $request->validate([
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => [Rule::exists('roles', 'name')],
        ]);

        // KEAMANAN (anti eskalasi hak akses): tidak boleh mengubah role akun sendiri, dan hanya super-admin
        // yang boleh memberi/mencabut role super-admin.
        abort_if($user->id === $actor->id, 403, 'Tidak bisa mengubah role akunmu sendiri.');
        $targetIsSuper = $user->hasRole('super-admin');
        $requestsSuper = in_array('super-admin', $data['roles'], true);
        abort_if(($targetIsSuper || $requestsSuper) && ! $actor->hasRole('super-admin'), 403, 'Hanya super-admin yang boleh mengubah role super-admin.');

        if ($targetIsSuper && ! $requestsSuper && User::role('super-admin')->where('status', 'active')->count() <= 1) {
            abort(422, 'Tidak bisa mencabut role super-admin terakhir.');
        }

        $user->syncRoles($data['roles']);
        ActivityLogger::log('user.roles_updated', "Mengubah role {$user->email} menjadi: ".implode(', ', $data['roles']), $user, $actor->id);

        return back()->with('success', 'Role pengguna berhasil diperbarui.');
    }

    /** @return array<int,string> */
    private function assignableRoles(User $actor): array
    {
        return $actor->hasRole('super-admin')
            ? Role::orderBy('name')->pluck('name')->all()
            : Role::where('name', '!=', 'super-admin')->orderBy('name')->pluck('name')->all();
    }
}
