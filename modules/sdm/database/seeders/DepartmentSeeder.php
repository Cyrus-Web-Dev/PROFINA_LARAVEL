<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Position;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        $structure = [
            'DIR' => ['name' => 'Direksi', 'positions' => ['Direktur Utama' => 1, 'Direktur' => 2]],
            'HR' => ['name' => 'Sumber Daya Manusia', 'positions' => ['Manajer HR' => 2, 'Staff HR' => 4, 'Staff Rekrutmen' => 4]],
            'FIN' => ['name' => 'Keuangan', 'positions' => ['Manajer Keuangan' => 2, 'Staff Keuangan' => 4]],
            'OPS' => ['name' => 'Operasional', 'positions' => ['Manajer Operasional' => 2, 'Staff Operasional' => 4]],
            'IT' => ['name' => 'Teknologi Informasi', 'positions' => ['Manajer IT' => 2, 'Staff IT' => 4]],
            'MKT' => ['name' => 'Pemasaran', 'positions' => ['Manajer Pemasaran' => 2, 'Staff Pemasaran' => 4]],
        ];

        foreach ($structure as $code => $data) {
            $department = Department::firstOrCreate(['code' => $code], ['name' => $data['name']]);

            foreach ($data['positions'] as $title => $level) {
                Position::firstOrCreate([
                    'department_id' => $department->id,
                    'title' => $title,
                ], ['level' => $level]);
            }
        }
    }
}
