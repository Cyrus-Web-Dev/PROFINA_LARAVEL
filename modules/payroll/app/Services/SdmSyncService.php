<?php

namespace App\Services;

use App\Models\AttendanceSnapshot;
use App\Models\Department;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Menyalin data karyawan & absensi dari SDM ke PAYBRIDGE (cermin baca-saja). Perubahan data sensitif (rekening,
 * NPWP) dicatat ke jejak audit; perubahan rekening menandai bank_changed_at (memicu verifikasi pada payroll berikutnya).
 */
class SdmSyncService
{
    public function __construct(private SdmClient $sdm)
    {
    }

    /** @return array{created:int,updated:int,unchanged:int} */
    public function syncEmployees(?string $updatedSince = null): array
    {
        $stats = ['created' => 0, 'updated' => 0, 'unchanged' => 0];
        $page = 1;
        do {
            $res = $this->sdm->employees($page, $updatedSince);
            foreach ($res['data'] as $row) {
                $stats[$this->upsert($row)]++;
            }
            $last = (int) ($res['meta']['last_page'] ?? 1);
            $page++;
        } while ($page <= $last);

        return $stats;
    }

    public function syncOne(string $nip): string
    {
        return $this->upsert($this->sdm->employee($nip));
    }

    /** @return 'created'|'updated'|'unchanged' */
    public function upsert(array $row): string
    {
        $dept = null;
        if (! empty($row['department']['code'])) {
            $dept = Department::updateOrCreate(['code' => $row['department']['code']], ['name' => $row['department']['name'] ?? $row['department']['code'], 'sdm_id' => $row['department']['id'] ?? null]);
        }

        $acct = $row['bank']['account_number'] ?? null;
        $new = [
            'sdm_id' => $row['id'] ?? null, 'nip' => $row['nip'], 'full_name' => $row['full_name'], 'gender' => $row['gender'] ?? null,
            'email' => $row['email'] ?? null, 'phone' => $row['phone'] ?? null, 'department_id' => $dept?->id,
            'position_title' => $row['position'] ?? null, 'employment_type' => $row['employment_type'] ?? 'kontrak',
            'employment_status' => $row['employment_status'] ?? 'active', 'join_date' => $row['join_date'] ?? null,
            'contract_end_date' => $row['contract_end_date'] ?? null, 'resign_date' => $row['resign_date'] ?? null,
            'ptkp_status' => $row['ptkp_status'] ?? 'TK/0', 'sdm_base_salary' => (float) ($row['base_salary'] ?? 0),
        ];
        $sensitive = [];
        if (array_key_exists('tax', $row) || array_key_exists('bank', $row)) {
            $npwp = $row['tax']['npwp'] ?? null;
            $sensitive = [
                'npwp' => $npwp, 'has_npwp' => filled($npwp),
                'birth_date' => $row['birth_date'] ?? null,
                'bank_name' => $row['bank']['name'] ?? null, 'bank_account_number' => $acct,
                'bank_account_hash' => Employee::hashAccount($acct), 'bank_account_name' => $row['bank']['account_name'] ?? null,
                'bpjs_kesehatan_number' => $row['bpjs']['kesehatan'] ?? null, 'bpjs_tk_number' => $row['bpjs']['ketenagakerjaan'] ?? null,
            ];
        }

        $hash = hash('sha256', json_encode([$new, $sensitive]));
        $emp = Employee::withTrashed()->where('nip', $row['nip'])->first();

        if (! $emp) {
            $emp = Employee::create($new + $sensitive + ['synced_at' => now(), 'sync_hash' => $hash, 'bank_changed_at' => null]);
            AuditLedger::record('employee.synced', $emp, ['nip' => $emp->nip, 'action' => 'created'], $emp->nip);

            return 'created';
        }
        if ($emp->sync_hash === $hash) {
            $emp->forceFill(['synced_at' => now()])->saveQuietly();

            return 'unchanged';
        }

        $bankChanged = $sensitive && $emp->bank_account_hash !== $sensitive['bank_account_hash'];
        $changed = array_keys(array_filter($new + $sensitive, fn ($v, $k) => $k !== 'bank_account_number' && $k !== 'npwp' && $emp->getRawOriginal($k) != $v && ! in_array($k, ['birth_date', 'bpjs_kesehatan_number', 'bpjs_tk_number'], true), ARRAY_FILTER_USE_BOTH));

        $emp->fill($new + $sensitive);
        $emp->deleted_at = null;
        $emp->synced_at = now();
        $emp->sync_hash = $hash;
        if ($bankChanged) {
            $emp->bank_changed_at = now();
        }
        $emp->save();

        AuditLedger::record($bankChanged ? 'employee.bank_changed' : 'employee.synced', $emp, ['nip' => $emp->nip, 'fields' => $changed, 'bank_changed' => $bankChanged], $emp->nip);

        return 'updated';
    }

    /** Tarik & simpan ringkasan absensi sebulan untuk semua karyawan. Mengembalikan jumlah baris. */
    public function syncAttendance(int $year, int $month, bool $detail = true): int
    {
        $data = $this->sdm->attendanceSummary($year, $month, $detail);
        $n = 0;
        DB::transaction(function () use ($data, $year, $month, &$n) {
            foreach ($data as $row) {
                $emp = Employee::where('nip', $row['nip'])->first();
                if (! $emp) {
                    continue;
                }
                $payload = [
                    'scheduled_days' => (int) ($row['scheduled_days'] ?? 0), 'present_days' => (float) ($row['present_days'] ?? 0),
                    'late_count' => (int) ($row['late_count'] ?? 0), 'late_minutes' => (int) ($row['late_minutes'] ?? 0),
                    'alpha_days' => (float) ($row['alpha_days'] ?? 0), 'sick_days' => (float) ($row['sick_days'] ?? 0),
                    'permit_days' => (float) ($row['permit_days'] ?? 0), 'paid_leave_days' => (float) ($row['paid_leave_days'] ?? 0),
                    'unpaid_leave_days' => (float) ($row['unpaid_leave_days'] ?? 0), 'wfh_days' => (float) ($row['wfh_days'] ?? 0),
                    'details' => $row['days'] ?? null,
                ];
                AttendanceSnapshot::updateOrCreate(
                    ['employee_id' => $emp->id, 'year' => $year, 'month' => $month],
                    $payload + ['source_hash' => hash('sha256', json_encode($payload)), 'fetched_at' => Carbon::now()]
                );
                $n++;
            }
        });

        return $n;
    }
}
