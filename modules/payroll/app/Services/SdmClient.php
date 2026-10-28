<?php

namespace App\Services;

use App\Exceptions\IntegrationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/** Klien REST API SDM (scope employees:read, employees:sensitive, attendance:read, leaves:read). */
class SdmClient
{
    public function configured(): bool
    {
        return filled(config('pay.integrations.sdm.url')) && filled(config('pay.integrations.sdm.token'));
    }

    public function ping(): array
    {
        return $this->call('GET', '/ping')['data'] ?? [];
    }

    /** @return array{data:array<int,array>,meta:array} */
    public function employees(int $page = 1, ?string $updatedSince = null, bool $sensitive = true): array
    {
        $r = $this->call('GET', '/employees', array_filter(['page' => $page, 'per_page' => 100, 'updated_since' => $updatedSince, 'sensitive' => $sensitive ? 1 : 0]));

        return ['data' => $r['data'] ?? [], 'meta' => $r['meta'] ?? []];
    }

    public function employee(string $nip, bool $sensitive = true): array
    {
        return $this->call('GET', '/employees/'.rawurlencode($nip), ['sensitive' => $sensitive ? 1 : 0])['data'] ?? [];
    }

    public function departments(): array
    {
        return $this->call('GET', '/departments')['data'] ?? [];
    }

    /** Ringkasan absensi & cuti sebulan; detail=1 menyertakan baris harian (untuk mencocokkan lembur). */
    public function attendanceSummary(int $year, int $month, bool $detail = false, ?string $nip = null): array
    {
        return $this->call('GET', '/attendance-summary', array_filter(['year' => $year, 'month' => $month, 'detail' => $detail ? 1 : 0, 'nip' => $nip]))['data'] ?? [];
    }

    public function holidays(int $year): array
    {
        return $this->call('GET', '/holidays', ['year' => $year])['data'] ?? [];
    }

    private function call(string $method, string $path, array $query = []): array
    {
        if (! $this->configured()) {
            throw new IntegrationException('Integrasi SDM belum diatur. Isi SDM_URL dan SDM_TOKEN di .env lalu jalankan php artisan config:clear.');
        }
        try {
            $r = $this->http()->{strtolower($method)}($path, $query);
        } catch (ConnectionException $e) {
            Log::warning('[SDM] Koneksi gagal: '.$e->getMessage());
            throw new IntegrationException('Tidak bisa menghubungi SDM. Pastikan aplikasi SDM berjalan dan SDM_URL benar.');
        }

        return $this->handle($r);
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl(rtrim(config('pay.integrations.sdm.url'), '/'))->withToken(config('pay.integrations.sdm.token'))
            ->acceptJson()->timeout((int) config('pay.integrations.sdm.timeout'))->connectTimeout(5);
    }

    private function handle(Response $r): array
    {
        if ($r->successful()) {
            return $r->json() ?? [];
        }
        $msg = $r->json('error.message');
        throw new IntegrationException(match (true) {
            $r->status() === 401 => 'SDM menolak token API. Periksa SDM_TOKEN (mungkin dibuat ulang atau klien dinonaktifkan).',
            $r->status() === 403 => 'Token SDM tidak punya izin (scope) untuk aksi ini. Tambahkan scope di SDM > Integrasi Payroll.',
            $r->status() === 429 => 'Terlalu banyak permintaan ke SDM. Coba lagi sebentar.',
            $r->status() >= 500 => 'SDM sedang bermasalah (HTTP '.$r->status().').',
            default => $msg ?: 'SDM membalas HTTP '.$r->status().'.',
        }, $r->status());
    }
}
