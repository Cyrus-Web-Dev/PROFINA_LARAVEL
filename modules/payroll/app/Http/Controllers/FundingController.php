<?php

namespace App\Http\Controllers;

use App\Models\FundingRequest;
use App\Models\IntegrationMessage;
use Illuminate\Http\Request;
use Inertia\Inertia;

class FundingController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:funding.view');
    }

    public function index(Request $request)
    {
        IntegrationMessage::where('direction', 'in')->whereNull('read_at')->update(['read_at' => now()]);

        return Inertia::render('Funding/Index', [
            'fundings' => FundingRequest::with('run:id,run_number,title,period_month,period_year')->orderByDesc('id')->paginate(15)->through(fn ($f) => [
                'id' => $f->id, 'number' => $f->number, 'run_id' => $f->run->id, 'title' => $f->run->title, 'status' => $f->status, 'net' => $f->amount_net_total, 'disbursable' => $f->amount_disbursable,
                'decided_by' => $f->decided_by_name, 'note' => $f->decision_note, 'finance_ref' => $f->finance_ref, 'disbursed_at' => $f->disbursed_at?->format('d/m/Y H:i'), 'reference' => $f->disbursement_ref,
            ]),
            'messages' => IntegrationMessage::orderByDesc('id')->limit(30)->get(['id', 'direction', 'system', 'subject', 'body', 'author', 'created_at'])->map(fn ($m) => $m->toArray() + ['when' => $m->created_at->format('d/m/Y H:i')]),
            'labels' => FundingRequest::STATUS_LABELS,
        ]);
    }
}
