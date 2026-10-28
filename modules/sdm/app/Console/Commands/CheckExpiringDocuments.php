<?php

namespace App\Console\Commands;

use App\Models\EmployeeDocument;
use App\Services\NotificationMailer;
use Illuminate\Console\Command;

class CheckExpiringDocuments extends Command
{
    protected $signature = 'sdm:check-expiring-documents';

    protected $description = 'Kirim email pengingat untuk dokumen karyawan yang akan kedaluwarsa (kontrak, sertifikat, dll)';

    public function handle(NotificationMailer $mailer): int
    {
        $days = (int) config('hr.document_expiry_reminder_days');

        $documents = EmployeeDocument::with('employee.user')
            ->whereNotNull('expires_at')
            ->whereDate('expires_at', now()->addDays($days)->toDateString())
            ->get();

        $sent = 0;
        foreach ($documents as $document) {
            $email = $document->employee->user?->email ?? $document->employee->personal_email;
            if (! $email) {
                continue;
            }

            $mailer->documentExpiring($email, $document->employee->full_name, $document->title, $document->expires_at->format('d/m/Y'));
            $sent++;
        }

        $this->info("Pengingat dokumen terkirim: {$sent} dari {$documents->count()} dokumen yang akan kedaluwarsa dalam {$days} hari.");

        return self::SUCCESS;
    }
}
