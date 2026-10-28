<?php

namespace App\Http\Controllers;

use App\Models\PayrollItem;
use App\Services\PayslipService;
use Illuminate\Http\Request;
use Inertia\Inertia;

/** Layanan mandiri karyawan: hanya slip miliknya sendiri yang sudah terbit. */
class PayslipController extends Controller
{
    public function mine(Request $request)
    {
        $emp = $request->user()->employee;
        $items = $emp ? PayrollItem::where('employee_id', $emp->id)->whereNotNull('payslip_published_at')->with('run:id,title,run_number,period_month,period_year')->orderByDesc('id')->get() : collect();

        return Inertia::render('Payslips/Mine', [
            'linked' => (bool) $emp,
            'payslips' => $items->map(fn ($i) => ['id' => $i->id, 'title' => $i->run->title, 'period' => sprintf('%02d/%d', $i->run->period_month, $i->run->period_year), 'gross' => $i->gross_earnings, 'net' => $i->net_pay, 'pph21' => $i->pph21,
                'bpjs' => $i->bpjs_kes_employee + $i->bpjs_jht_employee + $i->bpjs_jp_employee]),
        ]);
    }

    public function download(Request $request, PayrollItem $item, PayslipService $slips)
    {
        $emp = $request->user()->employee;
        $own = $emp && $item->employee_id === $emp->id && $item->payslip_published_at;
        abort_unless($own || $request->user()->can('runs.view'), 403);

        $pdf = $slips->pdf($item, false);
        abort_if(! $pdf, 500, 'Slip gagal dibuat.');

        return response($pdf, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="slip-'.($item->employee_snapshot['nip'] ?? $item->id).'-'.$item->run->run_number.'.pdf"']);
    }
}
