<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Leave;
use App\Models\LeaveType;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/** Logika pengajuan cuti yang dipakai bersama oleh web (Inertia) dan REST API. */
class LeaveService
{
    public function __construct(private HolidayService $holidays, private NotificationGateway $gateway)
    {
    }

    /**
     * Saldo cuti per jenis (hanya jenis yang punya jatah tahunan > 0). Pengajuan pending & disetujui dihitung
     * sebagai terpakai; yang ditolak/dibatalkan tidak. Cuti dihitung ke tahun tanggal MULAI-nya.
     *
     * @return array<int, array{leave_type_id:int,name:string,quota:int,used:float,remaining:float}>
     */
    public function balances(Employee $employee, ?int $year = null): array
    {
        $year ??= (int) today()->year;

        return LeaveType::where('default_quota_per_year', '>', 0)->orderBy('name')->get()->map(function (LeaveType $type) use ($employee, $year) {
            $used = $this->usedDays($employee, $type, $year);

            return [
                'leave_type_id' => $type->id,
                'name' => $type->name,
                'quota' => (int) $type->default_quota_per_year,
                'used' => $used,
                'remaining' => max(0, $type->default_quota_per_year - $used),
            ];
        })->all();
    }

    public function usedDays(Employee $employee, LeaveType $type, int $year): float
    {
        return (float) Leave::where('employee_id', $employee->id)
            ->where('leave_type_id', $type->id)
            ->whereIn('status', ['pending', 'approved'])
            ->whereYear('start_date', $year)
            ->sum('total_days');
    }

    /** @throws ValidationException */
    public function submit(Employee $employee, array $data, ?UploadedFile $attachment = null, ?int $actingUserId = null): Leave
    {
        $type = LeaveType::findOrFail($data['leave_type_id']);
        $start = Carbon::parse($data['start_date'])->startOfDay();
        $end = Carbon::parse($data['end_date'])->startOfDay();

        if ($end->lt($start)) {
            throw ValidationException::withMessages(['end_date' => 'Tanggal selesai tidak boleh sebelum tanggal mulai.']);
        }
        // Cuti biasa harus diajukan sebelum/pada hari mulai; jenis yang butuh surat (mis. sakit) boleh diajukan
        // belakangan, tapi maksimal 30 hari ke belakang.
        if ($start->lt(today()) && ! $type->requires_attachment) {
            throw ValidationException::withMessages(['start_date' => 'Tanggal mulai tidak boleh sebelum hari ini untuk jenis cuti ini.']);
        }
        if ($start->lt(today()->subDays(30))) {
            throw ValidationException::withMessages(['start_date' => 'Pengajuan surut maksimal 30 hari ke belakang.']);
        }
        if ((int) $start->diffInDays($end, true) > 60) {
            throw ValidationException::withMessages(['end_date' => 'Satu pengajuan maksimal 60 hari. Pecah menjadi beberapa pengajuan.']);
        }
        if ($type->requires_attachment && ! $attachment) {
            throw ValidationException::withMessages(['attachment' => "Jenis \"{$type->name}\" wajib melampirkan dokumen pendukung (mis. surat dokter)."]);
        }

        $days = $this->holidays->workingDays($start, $end);
        if ($days < 1) {
            throw ValidationException::withMessages(['end_date' => 'Semua tanggal yang dipilih jatuh pada akhir pekan/hari libur, tidak perlu mengajukan cuti.']);
        }

        $overlap = Leave::where('employee_id', $employee->id)
            ->whereIn('status', ['pending', 'approved'])
            ->whereDate('start_date', '<=', $end)
            ->whereDate('end_date', '>=', $start)
            ->exists();
        if ($overlap) {
            throw ValidationException::withMessages(['start_date' => 'Tanggal ini bertumpang tindih dengan pengajuan cuti lain yang masih aktif.']);
        }

        if ($type->default_quota_per_year > 0) {
            $remaining = $type->default_quota_per_year - $this->usedDays($employee, $type, (int) $start->year);
            if ($days > $remaining) {
                throw ValidationException::withMessages(['end_date' => sprintf(
                    'Sisa jatah %s tahun %d tinggal %s hari, sedangkan pengajuanmu %d hari kerja.',
                    $type->name, $start->year, rtrim(rtrim(number_format(max(0, $remaining), 1), '0'), '.'), $days
                )]);
            }
        }

        $path = $attachment ? $attachment->store('leave-attachments', 'local') : null;

        $leave = Leave::create([
            'employee_id' => $employee->id,
            'leave_type_id' => $type->id,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'total_days' => $days,
            'reason' => $data['reason'],
            'attachment_path' => $path,
            'status' => 'pending',
        ]);

        ActivityLogger::log('leave.requested', "{$employee->full_name} mengajukan {$type->name} {$start->format('d/m/Y')} - {$end->format('d/m/Y')} ({$days} hari kerja)", $leave, $actingUserId);

        $this->gateway->alertAdmins("Pengajuan cuti baru dari {$employee->full_name}: {$type->name}, {$start->format('d/m/Y')} - {$end->format('d/m/Y')} ({$days} hari kerja). Mohon ditinjau di SDM HRIS.");

        return $leave;
    }
}
