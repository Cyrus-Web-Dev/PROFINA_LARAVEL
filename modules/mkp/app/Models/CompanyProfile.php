<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class CompanyProfile extends Model
{
    protected $fillable = ['name', 'legal_name', 'npwp', 'address', 'phone', 'email', 'logo_path', 'currency'];

    protected $appends = ['logo_url'];

    /** URL logo untuk tampilan web (path relatif + versi supaya cache browser ikut diperbarui). */
    public function getLogoUrlAttribute(): ?string
    {
        if (! $this->logo_path) {
            return null;
        }

        return route('settings.company.logo', ['v' => $this->updated_at?->timestamp], false);
    }

    /** Logo sebagai data URI, dipakai di PDF (dompdf tidak perlu akses URL). Null bila tidak ada / gagal dibaca. */
    public function getLogoDataUriAttribute(): ?string
    {
        try {
            if (! $this->logo_path || ! Storage::disk('local')->exists($this->logo_path)) {
                return null;
            }

            $mime = Storage::disk('local')->mimeType($this->logo_path) ?: 'image/png';

            return 'data:'.$mime.';base64,'.base64_encode(Storage::disk('local')->get($this->logo_path));
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Selalu ada tepat satu baris profil perusahaan. Kalau belum pernah diisi,
     * kembalikan nilai default supaya halaman/PDF tidak error.
     */
    public static function current(): self
    {
        return self::first() ?? new self([
            'name' => 'Nama Perusahaan Anda',
            'address' => null,
            'phone' => null,
            'email' => null,
            'npwp' => null,
            'currency' => 'IDR',
        ]);
    }
}
