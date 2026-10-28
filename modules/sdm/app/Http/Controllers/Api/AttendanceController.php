<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Support\Geofence;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function checkIn(Request $request)
    {
        $employee = $request->user()->employee;
        abort_unless($employee, 422, 'Akun kamu belum terhubung ke data karyawan.');

        $data = $request->validate(['lat' => 'nullable|numeric|between:-90,90', 'lng' => 'nullable|numeric|between:-180,180']);

        $today = today()->toDateString();
        $existing = Attendance::where('employee_id', $employee->id)->whereDate('date', $today)->first();
        if ($existing && $existing->check_in) {
            return response()->json(['message' => 'Kamu sudah check-in hari ini.'], 422);
        }
        if ($existing && in_array($existing->status, ['izin', 'sakit', 'cuti', 'libur'], true)) {
            return response()->json(['message' => "Hari ini tercatat sebagai \"{$existing->status}\" oleh HR."], 422);
        }

        $geo = Geofence::evaluate(isset($data['lat']) ? (float) $data['lat'] : null, isset($data['lng']) ? (float) $data['lng'] : null);
        if (! $geo['ok']) {
            return response()->json(['message' => $geo['message']], 422);
        }

        $now = now();
        $workStart = today()->setTimeFromTimeString(config('hr.work_start_time').':00');
        $toleranceMinutes = (int) config('hr.late_tolerance_minutes');
        $lateMinutes = $now->greaterThan($workStart) ? (int) floor($workStart->diffInMinutes($now, true)) : 0;
        $status = $lateMinutes > $toleranceMinutes ? 'terlambat' : 'hadir';

        $attendance = Attendance::updateOrCreate(
            ['employee_id' => $employee->id, 'date' => $today],
            ['check_in' => $now->format('H:i:s'), 'check_in_lat' => $data['lat'] ?? null, 'check_in_lng' => $data['lng'] ?? null, 'status' => $status, 'late_minutes' => $lateMinutes, 'notes' => $geo['note']]
        );

        return response()->json(['message' => 'Check-in berhasil.', 'attendance' => $attendance]);
    }

    public function checkOut(Request $request)
    {
        $employee = $request->user()->employee;
        abort_unless($employee, 422, 'Akun kamu belum terhubung ke data karyawan.');

        $attendance = Attendance::where('employee_id', $employee->id)->whereDate('date', today())->first();
        if (! $attendance || ! $attendance->check_in) {
            return response()->json(['message' => 'Kamu belum check-in hari ini.'], 422);
        }
        if ($attendance->check_out) {
            return response()->json(['message' => 'Kamu sudah check-out hari ini.'], 422);
        }

        $data = $request->validate(['lat' => 'nullable|numeric|between:-90,90', 'lng' => 'nullable|numeric|between:-180,180']);
        $attendance->update(['check_out' => now()->format('H:i:s'), 'check_out_lat' => $data['lat'] ?? null, 'check_out_lng' => $data['lng'] ?? null]);

        return response()->json(['message' => 'Check-out berhasil.', 'attendance' => $attendance]);
    }

    public function history(Request $request)
    {
        $employee = $request->user()->employee;
        abort_unless($employee, 422, 'Akun kamu belum terhubung ke data karyawan.');

        $month = $request->input('month', today()->format('Y-m'));

        $attendances = Attendance::where('employee_id', $employee->id)
            ->whereRaw("to_char(date, 'YYYY-MM') = ?", [$month])
            ->orderBy('date')
            ->get();

        return response()->json(['data' => $attendances, 'month' => $month]);
    }
}
