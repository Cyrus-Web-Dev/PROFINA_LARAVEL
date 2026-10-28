<?php

namespace App\Exports;

/** Pembantu kecil: mengumpulkan baris laporan + nomor baris yang dicetak tebal. */
class SheetBuilder
{
    public array $rows = [];

    public array $bold = [];

    public function add(array $row, bool $bold = false): void
    {
        $this->rows[] = $row;
        if ($bold) {
            $this->bold[] = count($this->rows); // nomor baris Excel dimulai dari 1
        }
    }
}
