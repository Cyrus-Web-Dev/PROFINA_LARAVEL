<?php

namespace App\Http\Controllers;

use App\Exports\ArrayExport;
use App\Models\Employee;
use App\Models\PayrollItem;
use App\Models\PayrollRun;
use App\Services\AuditLedger;
use App\Services\PayslipService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:reports.view')->only('index');
        $this->middleware('permission:reports.export')->except('index');
    }

    public function index()
    {
        return Inertia::render('Reports/Index', [
            'runs' => PayrollRun::whereNotNull('locked_at')->orderByDesc('period_year')->orderByDesc('period_month')->limit(36)->get(['id', 'run_number', 'title']),
            'employees' => Employee::orderBy('full_name')->get(['id', 'nip', 'full_name']),
            'year' => (int) now()->year,
        ]);
    }

    /** Rekap PPh 21 per karyawan per masa (dasar penyusunan bukti potong; cocokkan dengan format Coretax terbaru). */
    public function pph21(Request $request)
    {
        $d = $request->validate(['year' => 'required|integer', 'month' => 'nullable|integer|between:1,12']);
        $rows = $this->items($d['year'], $d['month'] ?? null)->get()->map(fn ($i) => [
            $i->run->period_year, $i->run->period_month, $i->run->run_number, $i->employee_snapshot['nip'] ?? '', $i->employee_snapshot['full_name'] ?? '', $i->employee_snapshot['ptkp'] ?? '',
            ($i->employee_snapshot['has_npwp'] ?? false) ? 'Ya' : 'Tidak', $i->tax_gross, $i->pph21_method, $i->ter_category, $i->ter_rate, $i->pph21,
        ])->all();
        AuditLedger::record('report.exported', null, ['report' => 'pph21', 'year' => $d['year'], 'month' => $d['month'] ?? null], 'Rekap PPh 21');

        return Excel::download(new ArrayExport($rows, ['Tahun', 'Bulan', 'Putaran', 'NIP', 'Nama', 'PTKP', 'NPWP', 'Bruto kena pajak', 'Metode', 'Kategori TER', 'Tarif TER %', 'PPh 21'], 'PPh21'), 'rekap-pph21-'.$d['year'].'.xlsx');
    }

    public function bpjs(Request $request)
    {
        $d = $request->validate(['year' => 'required|integer', 'month' => 'nullable|integer|between:1,12']);
        $rows = $this->items($d['year'], $d['month'] ?? null)->get()->map(fn ($i) => [
            $i->run->period_year, $i->run->period_month, $i->employee_snapshot['nip'] ?? '', $i->employee_snapshot['full_name'] ?? '',
            $i->bpjs_kes_employee, $i->bpjs_kes_company, $i->bpjs_jht_employee, $i->bpjs_jht_company, $i->bpjs_jkk_company, $i->bpjs_jkm_company, $i->bpjs_jp_employee, $i->bpjs_jp_company,
        ])->all();

        return Excel::download(new ArrayExport($rows, ['Tahun', 'Bulan', 'NIP', 'Nama', 'Kes (K)', 'Kes (P)', 'JHT (K)', 'JHT (P)', 'JKK (P)', 'JKM (P)', 'JP (K)', 'JP (P)'], 'BPJS'), 'rekap-bpjs-'.$d['year'].'.xlsx');
    }

    public function departments(Request $request)
    {
        $d = $request->validate(['year' => 'required|integer']);
        $agg = [];
        foreach ($this->items($d['year'], null)->get() as $i) {
            $k = $i->employee_snapshot['department'] ?? '(tanpa departemen)';
            $m = $i->run->period_month;
            $agg[$k][$m] = ($agg[$k][$m] ?? 0) + $i->employer_cost;
        }
        $rows = [];
        foreach ($agg as $dept => $months) {
            $row = [$dept];
            for ($m = 1; $m <= 12; $m++) {
                $row[] = $months[$m] ?? 0;
            }
            $row[] = array_sum($months);
            $rows[] = $row;
        }

        return Excel::download(new ArrayExport($rows, array_merge(['Departemen'], array_map(fn ($m) => 'Bln '.$m, range(1, 12)), ['Total']), 'BiayaDept'), 'biaya-departemen-'.$d['year'].'.xlsx');
    }

    /** Bukti potong tahunan ringkas (mirip 1721-A1) per karyawan - dokumen kerja, BUKAN pengganti BPA1 resmi dari Coretax. */
    public function annualTax(Request $request, PayslipService $slips)
    {
        $d = $request->validate(['employee_id' => 'required|exists:employees,id', 'year' => 'required|integer']);
        $e = Employee::findOrFail($d['employee_id']);
        $items = $this->items($d['year'], null)->where('employee_id', $e->id)->get();
        abort_if($items->isEmpty(), 404, 'Tidak ada data payroll karyawan ini pada tahun tersebut.');

        $months = $items->groupBy(fn ($i) => $i->run->period_month)->map(fn ($g) => ['gross' => $g->sum('tax_gross'), 'pph' => $g->sum('pph21'), 'pension' => $g->sum(fn ($i) => $i->bpjs_jht_employee + $i->bpjs_jp_employee)])->sortKeys();
        AuditLedger::record('report.exported', $e, ['report' => 'annual_tax', 'year' => $d['year']], $e->nip);

        return Pdf::loadView('pdf.annual_tax', ['e' => $e, 'year' => $d['year'], 'months' => $months, 'company' => $slips->company(),
            'total' => ['gross' => $months->sum('gross'), 'pph' => $months->sum('pph'), 'pension' => $months->sum('pension')]])->download('ringkasan-pph21-'.$e->nip.'-'.$d['year'].'.pdf');
    }

    private function items(int $year, ?int $month)
    {
        return PayrollItem::query()->with('run:id,run_number,period_year,period_month')
            ->whereHas('run', fn ($r) => $r->where('period_year', $year)->whereNotNull('locked_at')->whereNotIn('status', ['cancelled', 'rejected'])->when($month, fn ($x) => $x->where('period_month', $month)))
            ->orderBy('payroll_run_id')->orderBy('id');
    }
}
