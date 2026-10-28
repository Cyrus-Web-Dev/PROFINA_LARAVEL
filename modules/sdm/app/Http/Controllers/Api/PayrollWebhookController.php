<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\IntegrationInbox;
use App\Models\PayslipMirror;
use App\Models\User;
use App\Observers\EmployeePayrollObserver;
use App\Services\NotificationMailer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Penerima webhook PAYBRIDGE (POST /api/payroll/webhook). Verifikasi HMAC (X-Paybridge-Signature) dengan PAYROLL_WEBHOOK_SECRET,
 * idempoten lewat X-Paybridge-Delivery. Event: contract.activated | contract.rejected | payslip.published | run.paid | ping.
 */
class PayrollWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $secret = config('hr.payroll.webhook_secret');
        if (! $secret) {
            return response()->json(['error' => 'PAYROLL_WEBHOOK_SECRET belum diatur di .env SDM.'], 503);
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
        if ($delivery !== '' && IntegrationInbox::where('source', 'payroll')->where('delivery_id', $delivery)->exists()) {
            return response()->json(['ok' => true, 'duplicate' => true]);
        }

        $row = IntegrationInbox::create(['source' => 'payroll', 'delivery_id' => $delivery ?: null, 'event' => $event, 'payload' => $payload]);
        try {
            $row->update(['status' => $this->handle($event, $payload['data'] ?? []), 'processed_at' => now()]);
        } catch (Throwable $e) {
            Log::error('[Payroll webhook] '.$event.': '.$e->getMessage());
            $row->update(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 500)]);
        }

        return response()->json(['ok' => true]);
    }

    private function handle(string $event, array $d): string
    {
        switch ($event) {
            case 'contract.activated':
                $emp = Employee::where('nip', $d['nip'] ?? '')->first();
                if (! $emp || ! isset($d['base_salary'])) {
                    return 'ignored';
                }
                // Kontrak yang sudah disetujui di Payroll menjadi sumber kebenaran gaji: selaraskan SDM tanpa memicu webhook balik.
                EmployeePayrollObserver::silently(function () use ($emp, $d) {
                    $emp->forceFill(array_filter(['base_salary' => $d['base_salary'], 'contract_end_date' => $d['end_date'] ?? null], fn ($v) => $v !== null))->save();
                });

                return 'processed';

            case 'contract.rejected':
                $this->notifyHr('Usulan kontrak ditolak Payroll', 'Usulan perubahan gaji untuk NIP '.($d['nip'] ?? '?').' ('.($d['contract_number'] ?? '').') ditolak di PAYBRIDGE. Alasan: '.($d['note'] ?? '-').'. Gaji pokok di SDM mungkin berbeda dari kontrak yang berlaku; periksa dan perbaiki.');

                return 'processed';

            case 'payslip.published':
                foreach ($d['payslips'] ?? [] as $p) {
                    if (empty($p['nip'])) {
                        continue;
                    }
                    PayslipMirror::updateOrCreate(['run_number' => $d['run_number'], 'nip' => $p['nip']], [
                        'employee_id' => Employee::where('nip', $p['nip'])->value('id'), 'run_type' => $d['type'] ?? 'regular',
                        'period_year' => $d['period_year'], 'period_month' => $d['period_month'], 'gross' => $p['gross'] ?? 0, 'net' => $p['net'] ?? 0, 'published_at' => now(),
                    ]);
                }

                return 'processed';

            case 'run.paid':
                return 'processed';

            default:
                return 'ignored';
        }
    }

    private function notifyHr(string $subject, string $message): void
    {
        try {
            $mailer = app(NotificationMailer::class);
            foreach (User::role('hr-admin')->where('status', 'active')->get() as $u) {
                $mailer->custom($u->email, $u->name, $subject, $message);
            }
        } catch (Throwable $e) {
            Log::warning('[Payroll webhook] Gagal kirim notifikasi: '.$e->getMessage());
        }
    }
}
