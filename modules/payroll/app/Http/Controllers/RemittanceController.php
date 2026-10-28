<?php

namespace App\Http\Controllers;

use App\Models\Remittance;
use App\Services\RemittanceService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RemittanceController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:remittances.view')->only('index');
        $this->middleware('permission:remittances.manage')->only('markPaid');
    }

    public function index(Request $request)
    {
        return Inertia::render('Remittances/Index', [
            'remittances' => Remittance::with('run:id,run_number,title')->when($request->status, fn ($q, $s) => $q->where('status', $s))->orderBy('due_date')->paginate(20)->withQueryString()
                ->through(fn ($r) => ['id' => $r->id, 'type' => $r->type, 'run' => $r->run->title, 'run_id' => $r->run->id, 'period' => sprintf('%02d/%d', $r->period_month, $r->period_year), 'amount' => $r->amount,
                    'due' => $r->due_date->toDateString(), 'report_due' => $r->report_due_date?->toDateString(), 'status' => $r->status, 'ref' => $r->payment_ref, 'paid_at' => $r->paid_at?->format('d/m/Y'), 'paid_by' => $r->paid_by_name,
                    'overdue' => $r->status === 'pending' && $r->due_date->isPast()]),
            'filters' => $request->only('status'),
        ]);
    }

    public function markPaid(Request $request, Remittance $remittance, RemittanceService $svc)
    {
        $d = $request->validate(['ref' => 'required|string|max:80']);
        $svc->markPaid($remittance, $request->user()->name.' (manual)', $d['ref'], null, null);

        return back()->with('success', 'Setoran dicatat terbayar. Pastikan jurnalnya dibuat di Keuangan.');
    }
}
