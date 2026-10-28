<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class DocumentNumber
{
    /** Nomor berurutan per bulan, aman dari tabrakan (kunci baris). Contoh: QT-202610-0007 */
    public static function next(string $prefix): string
    {
        $key = $prefix.'-'.now()->format('Ym');

        return DB::transaction(function () use ($key) {
            DB::table('number_sequences')->insertOrIgnore(['key' => $key, 'last_number' => 0]);
            $row = DB::table('number_sequences')->where('key', $key)->lockForUpdate()->first();
            $n = $row->last_number + 1;
            DB::table('number_sequences')->where('key', $key)->update(['last_number' => $n]);

            return sprintf('%s-%04d', $key, $n);
        });
    }
}
