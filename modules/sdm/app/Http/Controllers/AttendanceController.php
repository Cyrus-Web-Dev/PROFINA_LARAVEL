<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Employee;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Support\Geofence;
use App\Support\TeamScope;
use Inertia\Inertia;

class AttendanceController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:attendances.view')->only(['index']);
        $this->middleware('permission:attendances.manage')->only(['update']);
    }

    public function index(Request $request)
    {
        $date = $request->input('date', today()->toDateString());

        $query = TeamScope::constrain(
            Attendance::query()->with('employee:id,full_name,nip,department_id')->whereDate('date', $date),
            $request->user()
        );

        if ($departmentId = $request->input('department_id')) {
            $query->whereHas('employee', fn ($q) => $q->where('department_id', $departmentId));
        }

        return Inertia::render('Attendances/Index', [
            'attendances' => $query->get(),
            'date' => $date,
            'departments' => \App\Models\Department::orderBy('name')->get(['id', 'name']),
            'filters' => $request->only('department_id'),
        ]);
    }

    /** Karyawan check-in sendiri (self-service). */
    public function checkIn(Request $request)
    {
        $employee = $request->user()->employee;
        abort_unless($employee, 403, 'Akun kamu belum terhubung ke data karyawan.');

        $coords = $request->validate([
            'lat' => 'nullable|numeric|between:-90,90',
            'lng' => 'nullable|numeric|between:-180,180',
        ]);

        $today = today()->toDateString();
        $existing = Attendance::where('employee_id', $employee->id)->whereDate('date', $today)->first();
        if ($existing && $existing->check_in) {
            return back()->with('error', 'Kamu sudah check-in hari ini.');
        }
        // Hari ini sudah ditandai HR sebagai izin/sakit/cuti/libur -> tidak boleh ditimpa check-in.
        if ($existing && in_array($existing->status, ['izin', 'sakit', 'cuti', 'libur'], true)) {
            return back()->with('error', "Hari ini tercatat sebagai \"{$existing->status}\" oleh HR, tidak perlu check-in.");
        }

        $geo = Geofence::evaluate(isset($coords['lat']) ? (float) $coords['lat'] : null, isset($coords['lng']) ? (float) $coords['lng'] : null);
        if (! $geo['ok']) {
            return back()->with('error', $geo['message']);
        }

        $now = now();
        $workStart = today()->setTimeFromTimeString(config('hr.work_start_time').':00');
        $toleranceMinutes = (int) config('hr.late_tolerance_minutes');
        // (int) floor(...) WAJIB: di Carbon 3 diffInMinutes() mengembalikan float, sedangkan kolom late_minutes bertipe integer.
        $lateMinutes = $now->greaterThan($workStart) ? (int) floor($workStart->diffInMinutes($now, true)) : 0;
        $status = $lateMinutes > $toleranceMinutes ? 'terlambat' : 'hadir';

        Attendance::updateOrCreate(
            ['employee_id' => $employee->id, 'date' => $today],
            [
                'check_in' => $now->format('H:i:s'),
                'check_in_lat' => $coords['lat'] ?? null,
                'check_in_lng' => $coords['lng'] ?? null,
                'status' => $status,
                'late_minutes' => $lateMinutes,
                'notes' => $geo['note'],
            ]
        );

        return back()->with('success', 'Check-in berhasil dicatat pukul '.$now->format('H:i').'.');
    }

    public function checkOut(Request $request)
    {
        $employee = $request->user()->employee;
        abort_unless($employee, 403, 'Akun kamu belum terhubung ke data karyawan.');

        $today = today()->toDateString();
        $attendance = Attendance::where('employee_id', $employee->id)->whereDate('date', $today)->first();

        if (! $attendance || ! $attendance->check_in) {
            return back()->with('error', 'Kamu belum check-in hari ini.');
        }
        if ($attendance->check_out) {
            return back()->with('error', 'Kamu sudah check-out hari ini.');
        }

        $coords = $request->validate([
            'lat' => 'nullable|numeric|between:-90,90',
            'lng' => 'nullable|numeric|between:-180,180',
        ]);

        $attendance->update([
            'check_out' => now()->format('H:i:s'),
            'check_out_lat' => $coords['lat'] ?? null,
            'check_out_lng' => $coords['lng'] ?? null,
        ]);

        return back()->with('success', 'Check-out berhasil dicatat pukul '.now()->format('H:i').'.');
    }

    /** Admin/HR mengoreksi manual (mis. lupa absen, izin lisan). */
    public function update(Request $request, Attendance $attendance)
    {
        $data = $request->validate([
            'check_in' => 'nullable|date_format:H:i',
            'check_out' => 'nullable|date_format:H:i',
            'status' => 'required|in:hadir,terlambat,izin,sakit,alpha,cuti,wfh,libur',
            'notes' => 'nullable|string|max:500',
        ]);

        $attendance->update($data);
        ActivityLogger::log('attendance.corrected', "Mengoreksi absensi {$attendance->employee->full_name} tgl {$attendance->date->format('d/m/Y')}", $attendance, auth()->id());

        return back()->with('success', 'Absensi berhasil diperbarui.');
    }

    public function myHistory(Request $request)
    {
        $employee = $request->user()->employee;
        abort_unless($employee, 403);

        $month = $request->input('month', today()->format('Y-m'));

        return Inertia::render('Attendances/MyHistory', [
            'attendances' => Attendance::where('employee_id', $employee->id)
                ->whereRaw("to_char(date, 'YYYY-MM') = ?", [$month])
                ->orderBy('date')
                ->get(),
            'month' => $month,
        ]);
    }
}
