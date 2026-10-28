<?php

namespace App\Http\Controllers;

use App\Models\Leave;
use App\Models\LeaveType;
use App\Services\ActivityLogger;
use App\Services\LeaveService;
use App\Services\NotificationGateway;
use App\Services\NotificationMailer;
use App\Support\TeamScope;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LeaveController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:leaves.view')->only(['index']);
        $this->middleware('permission:leaves.approve')->only(['approve', 'reject']);
    }

    public function index(Request $request)
    {
        $query = TeamScope::constrain(
            Leave::query()->with(['employee:id,full_name,nip,department_id', 'leaveType']),
            $request->user()
        );

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        return Inertia::render('Leaves/Index', [
            'leaves' => $query->orderByDesc('created_at')->paginate(15)->withQueryString(),
            'leaveTypes' => LeaveType::orderBy('name')->get(),
            'filters' => $request->only('status'),
        ]);
    }

    /** Karyawan mengajukan cuti sendiri (validasi bisnis lengkap ada di LeaveService). */
    public function store(Request $request, LeaveService $service)
    {
        $employee = $request->user()->employee;
        abort_unless($employee, 403, 'Akun kamu belum terhubung ke data karyawan.');

        $data = $request->validate([
            'leave_type_id' => 'required|exists:leave_types,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date',
            'reason' => 'required|string|max:1000',
            'attachment' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:3072',
        ]);

        $service->submit($employee, $data, $request->file('attachment'), $request->user()->id);

        return back()->with('success', 'Pengajuan cuti berhasil dikirim, menunggu persetujuan.');
    }

    public function approve(Request $request, Leave $leave)
    {
        $this->authorizeDecision($request, $leave);
        $leave->update(['status' => 'approved', 'approved_by' => auth()->id(), 'approved_at' => now()]);
        ActivityLogger::log('leave.approved', "Menyetujui cuti {$leave->employee->full_name}", $leave, auth()->id());

        if ($email = $this->notifyEmail($leave)) {
            app(NotificationMailer::class)->leaveApproved($email, $leave->employee->full_name, $leave->start_date->format('d/m/Y'), $leave->end_date->format('d/m/Y'));
        }
        app(NotificationGateway::class)->toEmployee($leave->employee, "Pengajuan cuti kamu ({$leave->start_date->format('d/m/Y')} - {$leave->end_date->format('d/m/Y')}) DISETUJUI.");

        return back()->with('success', 'Cuti disetujui.');
    }

    public function reject(Request $request, Leave $leave)
    {
        $this->authorizeDecision($request, $leave);
        $data = $request->validate(['rejection_reason' => 'required|string|max:500']);
        $leave->update(['status' => 'rejected', 'rejection_reason' => $data['rejection_reason'], 'approved_by' => auth()->id(), 'approved_at' => now()]);
        ActivityLogger::log('leave.rejected', "Menolak cuti {$leave->employee->full_name}", $leave, auth()->id());

        if ($email = $this->notifyEmail($leave)) {
            app(NotificationMailer::class)->leaveRejected($email, $leave->employee->full_name, $data['rejection_reason']);
        }
        app(NotificationGateway::class)->toEmployee($leave->employee, "Pengajuan cuti kamu ({$leave->start_date->format('d/m/Y')} - {$leave->end_date->format('d/m/Y')}) DITOLAK. Alasan: {$data['rejection_reason']}");

        return back()->with('success', 'Cuti ditolak.');
    }

    public function myLeaves(Request $request, LeaveService $service)
    {
        $employee = $request->user()->employee;
        abort_unless($employee, 403);

        return Inertia::render('Leaves/MyLeaves', [
            'leaves' => Leave::where('employee_id', $employee->id)->with('leaveType')->orderByDesc('created_at')->get(),
            'leaveTypes' => LeaveType::orderBy('name')->get(),
            'balances' => $service->balances($employee),
            'upcomingHolidays' => app(\App\Services\HolidayService::class)->upcoming(5),
        ]);
    }

    /** Karyawan membatalkan pengajuan cuti miliknya sendiri, hanya selama masih berstatus pending. */
    public function cancel(Request $request, Leave $leave)
    {
        $employee = $request->user()->employee;
        abort_unless($employee && $leave->employee_id === $employee->id, 403);

        if ($leave->status !== 'pending') {
            return back()->with('error', 'Hanya pengajuan yang masih menunggu persetujuan yang bisa dibatalkan.');
        }

        $leave->update(['status' => 'cancelled']);
        ActivityLogger::log('leave.cancelled', "{$employee->full_name} membatalkan pengajuan cuti", $leave, auth()->id());

        return back()->with('success', 'Pengajuan cuti dibatalkan.');
    }

    /** Hanya atasan tim/HR yang boleh memutuskan, tidak boleh menyetujui cuti sendiri, dan hanya yang masih pending. */
    private function authorizeDecision(Request $request, Leave $leave): void
    {
        $user = $request->user();
        TeamScope::assertCanAct($user, $leave->employee);
        abort_if($user->employee && $leave->employee_id === $user->employee->id, 403, 'Kamu tidak bisa memutuskan pengajuan cutimu sendiri.');
        abort_unless($leave->status === 'pending', 422, 'Pengajuan ini sudah diproses.');
    }

    private function notifyEmail(Leave $leave): ?string
    {
        return $leave->employee->user?->email ?? $leave->employee->personal_email;
    }
}
