<?php

namespace Database\Seeders;

use App\Models\LeaveType;
use Illuminate\Database\Seeder;

class LeaveTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['name' => 'Cuti Tahunan', 'default_quota_per_year' => 12, 'is_paid' => true, 'requires_attachment' => false],
            ['name' => 'Cuti Sakit', 'default_quota_per_year' => 0, 'is_paid' => true, 'requires_attachment' => true],
            ['name' => 'Cuti Melahirkan', 'default_quota_per_year' => 0, 'is_paid' => true, 'requires_attachment' => true],
            ['name' => 'Cuti Menikah', 'default_quota_per_year' => 3, 'is_paid' => true, 'requires_attachment' => false],
            ['name' => 'Cuti Duka (Keluarga Inti)', 'default_quota_per_year' => 2, 'is_paid' => true, 'requires_attachment' => false],
            ['name' => 'Izin Pribadi', 'default_quota_per_year' => 0, 'is_paid' => false, 'requires_attachment' => false],
            ['name' => 'Cuti Tanpa Gaji', 'default_quota_per_year' => 0, 'is_paid' => false, 'requires_attachment' => false],
        ];

        foreach ($types as $type) {
            LeaveType::firstOrCreate(['name' => $type['name']], $type);
        }
    }
}
