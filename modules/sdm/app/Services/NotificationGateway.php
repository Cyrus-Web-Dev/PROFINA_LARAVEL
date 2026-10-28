<?php

namespace App\Services;

use App\Models\Employee;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Notifikasi tambahan di luar email: WhatsApp (gateway Fonnte) dan Telegram Bot. Keduanya OPSIONAL - bila
 * token tidak diisi di .env, semua method diam-diam mengembalikan false. Kegagalan pengiriman TIDAK PERNAH
 * melempar exception (notifikasi tidak boleh menggagalkan aksi utama seperti persetujuan cuti).
 */
class NotificationGateway
{
    public function whatsappEnabled(): bool
    {
        return (bool) config('hr.notifications.whatsapp.token');
    }

    public function telegramEnabled(): bool
    {
        return (bool) config('hr.notifications.telegram.bot_token');
    }

    /** Kirim WhatsApp lewat Fonnte. $number boleh format 08xxx atau 628xxx. */
    public function whatsapp(?string $number, string $message): bool
    {
        $token = config('hr.notifications.whatsapp.token');
        $number = preg_replace('/[^0-9]/', '', (string) $number);
        if (! $token || ! $number) {
            return false;
        }

        try {
            // Fonnte: header "Authorization: TOKEN" (tanpa "Bearer"), field target + message, countryCode mengganti 0 di depan.
            $response = Http::withHeaders(['Authorization' => $token])
                ->asForm()->timeout(8)->connectTimeout(4)
                ->post('https://api.fonnte.com/send', ['target' => $number, 'message' => $message, 'countryCode' => '62']);

            $ok = $response->successful() && ($response->json('status') === true);
            if (! $ok) {
                Log::warning('[WhatsApp] Fonnte menolak pengiriman: '.substr($response->body(), 0, 200));
            }

            return $ok;
        } catch (Throwable $e) {
            Log::warning('[WhatsApp] Gagal kirim: '.str_replace((string) $token, '***', $e->getMessage()));

            return false;
        }
    }

    public function telegram(?string $chatId, string $message): bool
    {
        $token = config('hr.notifications.telegram.bot_token');
        if (! $token || ! $chatId) {
            return false;
        }

        try {
            $response = Http::timeout(8)->connectTimeout(4)
                ->post("https://api.telegram.org/bot{$token}/sendMessage", ['chat_id' => $chatId, 'text' => $message]);

            if (! $response->successful()) {
                Log::warning('[Telegram] Gagal kirim: HTTP '.$response->status());
            }

            return $response->successful();
        } catch (Throwable $e) {
            // Pesan exception Guzzle memuat URL (yang berisi token bot) -> wajib disamarkan sebelum masuk log.
            Log::warning('[Telegram] Gagal kirim: '.str_replace((string) $token, '***', $e->getMessage()));

            return false;
        }
    }

    /** Peringatan ke tim HR lewat semua kanal yang aktif. */
    public function alertAdmins(string $message): void
    {
        $this->telegram(config('hr.notifications.telegram.admin_chat_id'), $message);
        $this->whatsapp(config('hr.notifications.whatsapp.admin_number'), $message);
    }

    /** Kabari satu karyawan lewat WhatsApp (bila nomornya ada). */
    public function toEmployee(Employee $employee, string $message): bool
    {
        return $this->whatsapp($employee->phone, $message);
    }
}
