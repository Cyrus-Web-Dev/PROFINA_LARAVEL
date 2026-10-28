<?php

namespace App\Http\Controllers;

use App\Models\AttendanceSnapshot;
use App\Models\Employee;
use App\Models\PayComponent;
use App\Models\PayrollInput;
use App\Models\Setting;
use App\Services\AuditLedger;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/** Input variabel (lembur, bonus, tunjangan insidentil, reimburse, potongan, koreksi): pengaju != penyetuju. */
class InputController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:inputs.view')->only(['index']);
        $this->middleware('permission:inputs.create')->only(['store', 'cancel']);
        $this->middleware('permission:inputs.approve')->only(['decide']);
    }

    public function index(Request $request)
    {
        $q = PayrollInput::query()->with(['employee:id,nip,full_name', 'requester:id,name', 'approver:id,name'])
            ->when($request->status, fn ($x, $s) => $x->where('status', $s))
            ->when($request->type, fn ($x, $t) => $x->where('type', $t))
            ->when($request->search, fn ($x, $s) => $x->whereHas('employee', fn ($e) => $e->where('full_name', 'ilike', "%$s%")->orWhere('nip', 'ilike', "%$s%")))
            ->orderByDesc('id');

        return Inertia::render('Inputs/Index', [
            'inputs' => $q->paginate(20)->withQueryString()->through(fn ($i) => [
                'id' => $i->id, 'employee' => $i->employee->full_name, 'nip' => $i->employee->nip, 'type' => $i->type, 'period' => sprintf('%02d/%d', $i->period_month, $i->period_year),
                'work_date' => $i->work_date?->toDateString(), 'hours' => $i->hours, 'amount' => $i->amount, 'description' => $i->description, 'status' => $i->status,
                'requested_by' => $i->requester?->name, 'approved_by' => $i->approver?->name, 'check' => $i->attendance_check, 'note' => $i->approval_note,
            ]),
            'employees' => Employee::where('employment_status', 'active')->orderBy('full_name')->get(['id', 'nip', 'full_name']),
            'components' => PayComponent::where('is_active', true)->where('category', '!=', 'basic')->orderBy('name')->get(['id', 'name', 'type']),
            'filters' => $request->only('status', 'type', 'search'),
            'largeThreshold' => (float) config('pay.controls.large_input_amount'),
            'types' => PayrollInput::TYPE_LABELS,
        ]);
    }

    public function store(Request $request)
    {
        $d = $request->validate([
            'employee_id' => 'required|exists:employees,id', 'type' => 'required|in:overtime,bonus,allowance,reimbursement,deduction,correction',
            'period_year' => 'required|integer|between:2024,2100', 'period_month' => 'required|integer|between:1,12',
            'pay_component_id' => 'nullable|exists:pay_components,id', 'description' => 'required|string|min:5|max:255',
            'work_date' => 'required_if:type,overtime|nullable|date', 'day_type' => 'required_if:type,overtime|nullable|in:weekday,restday,holiday',
            'hours' => 'required_if:type,overtime|nullable|numeric|min:0.25|max:12', 'amount' => 'required_unless:type,overtime|nullable|numeric|min:1|max:9999999999',
        ]);

        if ($d['type'] === 'overtime') {
            $d['amount'] = 0;
            $d['attendance_check'] = $this->checkOvertime((int) $d['employee_id'], $d['work_date'], $d['day_type'], (float) $d['hours']);
            if ($d['attendance_check']['ok'] === false) {
                throw ValidationException::withMessages(['hours' => $d['attendance_check']['message']]);
            }
        }
        $in = PayrollInput::create($d + ['status' => 'pending', 'requested_by' => $request->user()->id]);
        AuditLedger::record('input.created', $in, ['type' => $in->type, 'amount' => $in->amount, 'hours' => $in->hours, 'employee_id' => $in->employee_id], PayrollInput::TYPE_LABELS[$in->type]);

        return back()->with('success', 'Input dicatat. Menunggu persetujuan orang lain.');
    }

    /** Cocokkan jam lembur dengan absensi SDM (jam pulang melebihi jam kerja). */
    private function checkOvertime(int $employeeId, string $date, string $dayType, float $hours): array
    {
        $date = \Carbon\Carbon::parse($date);
        $snap = AttendanceSnapshot::where('employee_id', $employeeId)->where('year', $date->year)->where('month', $date->month)->first();
        $row = collect($snap?->details ?? [])->firstWhere('date', $date->toDateString());
        if (! $snap || ! $row || empty($row['check_out'])) {
            return ['ok' => null, 'message' => 'Belum bisa diverifikasi: absensi SDM untuk tanggal ini belum ditarik/kosong. Tarik data SDM lalu ajukan ulang, atau minta persetujuan dengan bukti SPL.'];
        }
        $out = \Carbon\Carbon::parse($date->toDateString().' '.$row['check_out']);
        if ($dayType === 'weekday') {
            $end = \Carbon\Carbon::parse($date->toDateString().' '.Setting::get('work.end_time', '17:00'));
            $worked = max(0, $end->diffInMinutes($out, false) / 60);
        } else {
            if (empty($row['check_in'])) {
                return ['ok' => false, 'message' => 'Tidak ada jam masuk pada absensi hari libur tersebut.'];
            }
            $worked = \Carbon\Carbon::parse($date->toDateString().' '.$row['check_in'])->diffInMinutes($out, false) / 60;
        }
        $ok = $hours <= $worked + 0.25;

        return ['ok' => $ok, 'worked_hours' => round($worked, 2), 'message' => $ok ? 'Sesuai absensi SDM (tercatat '.round($worked, 2).' jam).' : 'Jam lembur ('.$hours.') melebihi yang tercatat di absensi SDM ('.round($worked, 2).' jam).'];
    }

    public function decide(Request $request, PayrollInput $input)
    {
        $d = $request->validate(['decision' => 'required|in:approved,rejected', 'note' => 'nullable|string|max:500']);
        $u = $request->user();
        if ($input->status !== 'pending') {
            return back()->with('error', 'Input ini sudah diputuskan.');
        }
        if (config('pay.controls.sod_enforced') && $input->requested_by === $u->id) {
            return back()->with('error', 'Pemisahan tugas: pengaju tidak boleh menyetujui inputnya sendiri.');
        }
        if (Employee::where('user_id', $u->id)->where('id', $input->employee_id)->exists()) {
            return back()->with('error', 'Konflik kepentingan: kamu tidak boleh memutuskan input untuk dirimu sendiri.');
        }
        if ($input->amount >= (float) config('pay.controls.large_input_amount') && ! $u->can('inputs.approve-large')) {
            return back()->with('error', 'Nominal besar: butuh penyetuju level Keuangan.');
        }
        if ($d['decision'] === 'rejected' && blank($d['note'] ?? null)) {
            return back()->with('error', 'Alasan penolakan wajib diisi.');
        }
        $input->update(['status' => $d['decision'], 'approved_by' => $u->id, 'approved_at' => now(), 'approval_note' => $d['note'] ?? null]);
        AuditLedger::record('input.'.$d['decision'], $input, ['note' => $d['note'] ?? null, 'amount' => $input->amount], PayrollInput::TYPE_LABELS[$input->type]);

        return back()->with('success', $d['decision'] === 'approved' ? 'Input disetujui.' : 'Input ditolak.');
    }

    public function cancel(Request $request, PayrollInput $input)
    {
        if ($input->status !== 'pending' || $input->requested_by !== $request->user()->id) {
            return back()->with('error', 'Hanya pengaju yang bisa membatalkan input yang masih menunggu.');
        }
        $input->update(['status' => 'cancelled']);

        return back()->with('success', 'Input dibatalkan.');
    }
}
