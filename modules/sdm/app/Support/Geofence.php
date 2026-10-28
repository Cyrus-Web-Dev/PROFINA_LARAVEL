<?php

namespace App\Support;

/**
 * Geofencing absensi: memeriksa apakah koordinat check-in berada dalam radius kantor.
 * Dihitung sendiri (rumus Haversine) - tidak memakai API eksternal. Catatan: koordinat berasal dari browser/HP
 * pengguna sehingga bisa dipalsukan (GPS spoofing); fitur ini berfungsi sebagai pencegah & bukti pendukung,
 * bukan pengaman mutlak.
 */
class Geofence
{
    public static function enabled(): bool
    {
        return config('hr.office.lat') !== null && config('hr.office.lng') !== null && (int) config('hr.office.radius_m') > 0;
    }

    public static function distanceMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earth = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $earth * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /**
     * @return array{ok: bool, message: ?string, note: ?string}
     *   ok=false hanya bila mode enforce aktif dan check-in ditolak; note = catatan untuk disimpan di absensi.
     */
    public static function evaluate(?float $lat, ?float $lng): array
    {
        if (! self::enabled()) {
            return ['ok' => true, 'message' => null, 'note' => null];
        }

        $enforce = (bool) config('hr.office.enforce');

        if ($lat === null || $lng === null) {
            return $enforce
                ? ['ok' => false, 'message' => 'Izinkan akses lokasi di browser/HP untuk check-in. Lokasi wajib diaktifkan.', 'note' => null]
                : ['ok' => true, 'message' => null, 'note' => 'Lokasi tidak tersedia saat check-in.'];
        }

        $distance = self::distanceMeters($lat, $lng, (float) config('hr.office.lat'), (float) config('hr.office.lng'));
        $radius = (int) config('hr.office.radius_m');

        if ($distance <= $radius) {
            return ['ok' => true, 'message' => null, 'note' => null];
        }

        $rounded = number_format($distance, 0, ',', '.');

        return $enforce
            ? ['ok' => false, 'message' => "Kamu berada {$rounded} m dari kantor (batas {$radius} m). Check-in hanya bisa dilakukan di area kantor.", 'note' => null]
            : ['ok' => true, 'message' => null, 'note' => "Check-in di luar radius kantor ({$rounded} m)."];
    }
}
