<?php

namespace App\Services;

use App\Exceptions\GmailSendException;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Email notifikasi non-OTP: akun dibuat, cuti disetujui/ditolak, payroll diproses, dsb.
 * Semua method mengembalikan [berhasil, pesan_error]; kegagalan email TIDAK menggagalkan aksi utamanya
 * (mis. cuti tetap disetujui di database walau emailnya gagal terkirim).
 */
class NotificationMailer
{
    public function __construct(private GmailApiService $gmail)
    {
    }

    /** @return array{0: bool, 1: ?string} */
    public function accountCreated(string $email, string $name, string $tempPassword): array
    {
        return $this->deliver($email, $name, 'Akun PROFINA Kamu Sudah Dibuat', 'Akun kamu sudah siap', '#059669', [
            'Akun PROFINA kamu sudah dibuat oleh Admin PROFINA.',
            'Gunakan password sementara di bawah untuk login pertama kali, lalu segera ganti di menu Profil.',
        ], ['Email' => $email, 'Password Sementara' => $tempPassword], ['text' => 'Login Sekarang', 'url' => route('login')]);
    }

    /** Kabari pemegang izin users.manage bahwa ada pendaftar baru menunggu persetujuan. */
    public function notifyAdminsOfRegistration(User $new): void
    {
        try {
            $admins = User::permission('users.manage')->where('status', 'active')->get();
        } catch (Throwable $e) {
            Log::warning('[NotificationMailer] Gagal mengambil daftar admin: '.$e->getMessage());

            return;
        }

        foreach ($admins as $admin) {
            $this->deliver($admin->email, $admin->name, 'Pendaftar baru menunggu persetujuan', 'Pendaftar baru', '#d97706', [
                'Ada pengguna baru yang mendaftar. Setujui dan tentukan modul/role yang boleh dimasukinya.',
            ], ['Nama' => $new->name, 'Email' => $new->email], ['text' => 'Buka Kelola Pengguna', 'url' => route('admin.users.index')]);
        }
    }

    /** @return array{0: bool, 1: ?string} */
    public function accountApproved(User $user): array
    {
        return $this->deliver($user->email, $user->name, 'Akun PROFINA Kamu Disetujui', 'Akun disetujui', '#059669', [
            'Akun kamu sudah disetujui. Setelah login, kamu akan diarahkan ke modul sesuai penugasan yang diberikan Admin PROFINA.',
        ], [], ['text' => 'Login Sekarang', 'url' => route('login')]);
    }

    /** @return array{0: bool, 1: ?string} */
    public function accountRejected(User $user, ?string $reason): array
    {
        $paragraphs = ['Mohon maaf, pendaftaran akunmu di PROFINA belum bisa disetujui.'];
        if ($reason) {
            $paragraphs[] = "Alasan:\n{$reason}";
        }

        return $this->deliver($user->email, $user->name, 'Akun PROFINA Belum Disetujui', 'Belum disetujui', '#dc2626', $paragraphs);
    }

    /** @return array{0: bool, 1: ?string} */
    public function custom(string $email, string $name, string $subject, string $message): array
    {
        return $this->deliver($email, $name, $subject, $subject, '#0f172a', [$message]);
    }

    /** @return array{0: bool, 1: ?string} */
    private function deliver(string $email, string $name, string $subject, string $heading, string $color, array $paragraphs, array $details = [], ?array $button = null): array
    {
        try {
            $html = view('emails.notice', [
                'name' => $name,
                'heading' => $heading,
                'color' => $color,
                'paragraphs' => $paragraphs,
                'details' => $details,
                'button' => $button,
            ])->render();

            $text = "Halo {$name},\n\n".implode("\n\n", $paragraphs);
            foreach ($details as $label => $value) {
                $text .= "\n{$label}: {$value}";
            }
            if ($button) {
                $text .= "\n\n{$button['text']}: {$button['url']}";
            }

            $this->gmail->send($email, $name, $subject, $html, $text);

            return [true, null];
        } catch (GmailSendException $e) {
            Log::warning("[NotificationMailer] Email '{$subject}' ke {$email} gagal: ".$e->getMessage());

            return [false, $e->userMessage()];
        } catch (Throwable $e) {
            Log::error("[NotificationMailer] Email '{$subject}' gagal: ".$e->getMessage());

            return [false, 'Terjadi kesalahan saat menyusun email.'];
        }
    }
}
