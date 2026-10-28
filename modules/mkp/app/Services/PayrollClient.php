<?php

namespace App\Services;

use App\Exceptions\PayrollException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/** Klien REST API PAYBRIDGE v1 sisi Keuangan (scope funding:read/decide, remittance:read/update, budget:read). */
class PayrollClient
{
    public function configured(): bool
    {
        return filled(config('payroll.url')) && filled(config('payroll.token'));
    }

    public function ping(): array
    {
        return $this->call('GET', '/ping')['data'] ?? [];
    }

    public function listFunding(array $statuses, int $page = 1, ?string $updatedSince = null): array
    {
        $r = $this->call('GET', '/funding', array_filter(['status' => implode(',', $statuses), 'per_page' => 100, 'page' => $page, 'updated_since' => $updatedSince]));

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

    /** @param array{amount:float,reference?:?string,disbursed_by:string,journal_refs?:array} $payload */
    public function disburse(string $number, array $payload): array
    {
        return $this->call('POST', '/funding/'.rawurlencode($number).'/disbursement', $payload)['data'] ?? [];
    }

    public function sendNote(string $number, string $subject, ?string $body, string $author): void
    {
        $this->call('POST', '/funding/'.rawurlencode($number).'/notes', ['subject' => $subject, 'body' => $body, 'author' => $author]);
    }

    public function remittances(?string $status = null): array
    {
        return $this->call('GET', '/remittances', array_filter(['status' => $status]))['data'] ?? [];
    }

    public function remittancePaid(int $id, array $payload): array
    {
        return $this->call('POST', '/remittances/'.$id.'/paid', $payload)['data'] ?? [];
    }

    public function activeBudget(?int $year = null): array
    {
        return $this->call('GET', '/budgets/active', array_filter(['year' => $year]))['data'] ?? [];
    }

    private function call(string $method, string $path, array $data = []): array
    {
        if (! $this->configured()) {
            throw new PayrollException('Integrasi PAYBRIDGE belum diatur. Isi PAYROLL_URL dan PAYROLL_TOKEN di file .env lalu php artisan config:clear.');
        }
        try {
            $http = $this->http();
            $response = $method === 'GET' ? $http->get($path, $data) : $http->post($path, $data);
        } catch (ConnectionException $e) {
            Log::warning('[PAYBRIDGE] Koneksi gagal: '.$e->getMessage());
            throw new PayrollException('Tidak bisa menghubungi PAYBRIDGE. Pastikan aplikasi Payroll berjalan dan PAYROLL_URL benar.');
        }

        return $this->handle($response);
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl(config('payroll.url'))->withToken(config('payroll.token'))->acceptJson()->timeout(config('payroll.timeout'))->connectTimeout(5);
    }

    private function handle(Response $r): array
    {
        if ($r->successful()) {
            return $r->json() ?? [];
        }
        $error = $r->json('error') ?? [];
        $message = $error['message'] ?? null;
        $friendly = match (true) {
            $r->status() === 401 => 'PAYBRIDGE menolak token API. Periksa PAYROLL_TOKEN (mungkin dibuat ulang atau klien dinonaktifkan).',
            $r->status() === 403 => 'Token ini tidak punya izin (scope) untuk aksi tersebut. Minta Manager Payroll menambahkannya.',
            $r->status() === 404 => 'Data tidak ditemukan di PAYBRIDGE.',
            $r->status() === 429 => 'Terlalu banyak permintaan ke PAYBRIDGE. Coba lagi sebentar.',
            $r->status() === 422 => $message ?: 'PAYBRIDGE menolak aksi ini.',
            $r->status() >= 500 => 'PAYBRIDGE sedang bermasalah (HTTP '.$r->status().').',
            default => $message ?: 'PAYBRIDGE membalas HTTP '.$r->status().'.',
        };

        throw new PayrollException($friendly, $r->status(), is_array($error['details'] ?? null) ? $error['details'] : []);
    }
}
