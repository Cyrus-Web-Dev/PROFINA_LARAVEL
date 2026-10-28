<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request as RequestFacade;

class ActivityLogger
{
    /**
     * Catat satu entri audit trail. Dipanggil dari Service lain (JournalService,
     * SalesService, dst) setiap kali ada aksi penting yang perlu bisa ditelusuri
     * kembali: siapa melakukan apa, terhadap data apa, kapan.
     */
    public static function log(string $action, string $description, ?Model $subject = null, ?int $userId = null): void
    {
        ActivityLog::create([
            'user_id' => $userId ?? Auth::id(),
            'action' => $action,
            'subject_type' => $subject ? get_class($subject) : null,
            'subject_id' => $subject?->getKey(),
            'description' => $description,
            'ip_address' => RequestFacade::ip(),
            'created_at' => now(),
        ]);
    }
}
