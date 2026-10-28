<?php

namespace App\Models\Concerns;

use DateTimeInterface;

/**
 * Tanpa trait ini, Laravel men-serialize kolom bertipe cast 'date'/'datetime' ke JSON
 * sebagai ISO 8601 penuh (mis. "2026-09-18T00:00:00.000000Z"), sehingga tampil mentah
 * di halaman Vue yang hanya menampilkan `{{ model.tanggal }}` langsung.
 * Trait ini membuat SEMUA atribut tanggal pada model terkait tampil sebagai "Y-m-d"
 * saat dikirim ke frontend - konsisten dan langsung terbaca tanpa perlu format ulang di Vue.
 */
trait HasCleanDateSerialization
{
    protected function serializeDate(DateTimeInterface $date): string
    {
        return $date->format('Y-m-d');
    }
}
