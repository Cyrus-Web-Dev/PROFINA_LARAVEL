<?php

namespace App\Support;

/**
 * Hitung uang dengan bilangan bulat sen (bukan float). Kolom database: numeric(18,2).
 */
class Money
{
    public static function cents(string|int|float|null $v): int
    {
        return (int) round(((float) ($v ?? 0)) * 100);
    }

    public static function fmt(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }

    /** Harga satuan bersih setelah diskon % (dibulatkan ke sen). */
    public static function net(int $priceCents, float $discountPct): int
    {
        $bp = (int) round($discountPct * 100); // basis poin 0..10000

        return intdiv($priceCents * (10000 - $bp) * 2 + 10000, 20000);
    }

    /** qty (3 desimal) x harga bersih (sen) -> sen, dibulatkan ke atas pada setengah. */
    public static function line(float|string $qty, int $netPriceCents): int
    {
        $milli = (int) round(((float) $qty) * 1000);

        return intdiv($milli * $netPriceCents * 2 + 1000, 2000);
    }

    public static function pct(int $cents, float $pct): int
    {
        $bp = (int) round($pct * 100);

        return intdiv($cents * $bp * 2 + 10000, 20000);
    }
}
