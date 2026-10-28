<?php

namespace App\Console\Commands;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Migrasi best-effort dari aplikasi PHP native lama (database MySQL "perusahaan", tabel `karyawan`
 * dan `users`) ke skema SDM HRIS baru. Berdasarkan audit kode lama, skema aslinya:
 *
 *   users    (id, username, password, role, nip, alamat, no_telepon, posisi, gaji, tanggal_masuk)   -- data eksekutif
 *   karyawan (id, username, password, nip, alamat, no_telepon, divisi, posisi, gaji, tanggal_masuk)  -- karyawan biasa
 *
 * KARENA aplikasi lama tidak dibangun dengan migration/skema baku, kemungkinan besar struktur
 * kolommu sedikit berbeda. Perintah ini memakai Schema::hasColumn() secara defensif dan MELEWATI
 * kolom yang tidak ada (bukan error) - sesuaikan daftar $columnMap di bawah bila perlu, lalu jalankan lagi.
 * Aman dijalankan berulang (pakai updateOrCreate berbasis NIP).
 */
class ImportLegacyData extends Command
{
    protected $signature = 'sdm:import-legacy
                            {--dry-run : Tampilkan apa yang AKAN diimpor tanpa menyimpan apa pun}
                            {--connection=legacy_mysql : Nama koneksi database lama (lihat config/database.php)}';

    protected $description = 'Migrasi data karyawan dari database MySQL aplikasi lama ke SDM HRIS (best-effort, sesuaikan bila perlu)';

    private string $conn;

    public function handle(): int
    {
        $this->conn = (string) $this->option('connection');
        $dryRun = (bool) $this->option('dry-run');

        try {
            DB::connection($this->conn)->getPdo();
        } catch (\Throwable $e) {
            $this->error("Tidak bisa konek ke database lama ({$this->conn}). Cek LEGACY_DB_* di .env. Detail: ".$e->getMessage());

            return self::FAILURE;
        }

        $this->info($dryRun ? 'MODE DRY-RUN — tidak ada yang disimpan.' : 'Memulai impor...');

        $importedDept = 0;
        $importedEmp = 0;
        $skipped = 0;

        foreach (['karyawan', 'users'] as $table) {
            if (! Schema::connection($this->conn)->hasTable($table)) {
                $this->warn("Tabel `{$table}` tidak ditemukan di database lama, dilewati.");

                continue;
            }

            $rows = DB::connection($this->conn)->table($table)->get();
            $this->info("Ditemukan {$rows->count()} baris di tabel `{$table}`.");

            foreach ($rows as $row) {
                $row = (array) $row;
                $nip = $row['nip'] ?? null;

                if (! $nip) {
                    $skipped++;

                    continue;
                }

                // Departemen: kolom lama "divisi" (tabel karyawan) atau tidak ada (tabel users/eksekutif -> "Direksi").
                $deptName = trim((string) ($row['divisi'] ?? ($table === 'users' ? 'Direksi' : 'Umum')));
                $department = Department::firstOrCreate(
                    ['code' => Str::upper(Str::slug(Str::limit($deptName, 15, ''), '')) ?: 'UMUM'],
                    ['name' => $deptName ?: 'Umum']
                );
                if ($department->wasRecentlyCreated) {
                    $importedDept++;
                }

                $posTitle = trim((string) ($row['posisi'] ?? 'Staff'));
                $position = Position::firstOrCreate(
                    ['department_id' => $department->id, 'title' => $posTitle ?: 'Staff'],
                    ['level' => $table === 'users' ? 2 : 5]
                );

                $employeeData = [
                    'full_name' => $row['nama'] ?? $row['username'] ?? "Karyawan {$nip}",
                    'gender' => 'L', // Tidak ada kolom gender di skema lama; sesuaikan manual setelah impor.
                    'address' => $row['alamat'] ?? null,
                    'phone' => $row['no_telepon'] ?? $row['telepon'] ?? null,
                    'department_id' => $department->id,
                    'position_id' => $position->id,
                    'employment_type' => $table === 'users' ? 'tetap' : 'kontrak',
                    'join_date' => $this->parseDate($row['tanggal_masuk'] ?? null) ?? now()->toDateString(),
                    'employment_status' => 'active',
                    'base_salary' => (float) ($row['gaji'] ?? 0),
                    'ptkp_status' => 'TK/0',
                    'notes' => 'Diimpor otomatis dari aplikasi lama ('.$table.', id lama #'.($row['id'] ?? '?').'). Mohon lengkapi data yang belum terisi.',
                ];

                if ($dryRun) {
                    $this->line("  [dry-run] {$nip} — {$employeeData['full_name']} ({$deptName} / {$posTitle})");
                    $importedEmp++;

                    continue;
                }

                $employee = Employee::updateOrCreate(['nip' => $nip], $employeeData);

                // Buat akun login HANYA bila kolom username/password lama tersedia dan email valid dibuat dari username.
                if (! empty($row['username']) && ! empty($row['password']) && ! $employee->user_id) {
                    $email = filter_var($row['username'], FILTER_VALIDATE_EMAIL)
                        ? $row['username']
                        : Str::slug($row['username']).'@perusahaan.local';

                    if (! User::where('email', $email)->exists()) {
                        $user = User::create([
                            'name' => $employeeData['full_name'],
                            'email' => $email,
                            'password' => Str::random(32), // placeholder, ditimpa mentah di bawah agar TIDAK di-hash ulang
                            'status' => 'active',
                            'must_change_password' => true, // wajib ganti password lama demi keamanan
                            'email_verified_at' => now(),
                        ]);
                        // Hash bcrypt lama ($2y$...) tetap BISA diverifikasi Laravel walau driver default
                        // sekarang argon2id (Hash::check mendeteksi algoritme dari format hash-nya sendiri).
                        // Ditulis LANGSUNG lewat query builder (bukan Eloquent save()) supaya cast "hashed"
                        // di model User tidak menghash-ulang nilainya. Begitu karyawan ini login pertama kali,
                        // hash lamanya OTOMATIS naik ke argon2id (lihat AuthenticatedSessionController).
                        // lewat query builder (bukan Eloquent save()) supaya cast "hashed" tidak menghash-ulang nilainya.
                        DB::table('users')->where('id', $user->id)->update(['password' => $row['password']]);

                        $user->assignRole($table === 'users' ? 'hr-admin' : 'karyawan');
                        $employee->update(['user_id' => $user->id]);
                    }
                }

                $importedEmp++;
            }
        }

        if ($dryRun) {
            $this->info("Selesai (dry-run). Akan mengimpor {$importedEmp} karyawan, {$skipped} baris dilewati (tanpa NIP). Jalankan tanpa --dry-run untuk benar-benar menyimpan.");
        } else {
            $this->info("Selesai. {$importedEmp} karyawan diimpor/diperbarui, {$importedDept} departemen baru dibuat, {$skipped} baris dilewati (tanpa NIP).");
            $this->warn('PENTING: field "gender" di-set default "L" untuk semua data impor (tidak ada di skema lama) - mohon periksa dan koreksi manual di menu Karyawan. Password akun lama TIDAK diketahui HR - beri tahu karyawan untuk memakai "Lupa Password".');
        }

        return self::SUCCESS;
    }

    private function parseDate(?string $value): ?string
    {
        if (! $value) {
            return null;
        }
        try {
            return \Carbon\Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
