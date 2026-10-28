<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PayrollItem;
use Illuminate\Http\Request;

class PayrollController extends Controller
{
    public function myPayslips(Request $request)
    {
        $employee = $request->user()->employee;
        abort_unless($employee, 422, 'Akun kamu belum terhubung ke data karyawan.');

        $items = PayrollItem::where('employee_id', $employee->id)
            ->where('status', 'paid')
            ->with('period')
            ->orderByDesc('payroll_period_id')
            ->get()
            ->map(fn ($item) => [
                'id' => $item->id,
                'period' => $item->period->label,
                'basic_salary' => $item->basic_salary,
                'total_deductions' => $item->total_deductions,
                'pph21' => $item->pph21,
                'net_salary' => $item->net_salary,
                'paid_at' => $item->paid_at?->toDateString(),
                'download_url' => route('payroll.items.slip', $item->id),
            ]);

        return response()->json(['data' => $items]);
    }
}
