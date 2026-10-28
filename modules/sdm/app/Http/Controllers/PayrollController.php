<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\PayrollItem;
use App\Models\PayrollPeriod;
use App\Services\ActivityLogger;
use App\Services\NotificationMailer;
use App\Services\PayrollCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class PayrollController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:payroll.view')->only(['index', 'show']);
        $this->middleware('permission:payroll.manage')->only(['store', 'process', 'updateItem']);
        $this->middleware('permission:payroll.approve')->only(['markPaid']);
    }

    public function index()
    {
        return Inertia::render('Payroll/Index', [
            'external' => (bool) config('hr.payroll.external'),
            'periods' => PayrollPeriod::withCount('items')
                ->orderByDesc('period_year')->orderByDesc('period_month')
                ->get()
                ->map(fn ($p) => [
                    'id' => $p->id,
                    'label' => $p->label,
                    'status' => $p->status,
                    'items_count' => $p->items_count,
                    'processed_at' => $p->processed_at?->format('d/m/Y H:i'),
                ]),
        ]);
    }

    public function store(Request $request)
    {
        if (config('hr.payroll.external')) {
            return back()->with('error', 'Modul payroll SDM dialihkan ke PAYBRIDGE. Hitung, setujui, dan bayar gaji di sana.');
        }

        $data = $request->validate([
            'period_month' => 'required|integer|between:1,12',
            'period_year' => 'required|integer|between:2020,2100',
        ]);

        $exists = PayrollPeriod::where($data)->exists();
        if ($exists) {
            return back()->with('error', 'Periode payroll ini sudah ada.');
        }

        $period = PayrollPeriod::create($data);
        ActivityLogger::log('payroll.period_created', "Membuat periode payroll: {$period->label}", $period, auth()->id());

        return redirect()->route('payroll.show', $period)->with('success', 'Periode payroll dibuat.');
    }

    public function show(PayrollPeriod $payrollPeriod)
    {
        $payrollPeriod->load(['items.employee:id,full_name,nip,department_id', 'items.employee.department:id,name']);

        return Inertia::render('Payroll/Show', [
            'period' => $payrollPeriod,
            'items' => $payrollPeriod->items,
        ]);
    }

    /**
     * Hitung ulang seluruh komponen gaji (BPJS, PPh21, dst) untuk semua karyawan aktif
     * pada periode ini. Hanya bisa dijalankan selama status masih draft.
     */
    public function process(PayrollPeriod $payrollPeriod, PayrollCalculator $calculator)
    {
        if (config('hr.payroll.external')) {
            return back()->with('error', 'Modul payroll SDM dialihkan ke PAYBRIDGE. Hitung, setujui, dan bayar gaji di sana.');
        }

        if ($payrollPeriod->status !== 'draft') {
            return back()->with('error', 'Periode ini sudah diproses/dibayar dan tidak bisa dihitung ulang.');
        }

        DB::transaction(function () use ($payrollPeriod, $calculator) {
            $employees = Employee::where('employment_status', 'active')->get();

            foreach ($employees as $employee) {
                $calculated = $calculator->calculate($employee);

                PayrollItem::updateOrCreate(
                    ['payroll_period_id' => $payrollPeriod->id, 'employee_id' => $employee->id],
                    $calculated
                );
            }

            $payrollPeriod->update([
                'status' => 'processed',
                'processed_by' => auth()->id(),
                'processed_at' => now(),
            ]);
        });

        ActivityLogger::log('payroll.processed', "Memproses payroll periode: {$payrollPeriod->label}", $payrollPeriod, auth()->id());

        return back()->with('success', 'Payroll berhasil dihitung untuk seluruh karyawan aktif.');
    }

    /** Koreksi manual satu item sebelum ditandai lunas (mis. tambah tunjangan lembur). */
    public function updateItem(Request $request, PayrollItem $payrollItem)
    {
        if (config('hr.payroll.external')) {
            return back()->with('error', 'Modul payroll SDM dialihkan ke PAYBRIDGE. Hitung, setujui, dan bayar gaji di sana.');
        }

        abort_if($payrollItem->period->status === 'paid', 403, 'Periode sudah dibayar, tidak bisa diubah.');

        $data = $request->validate([
            'allowances' => 'nullable|array',
            'allowances.*.label' => 'required_with:allowances|string|max:100',
            'allowances.*.amount' => 'required_with:allowances|numeric|min:0',
            'other_deductions' => 'nullable|array',
            'other_deductions.*.label' => 'required_with:other_deductions|string|max:100',
            'other_deductions.*.amount' => 'required_with:other_deductions|numeric|min:0',
            'overtime_amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:500',
        ]);

        $calculator = app(PayrollCalculator::class);
        $recalculated = $calculator->calculate(
            $payrollItem->employee,
            $data['allowances'] ?? [],
            $data['other_deductions'] ?? [],
            $data['overtime_amount'] ?? 0
        );
        $recalculated['notes'] = $data['notes'] ?? null;

        $payrollItem->update($recalculated);
        ActivityLogger::log('payroll.item_updated', "Mengoreksi payroll {$payrollItem->employee->full_name}", $payrollItem, auth()->id());

        return back()->with('success', 'Item payroll berhasil diperbarui.');
    }

    /** Tandai seluruh periode sudah dibayar (mengunci semua item). */
    public function markPaid(PayrollPeriod $payrollPeriod)
    {
        if (config('hr.payroll.external')) {
            return back()->with('error', 'Modul payroll SDM dialihkan ke PAYBRIDGE. Hitung, setujui, dan bayar gaji di sana.');
        }

        if ($payrollPeriod->status !== 'processed') {
            return back()->with('error', 'Periode harus diproses terlebih dahulu sebelum ditandai lunas.');
        }

        DB::transaction(function () use ($payrollPeriod) {
            $payrollPeriod->update(['status' => 'paid', 'paid_at' => now()]);
            $payrollPeriod->items()->update(['status' => 'paid', 'paid_at' => now()]);
        });

        ActivityLogger::log('payroll.paid', "Menandai payroll periode {$payrollPeriod->label} sebagai lunas", $payrollPeriod, auth()->id());

        foreach ($payrollPeriod->items as $item) {
            $email = $item->employee->user?->email ?? $item->employee->personal_email;
            if ($email) {
                app(NotificationMailer::class)->payslipReady($email, $item->employee->full_name, $payrollPeriod->label, 'Rp '.number_format($item->net_salary, 0, ',', '.'));
            }
        }

        return back()->with('success', 'Payroll periode ini ditandai lunas dan slip gaji dikirim.');
    }

    public function myPayslips(Request $request)
    {
        $employee = $request->user()->employee;
        abort_unless($employee, 403);

        return Inertia::render('Payroll/MyPayslips', [
            'items' => PayrollItem::where('employee_id', $employee->id)
                ->where('status', 'paid')
                ->with('period')
                ->orderByDesc('payroll_period_id')
                ->get(),
        ]);
    }

    /** Unduh slip gaji sebagai PDF. Karyawan hanya bisa unduh miliknya sendiri; HR bisa unduh siapa saja. */
    public function downloadPayslip(Request $request, PayrollItem $payrollItem)
    {
        $employee = $request->user()->employee;
        $isOwner = $employee && $payrollItem->employee_id === $employee->id;
        abort_unless($isOwner || $request->user()->can('payroll.view'), 403);
        abort_unless($payrollItem->status === 'paid', 404, 'Slip gaji hanya bisa diunduh setelah periode ditandai lunas.');

        $payrollItem->load('employee.department', 'employee.position', 'period');

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.payslip', ['item' => $payrollItem])->setPaper('a4');

        return $pdf->download("Slip Gaji - {$payrollItem->employee->full_name} - {$payrollItem->period->label}.pdf");
    }
}
