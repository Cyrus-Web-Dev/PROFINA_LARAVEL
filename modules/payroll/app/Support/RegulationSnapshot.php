<?php

namespace App\Support;

use RuntimeException;

/**
 * "Foto" parameter regulasi yang berlaku pada satu tanggal. Murni (tanpa database) sehingga kalkulator bisa diuji
 * unit dan hasil payroll lama bisa dihitung ulang dengan parameter yang berlaku saat itu.
 */
class RegulationSnapshot
{
    /** @var array<string,mixed> */
    private array $values = [];

    /** @var array<string, array<int, array{0:?float,1:float}>> kategori => [[batas_atas|null, tarif]] terurut */
    private array $ter = [];

    public string $asOf;

    public bool $roundToRupiah = true;

    /**
     * @param  iterable<array{0:string,1:mixed,2:string}>  $rows  [code, value, effective_from]
     * @param  array<string, array<int, array{0:?int,1:float}>>  $ter
     */
    public static function build(iterable $rows, array $ter, string $asOf, bool $round = true): self
    {
        $s = new self();
        $s->asOf = $asOf;
        $s->roundToRupiah = $round;
        $best = [];
        foreach ($rows as $r) {
            [$code, $value, $from] = $r;
            if ($from <= $asOf && (! isset($best[$code]) || $from >= $best[$code])) {
                $best[$code] = $from;
                $s->values[$code] = $value;
            }
        }
        $s->ter = $ter;

        return $s;
    }

    public function get(string $code): mixed
    {
        if (! array_key_exists($code, $this->values)) {
            throw new RuntimeException("Parameter regulasi '{$code}' belum ada untuk tanggal {$this->asOf}. Periksa menu Regulasi / jalankan RegulationSeeder.");
        }

        return $this->values[$code];
    }

    public function num(string $code): float
    {
        return (float) $this->get($code);
    }

    /** @return array<int, array{0:?float,1:float}> */
    public function terTable(string $category): array
    {
        if (empty($this->ter[$category])) {
            throw new RuntimeException("Tabel TER kategori {$category} tidak tersedia.");
        }

        return $this->ter[$category];
    }

    public function round(float $v): float
    {
        return $this->roundToRupiah ? (float) round($v, 0) : round($v, 2);
    }

    /** @return array<string,mixed> kode parameter => nilai (untuk dicatat sebagai bukti di calc_meta) */
    public function dump(): array
    {
        return $this->values;
    }
}
