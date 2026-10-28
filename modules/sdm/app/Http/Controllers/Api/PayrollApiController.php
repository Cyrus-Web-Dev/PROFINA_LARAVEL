<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Leave;
use App\Services\HolidayService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * REST API SDM untuk PAYBRIDGE (prefix /api/payroll/v1). Auth: Bearer token klien integrasi (bukan token user Sanctum).
 * Respons: {"data": ..., "meta": ...} atau {"error": {"code","message"}}. Data sensitif hanya bila token ber-scope employees:sensitive.
 */
class PayrollApiController extends Controller
{
    public function __construct(private HolidayService $holidays)
    {
    }

    public function ping(Request $request): JsonResponse
    {
        $c = $request->attributes->get('integration_client');

        return response()->json(['data' => ['app' => 'SDM', 'client' => $c->name, 'scopes' => $c->scopes, 'time' => now()->toIso8601String()]]);
    }

    public function employees(Request $request): JsonResponse
    {
        $sensitive = $this->wantsSensitive($request);
        $p = Employee::with(['department:id,code,name', 'position:id,title'])
            ->when($request->updated_since, fn ($q, $t) => $q->where('updated_at', '>=', $t))
            ->when($request->status, fn ($q, $s) => $q->where('employment_status', $s))
            ->orderBy('id')->paginate(min((int) $request->get('per_page', 100), 200));

        return response()->json([
            'data' => $p->getCollection()->map(fn ($e) => $this->transform($e, $sensitive))->values(),
            'meta' => ['page' => $p->currentPage(), 'last_page' => $p->lastPage(), 'total' => $p->total()],
        ]);
    }

    public function employee(Request $request, string $nip): JsonResponse
    {
        $e = Employee::with(['department:id,code,name', 'position:id,title'])->where('nip', $nip)->first();
        if (! $e) {
            return response()->json(['error' => ['code' => 'not_found', 'message' => 'Karyawan tidak ditemukan.']], 404);
        }

        return response()->json(['data' => $this->transform($e, $this->wantsSensitive($request))]);
    }

    public function departments(): JsonResponse
    {
        return response()->json(['data' => Department::orderBy('name')->get(['id', 'code', 'name'])]);
    }

    public function holidays(Request $request): JsonResponse
    {
        $y = (int) ($request->year ?: now()->year);

        return response()->json(['data' => $this->holidays->between(Carbon::create($y, 1, 1), Carbon::create($y, 12, 31))]);
    }

    /**
     * Ringkasan absensi & cuti sebulan per karyawan. Hari kerja efektif mengikuti kalender libur SDM; cuti dihitung dari
     * tabel cuti (disetujui), bukan dari status absensi. detail=1 menyertakan baris harian untuk mencocokkan lembur.
     */
    public function attendanceSummary(Request $request): JsonResponse
    {
        $d = $request->validate(['year' => 'required|integer|between:2020,2100', 'month' => 'required|integer|between:1,12', 'nip' => 'nullable|string', 'detail' => 'nullable|boolean']);
        $start = Carbon::create($d['year'], $d['month'], 1)->startOfDay();
        $end = $start->copy()->endOfMonth()->startOfDay();
        $detail = (bool) ($d['detail'] ?? false);

        $workdays = [];
        $hol = $this->holidays->between($start, $end);
        for ($x = $start->copy(); $x->lte($end); $x->addDay()) {
            if (! $this->holidays->isWeekend($x) && ! isset($hol[$x->toDateString()])) {
                $workdays[] = $x->toDateString();
            }
        }

        $employees = Employee::query()->whereDate('join_date', '<=', $end)
            ->where(fn ($q) => $q->whereNull('resign_date')->orWhereDate('resign_date', '>=', $start))
            ->when($d['nip'] ?? null, fn ($q, $n) => $q->where('nip', $n))->orderBy('id')->get(['id', 'nip', 'join_date', 'resign_date']);

        $att = Attendance::whereBetween('date', [$start->toDateString(), $end->toDateString()])->whereIn('employee_id', $employees->pluck('id'))->get()->groupBy('employee_id');
        $leaves = Leave::with('leaveType:id,is_paid')->where('status', 'approved')->whereDate('start_date', '<=', $end)->whereDate('end_date', '>=', $start)
            ->whereIn('employee_id', $employees->pluck('id'))->get()->groupBy('employee_id');
        $unrecordedAsAlpha = (bool) config('hr.payroll.count_unrecorded_as_alpha', false);

        $rows = [];
        foreach ($employees as $e) {
            $from = max($start->toDateString(), $e->join_date?->toDateString() ?? $start->toDateString());
            $to = min($end->toDateString(), $e->resign_date?->toDateString() ?? $end->toDateString());
            $window = array_values(array_filter($workdays, fn ($w) => $w >= $from && $w <= $to));

            // cuti
            $paidLeave = 0.0;
            $unpaidLeave = 0.0;
            $leaveDates = [];
            foreach ($leaves[$e->id] ?? [] as $l) {
                $dates = array_values(array_filter($window, fn ($w) => $w >= $l->start_date->toDateString() && $w <= $l->end_date->toDateString()));
                $days = ($l->start_date->eq($l->end_date) && (float) $l->total_days < 1 && count($dates) === 1) ? (float) $l->total_days : (float) count($dates);
                foreach ($dates as $dt) {
                    $leaveDates[$dt] = true;
                }
                $l->leaveType?->is_paid === false ? $unpaidLeave += $days : $paidLeave += $days;
            }

            $byDate = ($att[$e->id] ?? collect())->keyBy(fn ($a) => $a->date->toDateString());
            $present = $late = $lateMin = $alpha = $sick = $permit = $wfh = 0;
            $unrecorded = 0;
            foreach ($window as $w) {
                if (isset($leaveDates[$w])) {
                    continue;
                }
                $a = $byDate[$w] ?? null;
                if (! $a) {
                    $unrecorded++;
                    continue;
                }
                switch ($a->status) {
                    case 'hadir': $present++; break;
                    case 'terlambat': $present++; $late++; $lateMin += (int) $a->late_minutes; break;
                    case 'wfh': $wfh++; break;
                    case 'sakit': $sick++; break;
                    case 'izin': $permit++; break;
                    case 'alpha': $alpha++; break;
                }
            }

            $row = [
                'nip' => $e->nip, 'scheduled_days' => count($window), 'present_days' => $present, 'late_count' => $late, 'late_minutes' => $lateMin,
                'alpha_days' => $alpha + ($unrecordedAsAlpha ? $unrecorded : 0), 'sick_days' => $sick, 'permit_days' => $permit,
                'paid_leave_days' => $paidLeave, 'unpaid_leave_days' => $unpaidLeave, 'wfh_days' => $wfh, 'unrecorded_days' => $unrecorded,
            ];
            if ($detail) {
                $row['days'] = $byDate->map(fn ($a) => ['date' => $a->date->toDateString(), 'status' => $a->status, 'check_in' => $a->check_in ? substr((string) $a->check_in, 0, 5) : null, 'check_out' => $a->check_out ? substr((string) $a->check_out, 0, 5) : null])->values();
            }
            $rows[] = $row;
        }

        return response()->json(['data' => $rows, 'meta' => ['year' => (int) $d['year'], 'month' => (int) $d['month'], 'working_days_in_month' => count($workdays), 'unrecorded_counted_as_alpha' => $unrecordedAsAlpha]]);
    }

    private function wantsSensitive(Request $request): bool
    {
        return $request->boolean('sensitive') && $request->attributes->get('integration_client')->can('employees:sensitive');
    }

    private function transform(Employee $e, bool $sensitive): array
    {
        $row = [
            'id' => $e->id, 'nip' => $e->nip, 'full_name' => $e->full_name, 'gender' => $e->gender, 'email' => $e->personal_email, 'phone' => $e->phone,
            'department' => $e->department ? ['id' => $e->department->id, 'code' => $e->department->code, 'name' => $e->department->name] : null,
            'position' => $e->position?->title, 'employment_type' => $e->employment_type, 'employment_status' => $e->employment_status,
            'join_date' => $e->join_date?->toDateString(), 'contract_end_date' => $e->contract_end_date?->toDateString(), 'resign_date' => $e->resign_date?->toDateString(),
            'ptkp_status' => $e->ptkp_status ?: 'TK/0', 'base_salary' => (float) $e->base_salary, 'updated_at' => $e->updated_at?->toIso8601String(),
        ];
        if ($sensitive) {
            $row['birth_date'] = $e->date_of_birth?->toDateString();
            $row['tax'] = ['npwp' => $e->npwp];
            $row['bank'] = ['name' => $e->bank_name, 'account_number' => $e->bank_account_number, 'account_name' => $e->bank_account_name];
            $row['bpjs'] = ['kesehatan' => $e->bpjs_kesehatan_number, 'ketenagakerjaan' => $e->bpjs_tk_number];
        }

        return $row;
    }
}
