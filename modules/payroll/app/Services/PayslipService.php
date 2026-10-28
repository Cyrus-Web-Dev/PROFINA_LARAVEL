<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\PayrollItem;
use App\Models\PayrollRun;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;
use Throwable;

class PayslipService
{
    public function __construct(private NotificationMailer $mailer, private WebhookService $webhooks)
    {
    }

    /** Terbitkan slip: tandai terbit & kabari SDM (batch 100 per event, tanpa rincian pajak). */
    public function publish(PayrollRun $run): void
    {
        $run->items()->where('is_held', false)->whereNull('payslip_published_at')->update(['payslip_published_at' => now()]);
        $run->items()->where('is_held', false)->orderBy('id')->chunk(100, function ($chunk) use ($run) {
            $this->webhooks->dispatch('payslip.published', [
                'run_number' => $run->run_number, 'period_year' => (int) $run->period_year, 'period_month' => (int) $run->period_month, 'type' => $run->type,
                'payslips' => $chunk->map(fn ($i) => ['nip' => $i->employee_snapshot['nip'] ?? null, 'gross' => $i->gross_earnings, 'net' => $i->net_pay, 'item_id' => $i->id])->values(),
            ]);
        });
        AuditLedger::record('payslips.published', $run, ['count' => $run->items()->whereNotNull('payslip_published_at')->count()], $run->run_number);
    }

    public function company(): array
    {
        return ['name' => Setting::get('company.name', config('app.name')), 'address' => Setting::get('company.address', ''), 'npwp' => Setting::get('company.npwp', '')];
    }

    public function pdfPassword(Employee $e): string
    {
        try {
            if ($e->birth_date) {
                return \Carbon\Carbon::parse($e->birth_date)->format('dmY');
            }
        } catch (Throwable) {
        }

        return preg_replace('/\s+/', '', $e->nip);
    }

    /** @return string|null biner PDF; null bila gagal dirender/dienkripsi saat enkripsi diwajibkan */
    public function pdf(PayrollItem $item, bool $encrypt = false): ?string
    {
        $item->loadMissing('run', 'employee');
        try {
            $dom = Pdf::loadView('pdf.payslip', ['item' => $item, 'run' => $item->run, 'company' => $this->company()])->setPaper('a4')->getDomPDF();
            $dom->render();
            if ($encrypt) {
                $pw = $this->pdfPassword($item->employee);
                $dom->getCanvas()->get_cpdf()->setEncryption($pw, bin2hex(random_bytes(8)), ['print']);
            }

            return $dom->output();
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    /** Kirim email slip (PDF terenkripsi) untuk item yang belum terkirim. @return array{sent:int,failed:int,remaining:int} */
    public function emailPending(PayrollRun $run, int $limit = 30): array
    {
        $sent = 0;
        $failed = 0;
        $encrypt = (bool) config('pay.payslip.encrypt_pdf', true);
        $items = $run->items()->with('employee')->where('is_held', false)->whereNotNull('payslip_published_at')->whereNull('payslip_emailed_at')
            ->where(fn ($q) => $q->whereNull('payslip_email_status')->orWhere('payslip_email_status', 'like', 'gagal%'))->limit($limit)->get();

        foreach ($items as $i) {
            $email = $i->employee->email ?: $i->employee_snapshot['email'] ?? null;
            if (! $email) {
                $i->update(['payslip_email_status' => 'gagal: email karyawan kosong']);
                $failed++;
                continue;
            }
            $pdf = $this->pdf($i, $encrypt);
            $period = $run->periodLabel();
            $paragraphs = ['Slip gaji periode '.$period.' sudah terbit.'];
            $attachments = [];
            if ($pdf) {
                $attachments[] = ['Slip-Gaji-'.$i->employee->nip.'-'.$run->period_year.sprintf('%02d', $run->period_month).'.pdf', $pdf, 'application/pdf'];
                if ($encrypt) {
                    $paragraphs[] = 'Lampiran PDF dilindungi kata sandi: tanggal lahirmu (DDMMYYYY). Jika tanggal lahir belum tercatat, gunakan NIP-mu.';
                }
            } else {
                $paragraphs[] = 'Lampiran tidak dapat dibuat otomatis. Unduh slip dari portal PAYBRIDGE.';
            }
            [$ok, $err] = $this->mailer->deliver($email, $i->employee->full_name, "Slip Gaji {$period}", 'Slip gaji terbit', '#17806a', $paragraphs,
                ['Periode' => $period, 'Gaji bersih' => 'Rp '.number_format($i->net_pay, 0, ',', '.')], ['text' => 'Buka portal', 'url' => route('login')], $attachments);
            $i->update($ok ? ['payslip_emailed_at' => now(), 'payslip_email_status' => $pdf ? 'terkirim' : 'terkirim tanpa lampiran'] : ['payslip_email_status' => 'gagal: '.mb_substr((string) $err, 0, 200)]);
            $ok ? $sent++ : $failed++;
        }

        $remaining = $run->items()->where('is_held', false)->whereNotNull('payslip_published_at')->whereNull('payslip_emailed_at')->count();

        return ['sent' => $sent, 'failed' => $failed, 'remaining' => $remaining];
    }
}
