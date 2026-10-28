<?php

namespace App\Support;

use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Pembatasan data berdasarkan struktur organisasi.
 *
 * Aturan: pemegang izin "employees.manage" (super-admin, hr-admin) = "HR-wide", melihat & bertindak atas
 * SEMUA karyawan. Pengguna lain yang punya izin modul (mis. role "manajer") hanya boleh melihat/bertindak
 * atas TIMNYA: karyawan yang atasan langsungnya (supervisor_id) adalah dia, atau karyawan di departemen
 * yang dia pimpin (departments.manager_employee_id). Tanpa data karyawan sendiri -> tidak melihat apa pun.
 */
class TeamScope
{
    public static function isHrWide(User $user): bool
    {
        return $user->can('employees.manage');
    }

    /** Batasi query Employee ke tim milik $user (atau tak dibatasi bila HR-wide). */
    public static function constrainEmployees(Builder $query, User $user): Builder
    {
        if (self::isHrWide($user)) {
            return $query;
        }

        $me = $user->employee;
        if (! $me) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $w) use ($me) {
            $w->where('employees.supervisor_id', $me->id)
                ->orWhereIn('employees.department_id', Department::where('manager_employee_id', $me->id)->select('id'));
        });
    }

    /** Batasi query model yang punya relasi "employee" (Leave, Attendance, PerformanceReview, ...). */
    public static function constrain(Builder $query, User $user, string $relation = 'employee'): Builder
    {
        if (self::isHrWide($user)) {
            return $query;
        }

        return $query->whereHas($relation, fn (Builder $q) => self::constrainEmployees($q, $user));
    }

    /** Pastikan $user berhak bertindak atas $target; kalau tidak -> 403. */
    public static function assertCanAct(User $user, Employee $target): void
    {
        if (self::isHrWide($user)) {
            return;
        }

        $allowed = self::constrainEmployees(Employee::query()->whereKey($target->id), $user)->exists();
        abort_unless($allowed, 403, 'Karyawan ini bukan bagian dari timmu.');
    }
}
