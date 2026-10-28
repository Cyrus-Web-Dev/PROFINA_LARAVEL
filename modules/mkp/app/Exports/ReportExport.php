<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Ekspor laporan sederhana ke Excel: baris demi baris, dengan baris tebal dan format angka ribuan.
 */
class ReportExport implements FromArray, ShouldAutoSize, WithColumnFormatting, WithStyles, WithTitle
{
    /**
     * @param  array<int, array<int, mixed>>  $rows
     * @param  array<int>  $boldRows  nomor baris (mulai 1) yang dicetak tebal
     * @param  array<string>  $numberColumns  huruf kolom berformat angka
     */
    public function __construct(
        private array $rows,
        private string $title,
        private array $boldRows = [],
        private array $numberColumns = ['C', 'D'],
    ) {
    }

    public function array(): array
    {
        return $this->rows;
    }

    public function title(): string
    {
        // Nama sheet Excel: maks 31 karakter, tanpa \ / ? * [ ] :
        return mb_substr(preg_replace('/[\\\\\/\?\*\[\]:]/', '-', $this->title), 0, 31);
    }

    public function styles(Worksheet $sheet): array
    {
        $styles = [];
        foreach ($this->boldRows as $row) {
            $styles[$row] = ['font' => ['bold' => true]];
        }

        return $styles;
    }

    public function columnFormats(): array
    {
        return array_fill_keys($this->numberColumns, '#,##0.00;[Red]-#,##0.00');
    }
}
