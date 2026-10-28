<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\EmployeeLoan;
use App\Services\AuditLedger;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LoanController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:loans.view')->only(['index']);
        $this->middleware('permission:loans.manage')->only(['store']);
        $this->middleware('permission:loans.approve')->only(['decide']);
    }

    public function index(Request $request)
    {
        return Inertia::render('Loans/Index', [
            'loans' => EmployeeLoan::with('employee:id,nip,full_name')->when($request->status, fn ($q, $s) => $q->where('status', $s))->orderByDesc('id')->paginate(20)->withQueryString()
                ->through(fn ($l) => ['id' => $l->id, 'number' => $l->loan_number, 'employee' => $l->employee->full_name, 'nip' => $l->employee->nip, 'principal' => $l->principal, 'installments' => $l->installments,
                    'installment_amount' => $l->installment_amount, 'paid' => $l->paid_installments, 'remaining' => $l->remaining, 'status' => $l->status, 'start' => sprintf('%02d/%d', $l->start_month, $l->start_year), 'reason' => $l->reason]),
            'employees' => Employee::where('employment_status', 'active')->orderBy('full_name')->get(['id', 'nip', 'full_name']),
            'filters' => $request->only('status'),
        ]);
    }

    public function store(Request $request)
    {
        $d = $request->validate(['employee_id' => 'required|exists:employees,id', 'principal' => 'required|numeric|min:100000|max:999999999', 'installments' => 'required|integer|between:1,60',
            'start_year' => 'required|integer|between:2024,2100', 'start_month' => 'required|integer|between:1,12', 'reason' => 'required|string|min:5|max:255']);
        $l = EmployeeLoan::create($d + [
            'loan_number' => 'KSB-'.now()->format('ym').'-'.str_pad((string) (EmployeeLoan::count() + 1), 4, '0', STR_PAD_LEFT),
            'installment_amount' => ceil($d['principal'] / $d['installments']), 'remaining' => $d['principal'], 'status' => 'pending', 'requested_by' => $request->user()->id,
        ]);
        AuditLedger::record('loan.created', $l, ['principal' => $l->principal, 'installments' => $l->installments], $l->loan_number);

        return back()->with('success', 'Kasbon diajukan; menunggu persetujuan.');
    }

    public function decide(Request $request, EmployeeLoan $loan)
    {
        $d = $request->validate(['decision' => 'required|in:approved,rejected']);
        $u = $request->user();
        if ($loan->status !== 'pending') {
            return back()->with('error', 'Kasbon sudah diputuskan.');
        }
        if (config('pay.controls.sod_enforced') && $loan->requested_by === $u->id) {
            return back()->with('error', 'Pemisahan tugas: pengaju tidak boleh menyetujui kasbon yang diajukannya.');
        }
        if (Employee::where('user_id', $u->id)->where('id', $loan->employee_id)->exists()) {
            return back()->with('error', 'Konflik kepentingan: tidak boleh memutuskan kasbon untuk dirimu sendiri.');
        }
        $loan->update(['status' => $d['decision'] === 'approved' ? 'active' : 'rejected', 'approved_by' => $u->id, 'approved_at' => now()]);
        AuditLedger::record('loan.'.$d['decision'], $loan, [], $loan->loan_number);

        return back()->with('success', 'Keputusan kasbon dicatat.');
    }
}
