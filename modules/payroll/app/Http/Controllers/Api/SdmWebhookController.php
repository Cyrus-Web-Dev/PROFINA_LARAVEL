<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CompensationContract;
use App\Models\Employee;
use App\Models\InboundEvent;
use App\Services\AuditLedger;
use App\Services\ContractService;
use App\Services\SdmSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Penerima webhook SDM (POST /api/sdm/webhook). Keamanan: HMAC-SHA256 atas isi mentah (X-Sdm-Signature) + idempotensi
 * lewat X-Sdm-Delivery. Prinsip "event tipis": SDM hanya memberi tahu SIAPA yang berubah; data lengkap (rekening, NPWP)
 * ditarik lewat REST API ber-token, bukan dibawa di webhook.
 * Perubahan gaji dari SDM TIDAK PERNAH langsung berlaku: ia hanya menjadi USULAN kontrak (menunggu verifikasi & persetujuan).
 */
class SdmWebhookController extends Controller
{
    public function __invoke(Request $request, SdmSyncService $sync, ContractService $contracts): JsonResponse
    {
        $secret = config('pay.integrations.sdm.webhook_secret');
        if (! $secret) {
            return response()->json(['error' => 'Webhook belum dikonfigurasi (SDM_WEBHOOK_SECRET kosong).'], 503);
        }
        $body = $request->getContent();
        if (! hash_equals('sha256='.hash_hmac('sha256', $body, $secret), (string) $request->header('X-Sdm-Signature'))) {
            return response()->json(['error' => 'Tanda tangan tidak valid.'], 401);
        }
        $payload = json_decode($body, true);
        if (! is_array($payload)) {
            return response()->json(['error' => 'Isi bukan JSON.'], 400);
        }

        $event = (string) ($request->header('X-Sdm-Event') ?: ($payload['event'] ?? ''));
        if ($event === 'ping') {
            return response()->json(['ok' => true, 'pong' => true]);
        }
        $delivery = (string) $request->header('X-Sdm-Delivery');
        if ($delivery !== '' && InboundEvent::where('source', 'sdm')->where('delivery_id', $delivery)->exists()) {
            return response()->json(['ok' => true, 'duplicate' => true]);
        }

        $row = InboundEvent::create(['source' => 'sdm', 'delivery_id' => $delivery ?: null, 'event' => $event, 'payload' => $payload]);
        try {
            $status = $this->handle($event, $payload['data'] ?? [], $sync, $contracts, $delivery);
            $row->update(['status' => $status, 'processed_at' => now()]);
        } catch (Throwable $e) {
            // Tetap 200 agar SDM tidak mengulang tanpa henti; kejadian tersimpan & bisa diputar ulang dari halaman Integrasi.
            Log::error('[SDM webhook] '.$event.': '.$e->getMessage());
            $row->update(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 500)]);
        }

        return response()->json(['ok' => true]);
    }

    /** Dipakai juga oleh "Putar ulang" di halaman Integrasi. */
    public function handle(string $event, array $d, SdmSyncService $sync, ContractService $contracts, ?string $delivery = null): string
    {
        $nip = $d['nip'] ?? null;
        switch ($event) {
            case 'employee.created':
            case 'employee.updated':
            case 'employee.status_changed':
            case 'employee.bank_changed':
                if (! $nip) {
                    return 'ignored';
                }
                $sync->syncOne($nip);

                return 'processed';

            case 'employee.compensation_changed':
                if (! $nip || ! isset($d['new_base_salary'])) {
                    return 'ignored';
                }
                $sync->syncOne($nip);
                $emp = Employee::where('nip', $nip)->firstOrFail();
                $effective = $d['effective_date'] ?? now()->toDateString();
                $ref = 'sdm:'.($delivery ?: sha1($nip.$effective.$d['new_base_salary']));

                if (CompensationContract::where('sdm_ref', $ref)->exists()
                    || CompensationContract::where('employee_id', $emp->id)->whereIn('status', ['proposed', 'verified'])->where('base_salary', $d['new_base_salary'])->exists()) {
                    return 'ignored';
                }
                $base = $emp->activeContract()->with('components')->first();
                $data = [
                    'employee_id' => $emp->id, 'base_salary' => $d['new_base_salary'], 'effective_date' => $effective,
                    'contract_type' => $base->contract_type ?? ($emp->employment_type === 'tetap' ? 'pkwtt' : 'pkwt'), 'grade_id' => $base->grade_id ?? null,
                    'jkk_class' => $base->jkk_class ?? 1, 'bpjs_kes_enrolled' => $base->bpjs_kes_enrolled ?? true, 'bpjs_tk_enrolled' => $base->bpjs_tk_enrolled ?? true,
                    'tax_scheme' => $base->tax_scheme ?? 'ter', 'thr_eligible' => $base->thr_eligible ?? true, 'overtime_eligible' => $base->overtime_eligible ?? false,
                    'end_date' => $d['contract_end_date'] ?? $emp->contract_end_date?->toDateString(), 'document_ref' => $d['document_ref'] ?? null, 'sdm_ref' => $ref,
                    'reason' => 'Usulan dari SDM: '.($d['reason'] ?? 'perubahan gaji'), 'proposed_by_name' => ($d['changed_by'] ?? 'SDM').' (SDM)',
                ];
                $components = $base ? $base->components->map(fn ($c) => ['pay_component_id' => $c->pay_component_id, 'amount' => $c->amount])->all() : [];
                $contracts->propose($data, $components, null, 'sdm');
                AuditLedger::record('contract.proposed_from_sdm', $emp, ['nip' => $nip, 'old' => $d['old_base_salary'] ?? null, 'new' => $d['new_base_salary']], $nip);

                return 'processed';

            default:
                return 'ignored';
        }
    }
}
