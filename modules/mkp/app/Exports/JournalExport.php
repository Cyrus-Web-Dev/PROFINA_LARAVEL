<?php

namespace App\Exports;

use App\Models\JournalDetail;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class JournalExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(private string $startDate, private string $endDate)
    {
    }

    public function collection()
    {
        return JournalDetail::join('journals', 'journals.id', '=', 'journal_details.journal_id')
            ->join('chart_of_accounts', 'chart_of_accounts.id', '=', 'journal_details.account_id')
            ->where('journals.status', 'posted')
            ->whereDate('journals.journal_date', '>=', $this->startDate)
            ->whereDate('journals.journal_date', '<=', $this->endDate)
            ->orderBy('journals.journal_date')
            ->select(
                'journals.journal_date', 'journals.journal_number', 'journals.description as journal_description',
                'chart_of_accounts.code as account_code', 'chart_of_accounts.name as account_name',
                'journal_details.debit', 'journal_details.credit', 'journal_details.description as line_description'
            )->get();
    }

    public function headings(): array
    {
        return ['Tanggal', 'No. Jurnal', 'Keterangan Jurnal', 'Kode Akun', 'Nama Akun', 'Debit', 'Kredit', 'Keterangan Baris'];
    }

    public function map($row): array
    {
        return [
            $row->journal_date, $row->journal_number, $row->journal_description,
            $row->account_code, $row->account_name, (float) $row->debit, (float) $row->credit, $row->line_description,
        ];
    }
}
