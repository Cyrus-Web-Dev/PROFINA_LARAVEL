<?php

namespace App\Services\Calc;

use App\Support\RegulationSnapshot;

/** Upah lembur: DIHITUNG SISTEM dari jam & upah kontrak (bukan nominal ketikan pengaju) - mencegah penggelembungan. */
class OvertimeCalculator
{
    /** @param string $dayType weekday|restday|holiday */
    public function amount(RegulationSnapshot $reg, float $monthlyWage, string $dayType, float $hours): float
    {
        $c = $reg->get('labor.overtime');
        $hourly = $monthlyWage / max(1, (int) $c['divisor']);
        $mult = 0.0;

        if ($dayType === 'weekday') {
            $mult = min($hours, 1) * $c['weekday_first'] + max(0, $hours - 1) * $c['weekday_next'];
        } else { // hari istirahat/libur (5 hari kerja)
            $first = min($hours, 8);
            $ninth = min(max($hours - 8, 0), 1);
            $rest = max($hours - 9, 0);
            $mult = $first * $c['rest_first8'] + $ninth * $c['rest_9th'] + $rest * $c['rest_10th_plus'];
        }

        return $reg->round($hourly * $mult);
    }
}
