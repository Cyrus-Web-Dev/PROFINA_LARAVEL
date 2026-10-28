<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

/** Ekspor generik: baris array + judul kolom. Dipakai semua laporan. */
class ArrayExport implements FromArray, ShouldAutoSize, WithHeadings, WithTitle
{
    public function __construct(private array $rows, private array $headings, private string $title = 'Laporan')
    {
    }

    public function array(): array
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return $this->headings;
    }

    public function title(): string
    {
        return mb_substr($this->title, 0, 30);
    }
}
