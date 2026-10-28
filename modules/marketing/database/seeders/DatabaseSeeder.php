<?php

namespace Database\Seeders;

use App\Models\PriceLevel;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolePermissionSeeder::class);

        foreach ([['umum', 'Umum / Retail', 0, 1], ['reseller', 'Reseller', 10, 2], ['grosir', 'Grosir', 15, 3]] as [$code, $name, $disc, $sort]) {
            PriceLevel::firstOrCreate(['code' => $code], ['name' => $name, 'discount_pct' => $disc, 'sort' => $sort]);
        }
    }
}
