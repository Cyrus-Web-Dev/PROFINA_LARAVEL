<?php

namespace App\Services;

use App\Exceptions\GmailSendException;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Email terkait akun pengguna (persetujuan, penolakan, pesan dari Manager, pemberitahuan pendaftar baru).
 * Semua method mengembalikan [berhasil, pesan_error]; kegagalan email tidak menggagalkan aksi utamanya.
 */
class AccountMailer
{
    public function __construct(private GmailApiService $gmail)
    {
    }

    /** @return array{0: bool, 1: ?string} */
    public function approved(User $user, ?string $note): array
    {
        $role = $user->roleName();
        $access = match ($role) {
            'admin' => 'Semua fitur (Manager)',
            'viewer' => 'Hanya melihat laporan',
            default => $user->getAllPermissions()->pluck('name')
                ->map(fn ($n) => config("mkp.permission_labels.{$n}"))->filter()->implode(', ') ?: 'Hanya melihat laporan',
        };

        $paragraphs = ['Pendaftaran akunmu di MKP sudah disetujui. Kamu sekarang bisa login.'];
        if ($note) {
            $paragraphs[] = "Pesan dari Manager:\n{$note}";
        }

        return $this->deliver($user, 'Pendaftaran kamu disetujui - MKP', 'Pendaftaran disetujui', '#059669', $paragraphs, [
            'Role' => $user->roleLabel(),
            'Posisi' => $user->positionLabel() ?? '-',
            'Hak akses' => $access,
        ], ['text' => 'Login ke MKP', 'url' => route('login')]);
    }

    /** @return array{0: bool, 1: ?string} */
    public function rejected(User $user, ?string $reason): array
    {
        $paragraphs = ['Mohon maaf, pendaftaran akunmu di MKP belum bisa disetujui.'];
        if ($reason) {
            $paragraphs[] = "Alasan:\n{$reason}";
        }
        $paragraphs[] = 'Jika ini keliru, silakan hubungi Manager perusahaanmu.';

        return $this->deliver($user, 'Pendaftaran kamu belum disetujui - MKP', 'Pendaftaran belum disetujui', '#dc2626', $paragraphs);
    }

    /** @return array{0: bool, 1: ?string} */
    public function custom(User $user, string $subject, string $message, string $fromName): array
    {
        return $this->deliver($user, $subject, $subject, '#0f172a', [$message, "— {$fromName} (Manager)"]);
    }

    /** Beri tahu semua Manager aktif bahwa ada pendaftar baru. Tidak pernah melempar error. */
    public function notifyAdminsOfRegistration(User $applicant): void
    {
        try {
            $admins = User::role('admin')->where('status', 'active')->limit(10)->get();
            foreach ($admins as $admin) {
                $this->deliver($admin, 'Pendaftar baru menunggu persetujuan - MKP', 'Ada pendaftar baru', '#d97706', [
                    "{$applicant->name} ({$applicant->email}) baru mendaftar dan menunggu persetujuanmu.",
                ], [], ['text' => 'Tinjau pendaftar', 'url' => route('admin.users.index', ['status' => 'pending'])]);
            }
        } catch (Throwable $e) {
            Log::warning('[AccountMailer] Gagal memberi tahu Manager: '.$e->getMessage());
        }
    }

    /** @return array{0: bool, 1: ?string} */
    private function deliver(User $to, string $subject, string $heading, string $color, array $paragraphs, array $details = [], ?array $button = null): array
    {
        try {
            $html = view('emails.notice', [
                'name' => $to->name,
                'heading' => $heading,
                'color' => $color,
                'paragraphs' => $paragraphs,
                'details' => $details,
                'button' => $button,
            ])->render();

            $text = "Halo {$to->name},\n\n".implode("\n\n", $paragraphs);
            foreach ($details as $label => $value) {
                $text .= "\n{$label}: {$value}";
            }
            if ($button) {
                $text .= "\n\n{$button['text']}: {$button['url']}";
            }

            $this->gmail->send($to->email, $to->name, $subject, $html, $text);

            return [true, null];
        } catch (GmailSendException $e) {
            Log::warning("[AccountMailer] Email '{$subject}' ke {$to->email} gagal: ".$e->getMessage());

            return [false, $e->userMessage()];
        } catch (Throwable $e) {
            Log::error("[AccountMailer] Email '{$subject}' gagal: ".$e->getMessage());

            return [false, 'Terjadi kesalahan saat menyusun email.'];
        }
    }
}
