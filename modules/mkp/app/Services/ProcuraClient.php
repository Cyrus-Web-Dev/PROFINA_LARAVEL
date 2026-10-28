<?php

namespace App\Services;

use App\Exceptions\ProcuraException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/** Klien REST API PROCURA v1 sisi Keuangan (scope funding:read + funding:decide). */
class ProcuraClient
{
    public function configured(): bool
    {
        return filled(config('procura.url')) && filled(config('procura.token'));
    }

    public function ping(): array
    {
        return $this->call('GET', '/ping')['data'] ?? [];
    }

    /**
     * @param  array<int,string>  $statuses
     * @return array{data: array<int,array>, meta: array}
     */
    public function listFunding(array $statuses, int $page = 1, ?string $updatedSince = null): array
    {
        $r = $this->call('GET', '/funding', array_filter([
            'status' => implode(',', $statuses), 'per_page' => 100, 'page' => $page, 'updated_since' => $updatedSince,
        ]));

        return ['data' => $r['data'] ?? [], 'meta' => $r['meta'] ?? []];
    }

    public function find(string $number): array
    {
        return $this->call('GET', '/funding/'.rawurlencode($number))['data'] ?? [];
    }

    /** @param array{decision:string,approved_amount?:float,note?:?string,decided_by:string,finance_ref?:?string} $payload */
    public function decide(string $number, array $payload): array
    {
        return $this->call('POST', '/funding/'.rawurlencode($number).'/decision', $payload)['data'] ?? [];
    }

    /** @param array{amount:float,method:string,reference?:?string,disbursed_by:string} $payload */
    public function disburse(string $number, array $payload): array
    {
        return $this->call('POST', '/funding/'.rawurlencode($number).'/disbursement', $payload)['data'] ?? [];
    }

    public function sendNote(string $number, string $subject, ?string $body, string $author): void
    {
        $this->call('POST', '/funding/'.rawurlencode($number).'/notes', ['subject' => $subject, 'body' => $body, 'author' => $author]);
    }

    /** @return array<string,mixed> seluruh isi JSON ("data" dan "meta") */
    private function call(string $method, string $path, array $data = []): array
    {
        if (! $this->configured()) {
            throw new ProcuraException('Integrasi PROCURA belum diatur. Isi PROCURA_URL dan PROCURA_TOKEN di file .env.');
        }

        try {
            $http = $this->http();
            $response = $method === 'GET' ? $http->get($path, $data) : $http->post($path, $data);
        } catch (ConnectionException $e) {
            Log::warning('[PROCURA] Koneksi gagal: '.$e->getMessage());
            throw new ProcuraException('Tidak bisa menghubungi PROCURA. Pastikan aplikasi Pengadaan sedang berjalan dan PROCURA_URL benar.');
        }

        return $this->handle($response);
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl(config('procura.url'))->withToken(config('procura.token'))->acceptJson()
            ->timeout(config('procura.timeout'))->connectTimeout(5);
    }

    private function handle(Response $r): array
    {
        if ($r->successful()) {
            return $r->json() ?? [];
        }

        $error = $r->json('error') ?? [];
        $message = $error['message'] ?? null;
        $details = is_array($error['details'] ?? null) ? $error['details'] : [];

        $friendly = match (true) {
            $r->status() === 401 => 'PROCURA menolak token API. Periksa PROCURA_TOKEN (mungkin sudah dibuat ulang atau klien dinonaktifkan).',
            $r->status() === 403 => 'Token API ini tidak punya izin untuk aksi tersebut. Minta Manager Pengadaan menambah scope funding:read / funding:decide.',
            $r->status() === 404 => 'Pengajuan tidak ditemukan di PROCURA.',
            $r->status() === 429 => 'Terlalu banyak permintaan ke PROCURA. Coba lagi sebentar lagi.',
            $r->status() === 422 => $message ?: 'PROCURA menolak aksi ini.',
            $r->status() >= 500 => 'PROCURA sedang bermasalah (HTTP '.$r->status().'). Coba lagi nanti.',
            default => $message ?: 'PROCURA membalas dengan HTTP '.$r->status().'.',
        };

        throw new ProcuraException($friendly, $r->status(), $details);
    }
}
