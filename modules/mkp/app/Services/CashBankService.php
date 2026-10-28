<?php

namespace App\Services;

use App\Models\ChartOfAccount;
use Illuminate\Validation\ValidationException;

class CashBankService
{
    public function __construct(private JournalService $journalService, private FinancialReportService $reportService)
    {
    }

    /**
     * Transfer dana antar akun Kas/Bank (misal: setor tunai dari Kas ke Bank).
     * Jurnal: Debit akun tujuan, Kredit akun asal.
     */
    public function transfer(array $data, int $userId)
    {
        if ($data['from_account_id'] === $data['to_account_id']) {
            throw ValidationException::withMessages(['to_account_id' => 'Akun tujuan harus berbeda dari akun asal.']);
        }

        $lines = [
            ['account_id' => $data['to_account_id'], 'debit' => $data['amount'], 'credit' => 0, 'description' => $data['note'] ?? 'Transfer kas/bank'],
            ['account_id' => $data['from_account_id'], 'debit' => 0, 'credit' => $data['amount'], 'description' => $data['note'] ?? 'Transfer kas/bank'],
        ];

        $journal = $this->journalService->create([
            'journal_date' => $data['transfer_date'],
            'type' => 'umum',
            'reference' => null,
            'description' => 'Transfer Kas/Bank: '.($data['note'] ?? ''),
            'created_by' => $userId,
        ], $lines, true);

        return $this->journalService->post($journal, $userId);
    }

    /**
     * Ringkasan saldo tiap akun Kas/Bank pada tanggal tertentu.
     */
    public function summary(?string $asOfDate = null): array
    {
        return ChartOfAccount::cashBank()->orderBy('code')->get()
            ->map(fn ($a) => ['id' => $a->id, 'code' => $a->code, 'name' => $a->name, 'balance' => $a->getBalance(null, $asOfDate)])
            ->values()->toArray();
    }
}
