<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

class Attachment extends Model
{
    protected $fillable = ['attachable_type', 'attachable_id', 'path', 'original_name', 'mime', 'size', 'uploaded_by'];

    protected static function booted(): void
    {
        // Menghapus baris = menghapus berkasnya juga.
        static::deleting(fn (Attachment $a) => Storage::disk('local')->delete($a->path));
    }

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime, 'image/');
    }

    /** Data lampiran untuk panel di halaman detail (Vue). */
    public static function panelFor(Model $record): array
    {
        return $record->attachments()->with('uploader:id,name')->orderByDesc('id')->get()
            ->map(fn (self $a) => [
                'id' => $a->id,
                'name' => $a->original_name,
                'mime' => $a->mime,
                'size' => $a->size,
                'is_image' => $a->isImage(),
                'url' => route('attachments.show', $a->id, false),
                'uploaded_by' => $a->uploaded_by,
                'uploader' => $a->uploader?->name,
                'created_at' => $a->created_at?->format('d/m/Y H:i'),
            ])->all();
    }
}
