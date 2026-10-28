<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\WorkflowException;
use App\Http\Controllers\Controller;
use App\Models\FundingRequest;
use App\Models\Remittance;
use App\Services\AuditLedger;
use App\Services\BudgetService;
use App\Services\FundingService;
use App\Services\RemittanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * REST API PAYBRIDGE v1. Format: {"data": ...} atau {"error": {"code","message","details"}} (sama dengan PROCURA).
 * Sisi Keuangan: funding, remittances, budgets. Sisi SDM: contracts, payslips (ringkasan).
 */
class ApiController extends Controller
{
    public function ping(Request $request): JsonResponse
    {
        $c = $request->attributes->get('api_client');

        return response()->json(['data' => ['app' => 'PAYBRIDGE', 'client' => $c->name, 'system' => $c->system, 'scopes' => $c->scopes, 'time' => now()->toIso8601String()]]);
    }

    // ---------- Keuangan: pengajuan dana ----------
    public function fundingIndex(Request $request, FundingService $svc): JsonResponse
    {
        $q = FundingRequest::with('run')->orderByDesc('id')
            ->when($request->status, fn ($x, $s) => $x->whereIn('status', explode(',', $s)))
            ->when($request->updated_since, fn ($x, $t) => $x->where('updated_at', '>=', $t));
        $p = $q->paginate(min((int) $request->get('per_page', 50), 100));

        return response()->json(['data' => $p->getCollection()->map(fn ($f) => $svc->payload($f))->values(), 'meta' => ['page' => $p->currentPage(), 'last_page' => $p->lastPage(), 'total' => $p->total()]]);
    }

    public function fundingShow(string $number, FundingService $svc): JsonResponse
    {
        return response()->json(['data' => $svc->payload($this->funding($number))]);
    }

    public function fundingDecision(Request $request, string $number, FundingService $svc): JsonResponse
    {
        $d = $this->validated($request, ['decision' => 'required|in:approve,reject,revision', 'note' => 'nullable|string|max:1000', 'decided_by' => 'required|string|max:120', 'finance_ref' => 'nullable|string|max:80', 'approved_amount' => 'nullable|numeric']);
        $f = $this->funding($number);
        try {
            if ($d['decision'] === 'approve' && isset($d['approved_amount']) && abs((float) $d['approved_amount'] - $f->amount_net_total) > 0.01) {
                throw new WorkflowException('Nominal disetujui harus sama dengan total utang gaji Rp '.number_format($f->amount_net_total, 2, ',', '.').' (persetujuan sebagian tidak berlaku untuk payroll).');
            }
            $f = $svc->decide($f, $d['decision'], $d['note'] ?? null, $d['decided_by'], $d['finance_ref'] ?? null);
        } catch (WorkflowException $e) {
            return $this->workflow($e);
        }

        return response()->json(['data' => $svc->payload($f)]);
    }

    public function fundingDisbursement(Request $request, string $number, FundingService $svc): JsonResponse
    {
        $d = $this->validated($request, ['amount' => 'required|numeric|min:0', 'method' => 'nullable|string|max:20', 'reference' => 'nullable|string|max:80', 'disbursed_by' => 'required|string|max:120', 'journal_refs' => 'nullable|array']);
        $f = $this->funding($number);
        try {
            $f = $svc->disbursed($f, (float) $d['amount'], $d['reference'] ?? null, $d['disbursed_by']);
            if (! empty($d['journal_refs'])) {
                $f->update(['journal_refs' => $d['journal_refs']]);
            }
        } catch (WorkflowException $e) {
            return $this->workflow($e);
        }

        return response()->json(['data' => $svc->payload($f->fresh())]);
    }

    public function fundingNote(Request $request, string $number, FundingService $svc): JsonResponse
    {
        $d = $this->validated($request, ['subject' => 'required|string|max:200', 'body' => 'nullable|string|max:3000', 'author' => 'required|string|max:120']);
        $svc->note($this->funding($number), $d['subject'], $d['body'] ?? null, $d['author'], $request->header('Idempotency-Key'));

        return response()->json(['data' => ['ok' => true]], 201);
    }

    // ---------- Keuangan: setoran ----------
    public function remittanceIndex(Request $request, RemittanceService $svc): JsonResponse
    {
        $rows = Remittance::with('run')->when($request->status, fn ($q, $s) => $q->whereIn('status', explode(',', $s)))->orderBy('due_date')->limit(200)->get();

        return response()->json(['data' => $rows->map(fn ($r) => $svc->payload($r))->values()]);
    }

    public function remittancePaid(Request $request, int $id, RemittanceService $svc): JsonResponse
    {
        $d = $this->validated($request, ['paid_by' => 'required|string|max:120', 'reference' => 'required|string|max:80', 'finance_ref' => 'nullable|string|max:80', 'journal_ref' => 'nullable|string|max:60']);
        $r = Remittance::find($id) ?? abort(404, 'Setoran tidak ditemukan.');
        try {
            $r = $svc->markPaid($r, $d['paid_by'], $d['reference'], $d['finance_ref'] ?? null, $d['journal_ref'] ?? null);
        } catch (WorkflowException $e) {
            return $this->workflow($e);
        }

        return response()->json(['data' => $svc->payload($r)]);
    }

    // ---------- Anggaran ----------
    public function budgetActive(Request $request, BudgetService $svc): JsonResponse
    {
        $plan = $svc->activePlan((int) ($request->year ?: now()->year));
        if (! $plan) {
            return $this->error(404, 'not_found', 'Belum ada anggaran SDM aktif untuk tahun tersebut.');
        }
        $plan->load('lines');

        return response()->json(['data' => $svc->financePayload($plan)]);
    }

    // ---------- SDM: kontrak & slip (ringkasan) ----------
    public function contracts(Request $request): JsonResponse
    {
        $rows = \App\Models\CompensationContract::with('employee:id,nip')->when($request->nip, fn ($q, $n) => $q->whereHas('employee', fn ($e) => $e->where('nip', $n)))
            ->when($request->status, fn ($q, $s) => $q->whereIn('status', explode(',', $s)))->orderByDesc('id')->limit(100)->get();

        return response()->json(['data' => $rows->map(fn ($c) => ['contract_number' => $c->contract_number, 'nip' => $c->employee->nip, 'version' => $c->version, 'status' => $c->status,
            'effective_date' => $c->effective_date->toDateString(), 'end_date' => $c->end_date?->toDateString()])->values()]);
    }

    public function payslips(Request $request): JsonResponse
    {
        $nip = $request->validate(['nip' => 'required|string'])['nip'];
        $items = \App\Models\PayrollItem::whereHas('employee', fn ($e) => $e->where('nip', $nip))->whereNotNull('payslip_published_at')->with('run:id,run_number,period_year,period_month,type')->orderByDesc('id')->limit(36)->get();

        return response()->json(['data' => $items->map(fn ($i) => ['run_number' => $i->run->run_number, 'period_year' => (int) $i->run->period_year, 'period_month' => (int) $i->run->period_month, 'type' => $i->run->type, 'gross' => $i->gross_earnings, 'net' => $i->net_pay])->values()]);
    }

    // ---------- Auditor ----------
    public function auditHead(): JsonResponse
    {
        $r = AuditLedger::verify();

        return response()->json(['data' => ['ok' => $r['ok'], 'checked' => $r['checked'], 'head' => $r['head'], 'broken_at' => $r['broken_at'], 'reason' => $r['reason'], 'at' => now()->toIso8601String()]]);
    }

    // ---------- util ----------
    private function funding(string $number): FundingRequest
    {
        return FundingRequest::with('run')->where('number', $number)->orWhereHas('run', fn ($r) => $r->where('run_number', $number))->first() ?? abort(404, 'Pengajuan tidak ditemukan.');
    }

    private function validated(Request $request, array $rules): array
    {
        $v = Validator::make($request->all(), $rules);
        if ($v->fails()) {
            throw new ValidationException($v);
        }

        return $v->validated();
    }

    private function workflow(WorkflowException $e): JsonResponse
    {
        return $this->error(422, 'workflow_violation', $e->getMessage());
    }

    private function error(int $status, string $code, string $message, array $details = []): JsonResponse
    {
        return response()->json(['error' => ['code' => $code, 'message' => $message, 'details' => (object) $details]], $status);
    }
}
