<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Leave;
use App\Models\LeaveType;
use App\Services\LeaveService;
use Illuminate\Http\Request;

class LeaveController extends Controller
{
    public function index(Request $request)
    {
        $employee = $request->user()->employee;
        abort_unless($employee, 422, 'Akun kamu belum terhubung ke data karyawan.');

        return response()->json([
            'data' => Leave::where('employee_id', $employee->id)->with('leaveType')->orderByDesc('created_at')->get(),
            'leave_types' => LeaveType::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, LeaveService $service)
    {
        $employee = $request->user()->employee;
        abort_unless($employee, 422, 'Akun kamu belum terhubung ke data karyawan.');

        $data = $request->validate([
            'leave_type_id' => 'required|exists:leave_types,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date',
            'reason' => 'required|string|max:1000',
            'attachment' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:3072',
        ]);

        $leave = $service->submit($employee, $data, $request->file('attachment'), $request->user()->id);

        return response()->json(['message' => 'Pengajuan cuti berhasil dikirim.', 'data' => $leave], 201);
    }

    /** Saldo cuti tahun berjalan per jenis cuti. */
    public function balances(Request $request, LeaveService $service)
    {
        $employee = $request->user()->employee;
        abort_unless($employee, 422, 'Akun kamu belum terhubung ke data karyawan.');

        return response()->json(['data' => $service->balances($employee)]);
    }

    public function cancel(Request $request, Leave $leave)
    {
        $employee = $request->user()->employee;
        abort_unless($employee && $leave->employee_id === $employee->id, 403);

        if ($leave->status !== 'pending') {
            return response()->json(['message' => 'Hanya pengajuan berstatus menunggu yang bisa dibatalkan.'], 422);
        }

        $leave->update(['status' => 'cancelled']);

        return response()->json(['message' => 'Pengajuan cuti dibatalkan.', 'data' => $leave]);
    }
}
