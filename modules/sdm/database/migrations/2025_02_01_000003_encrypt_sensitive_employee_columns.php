<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Enkripsi kolom identitas sensitif karyawan di level aplikasi (cast "encrypted" di model Employee,
 * AES-256 dengan APP_KEY). Jadi kalau file backup / dump database bocor, NPWP, nomor BPJS, dan nomor
 * rekening TIDAK terbaca tanpa APP_KEY. Kolom diubah ke TEXT lewat SQL native (BUKAN Schema::change(),
 * supaya TIDAK butuh dependency tambahan doctrine/dbal) karena hasil enkripsi jauh lebih panjang dari
 * varchar(50) semula.
 *
 * PENTING: simpan APP_KEY di tempat aman & backup terpisah dari database. Kalau APP_KEY hilang/berubah
 * tanpa migrasi ulang, kolom terenkripsi ini tidak bisa dibaca lagi.
 */
return new class extends Migration
{
    private array $columns = ['npwp', 'bpjs_kesehatan_number', 'bpjs_tk_number', 'bank_account_number'];

    public function up(): void
    {
        foreach ($this->columns as $col) {
            DB::statement("ALTER TABLE employees ALTER COLUMN {$col} TYPE TEXT");
        }

        // Enkripsi data yang SUDAH ada (dilewati bila kosong/null).
        DB::table('employees')->orderBy('id')->each(function ($row) {
            $update = [];
            foreach ($this->columns as $col) {
                if ($row->{$col} !== null && $row->{$col} !== '') {
                    $update[$col] = Crypt::encryptString($row->{$col});
                }
            }
            if ($update) {
                DB::table('employees')->where('id', $row->id)->update($update);
            }
        });
    }

    public function down(): void
    {
        DB::table('employees')->orderBy('id')->each(function ($row) {
            $update = [];
            foreach ($this->columns as $col) {
                if ($row->{$col} !== null && $row->{$col} !== '') {
                    try {
                        $update[$col] = mb_substr(Crypt::decryptString($row->{$col}), 0, 50);
                    } catch (\Throwable) {
                        // Sudah plain / rusak: biarkan apa adanya.
                    }
                }
            }
            if ($update) {
                DB::table('employees')->where('id', $row->id)->update($update);
            }
        });

        foreach ($this->columns as $col) {
            DB::statement("ALTER TABLE employees ALTER COLUMN {$col} TYPE VARCHAR(50)");
        }
    }
};
