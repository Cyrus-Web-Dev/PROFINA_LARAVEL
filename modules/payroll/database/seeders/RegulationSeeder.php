<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RegulationSeeder extends Seeder
{
    public function run(): void
    {
        foreach (RegulationData::params() as [$code, $value, $from, $source]) {
            DB::table('regulation_parameters')->updateOrInsert(['code' => $code, 'effective_from' => $from], ['value' => json_encode($value), 'source' => $source, 'created_at' => now(), 'updated_at' => now()]);
        }

        if (! DB::table('tax_ter_rates')->where('effective_from', RegulationData::TER_EFFECTIVE)->exists()) {
            $rows = [];
            foreach (RegulationData::terTables() as $cat => $tiers) {
                $lower = 0;
                foreach ($tiers as [$upper, $rate]) {
                    $rows[] = ['category' => $cat, 'lower_bound' => $lower, 'upper_bound' => $upper, 'rate' => $rate, 'effective_from' => RegulationData::TER_EFFECTIVE, 'created_at' => now(), 'updated_at' => now()];
                    $lower = $upper;
                }
            }
            DB::table('tax_ter_rates')->insert($rows);
        }
        app(\App\Services\Regulations::class)->flush();
    }
}
