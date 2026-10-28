<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Leave;
use App\Models\PayrollItem;
use App\Services\HolidayService;
use App\Services\LeaveService;
use App\Support\TeamScope;
use App\Models\Training;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(HolidayService $holidays, LeaveService $leaveService)
    {
        $user = auth()->user();

        // Karyawan biasa (tanpa izin employees.view) TIDAK boleh melihat statistik seluruh perusahaan:
        // tampilkan dashboard personal (absen hari ini, saldo cuti, slip gaji terakhir, pengumuman).
        if (! $user->can('employees.view')) {
            return $this->employeeDashboard($user, $holidays, $leaveService);
        }

        $totalActive = Employee::where('employment_status', 'active')->count();

        // PENTING: query agregat di bawah ini SENGAJA pakai DB::table() (query builder murni),
        // BUKAN Employee::query() (Eloquent). Kalau pakai Eloquent, hasilnya dihidrasi sebagai model
        // Employee, dan karena model Employee punya $appends (photo_url, employment_type_label, dst),
        // Laravel otomatis mencoba menghitung accessor itu untuk SETIAP baris - padahal baris agregat
        // ini sengaja cuma punya 1-2 kolom (mis. cuma "employment_type" + "total"), sehingga kolom lain
        // yang dibutuhkan accessor (mis. "employment_status") tidak ada dan berujung TypeError.
        // DB::table() mengembalikan objek stdClass biasa, bukan model, sehingga $appends tidak pernah tersentuh.
        $byDepartment = DB::table('employees')
            ->join('departments', 'departments.id', '=', 'employees.department_id')
            ->where('employment_status', 'active')
            ->whereNull('employees.deleted_at')
            ->select('departments.name', DB::raw('count(*) as total'))
            ->groupBy('departments.name')
            ->orderByDesc('total')
            ->get();

        $byEmploymentType = DB::table('employees')
            ->where('employment_status', 'active')
            ->whereNull('deleted_at')
            ->select('employment_type', DB::raw('count(*) as total'))
            ->groupBy('employment_type')
            ->get();

        // Manajer hanya melihat angka timnya; HR melihat seluruh perusahaan (lihat TeamScope).
        $todayAttendance = TeamScope::constrain(Attendance::query()->whereDate('date', today()), $user)
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $pendingLeaves = TeamScope::constrain(Leave::query()->where('status', 'pending'), $user)->count();

        $upcomingTrainings = Training::whereIn('status', ['scheduled', 'ongoing'])
            ->where('start_date', '>=', today())
            ->orderBy('start_date')
            ->limit(5)
            ->get(['id', 'title', 'start_date', 'location']);

        $expiringDocuments = DB::table('employee_documents')
            ->join('employees', 'employees.id', '=', 'employee_documents.employee_id')
            ->whereNotNull('employee_documents.expires_at')
            ->whereBetween('employee_documents.expires_at', [today(), today()->addDays(30)])
            ->select('employee_documents.title', 'employees.full_name', 'employee_documents.expires_at')
            ->orderBy('employee_documents.expires_at')
            ->limit(5)
            ->get();

        $newHiresLast6Months = DB::table('employees')
            ->where('join_date', '>=', now()->subMonths(6))
            ->whereNull('deleted_at')
            ->select(DB::raw("to_char(join_date, 'YYYY-MM') as month"), DB::raw('count(*) as total'))
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        return Inertia::render('Dashboard/Index', [
            'stats' => [
                'total_active_employees' => $totalActive,
                'pending_leaves' => $pendingLeaves,
                'today_present' => $todayAttendance->get('hadir', 0) + $todayAttendance->get('wfh', 0),
                'today_absent' => $todayAttendance->get('alpha', 0),
            ],
            'by_department' => $byDepartment,
            'by_employment_type' => $byEmploymentType,
            'today_attendance' => $todayAttendance,
            'upcoming_trainings' => $upcomingTrainings,
            // Dokumen pribadi hanya untuk pemegang izin documents.view (HR).
            'expiring_documents' => $user->can('documents.view') ? $expiringDocuments : [],
            'new_hires_trend' => $newHiresLast6Months,
            'upcoming_holidays' => $holidays->upcoming(5),
        ]);
    }

    private function employeeDashboard($user, HolidayService $holidays, LeaveService $leaveService)
    {
        $employee = $user->employee;

        $latestPayslip = null;
        $todayRecord = null;
        $balances = [];
        $pendingLeaves = 0;

        if ($employee) {
            $todayRecord = Attendance::where('employee_id', $employee->id)->whereDate('date', today())->first(['check_in', 'check_out', 'status', 'late_minutes']);
            $balances = $leaveService->balances($employee);
            $pendingLeaves = Leave::where('employee_id', $employee->id)->where('status', 'pending')->count();

            $item = PayrollItem::where('employee_id', $employee->id)->where('status', 'paid')->with('period')->orderByDesc('payroll_period_id')->first();
            if ($item) {
                $latestPayslip = ['id' => $item->id, 'period' => $item->period->label, 'net_salary' => $item->net_salary];
            }
        }

        return Inertia::render('Dashboard/Employee', [
            'employee' => $employee ? [
                'full_name' => $employee->full_name,
                'position' => $employee->position?->title,
                'department' => $employee->department?->name,
                'join_date' => $employee->join_date?->toDateString(),
            ] : null,
            'today' => $todayRecord,
            'balances' => $balances,
            'pending_leaves' => $pendingLeaves,
            'latest_payslip' => $latestPayslip,
            'announcements' => Announcement::orderByDesc('is_pinned')->orderByDesc('published_at')->limit(3)->get(['id', 'title', 'body', 'is_pinned', 'published_at']),
            'upcoming_holidays' => $holidays->upcoming(5),
        ]);
    }
}
