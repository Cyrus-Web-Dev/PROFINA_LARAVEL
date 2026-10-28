<?php

namespace App\Support;

/**
 * Mengolah ulang gambar unggahan dengan GD: membuang metadata (EXIF/GPS), memastikan isinya
 * benar-benar gambar, dan mengecilkan ukurannya. Mengembalikan null bila GD tidak tersedia
 * atau file tidak bisa dibaca (pemanggil lalu menyimpan file apa adanya).
 */
class ImageProcessor
{
    /** Potong persegi dari tengah, kecilkan maksimal $size px, simpan JPEG. Dipakai untuk foto profil. */
    public static function squareJpeg(string $path, int $size = 512): ?string
    {
        $src = self::load($path);
        if (! $src) {
            return null;
        }

        $w = imagesx($src);
        $h = imagesy($src);
        $side = min($w, $h);
        $out = min($size, $side);

        $dst = imagecreatetruecolor($out, $out);
        imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255)); // PNG transparan -> latar putih
        imagecopyresampled($dst, $src, 0, 0, intdiv($w - $side, 2), intdiv($h - $side, 2), $out, $out, $side, $side);

        ob_start();
        imagejpeg($dst, null, 88);

        return ob_get_clean() ?: null;
    }

    /** Pertahankan rasio & transparansi, kecilkan maksimal $max px pada sisi terpanjang, simpan PNG. Dipakai untuk logo. */
    public static function logoPng(string $path, int $max = 600): ?string
    {
        $src = self::load($path);
        if (! $src) {
            return null;
        }

        $w = imagesx($src);
        $h = imagesy($src);
        $ratio = min(1, $max / max($w, $h));
        $nw = max(1, (int) round($w * $ratio));
        $nh = max(1, (int) round($h * $ratio));

        $dst = imagecreatetruecolor($nw, $nh);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);

        ob_start();
        imagepng($dst, null, 6);

        return ob_get_clean() ?: null;
    }

    private static function load(string $path): \GdImage|false|null
    {
        if (! function_exists('imagecreatefromstring')) {
            return null;
        }

        $data = @file_get_contents($path);

        return $data === false ? false : @imagecreatefromstring($data);
    }
}
