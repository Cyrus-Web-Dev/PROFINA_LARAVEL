<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PayrollEvent;
use App\Models\PayrollFunding;
use App\Services\PayrollFundingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Penerima webhook PAYBRIDGE (POST /api/payroll/webhook). Event: funding.submitted | funding.cancelled | remittance.created | budget.approved.
 * HMAC-SHA256 (X-Paybridge-Signature) + idempotensi (X-Paybridge-Delivery). Handler TIDAK memanggil balik PAYBRIDGE.
 */
class PayrollWebhookController extends Controller
{
    public function __invoke(Request $request, PayrollFundingService $svc): JsonResponse
    {
        $secret = config('payroll.webhook_secret');
        if (! $secret) {
            return response()->json(['error' => 'Webhook belum dikonfigurasi (PAYROLL_WEBHOOK_SECRET kosong).'], 503);
        }
        $body = $request->getContent();
        if (! hash_equals('sha256='.hash_hmac('sha256', $body, $secret), (string) $request->header('X-Paybridge-Signature'))) {
            return response()->json(['error' => 'Tanda tangan tidak valid.'], 401);
        }
        $payload = json_decode($body, true);
        if (! is_array($payload)) {
            return response()->json(['error' => 'Isi bukan JSON.'], 400);
        }
        $event = (string) ($request->header('X-Paybridge-Event') ?: ($payload['event'] ?? ''));
        if ($event === 'ping') {
            return response()->json(['ok' => true, 'pong' => true]);
        }
        $delivery = (string) $request->header('X-Paybridge-Delivery');
        if ($delivery !== '' && PayrollEvent::where('delivery_id', $delivery)->exists()) {
            return response()->json(['ok' => true, 'duplicate' => true]);
        }

        $row = PayrollEvent::create(['delivery_id' => $delivery ?: null, 'event' => $event, 'payload' => $payload]);
        try {
            $d = $payload['data'] ?? [];
            $note = null;
            switch ($event) {
                case 'funding.submitted':
                    $svc->upsert($d);
                    break;
                case 'funding.cancelled':
                    PayrollFunding::where('payroll_number', $d['number'] ?? '')->whereNotIn('status', ['disbursed', 'closed'])->update(['status' => 'cancelled']);
                    break;
                case 'remittance.created':
                    $svc->upsertRemittance($d);
                    break;
                case 'budget.approved':
                    if (config('payroll.auto_apply_budget')) {
                        $note = $svc->applyBudget($d).' baris anggaran diterapkan';
                    } else {
                        $note = 'auto_apply_budget=false: tidak diterapkan';
                    }
                    break;
                default:
                    $row->update(['status' => 'ignored']);

                    return response()->json(['ok' => true]);
            }
            $row->update(['status' => 'processed', 'note' => $note]);
        } catch (Throwable $e) {
            // Tetap 200 agar PAYBRIDGE tidak mengulang terus; `payroll:sync` mengoreksi.
            Log::error('[PAYBRIDGE webhook] '.$event.': '.$e->getMessage());
            $row->update(['status' => 'failed', 'note' => mb_substr($e->getMessage(), 0, 400)]);
        }

        return response()->json(['ok' => true]);
    }
}
