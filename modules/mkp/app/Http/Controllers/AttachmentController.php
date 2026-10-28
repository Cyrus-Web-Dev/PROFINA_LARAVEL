<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\Bill;
use App\Models\Invoice;
use App\Models\Journal;
use App\Models\ProcurementFunding;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Lampiran bukti transaksi (foto nota, PDF faktur, dll) pada jurnal, invoice, dan bill.
 * Berkas disimpan di storage/app/private dan hanya bisa dibuka lewat aplikasi (harus login).
 */
class AttachmentController extends Controller
{
    private const MAX_PER_RECORD = 10;

    /** jenis => [kelas model, hak akses yang dibutuhkan untuk menambah lampiran] */
    private const TYPES = [
        'journal' => [Journal::class, 'journal.create'],
        'invoice' => [Invoice::class, 'ar.manage'],
        'bill' => [Bill::class, 'ap.manage'],
        'procurement' => [ProcurementFunding::class, 'procurement.decide|procurement.disburse'],
    ];

    public function store(Request $request)
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(self::TYPES))],
            'id' => 'required|integer|min:1',
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ], [
            'file.required' => 'Pilih file terlebih dahulu.',
            'file.mimes' => 'Format file harus JPG, PNG, WebP, atau PDF.',
            'file.max' => 'Ukuran file maksimal 5 MB.',
        ]);

        [$class, $permission] = self::TYPES[$data['type']];
        abort_unless($request->user()->hasAnyPermission(explode('|', $permission)), 403, 'Kamu tidak berhak menambah lampiran di sini.');

        $record = $class::findOrFail($data['id']);

        if ($record->attachments()->count() >= self::MAX_PER_RECORD) {
            throw ValidationException::withMessages(['file' => 'Maksimal '.self::MAX_PER_RECORD.' lampiran per dokumen.']);
        }

        $file = $request->file('file');
        $ext = strtolower($file->extension() ?: 'bin');
        $path = Storage::disk('local')->putFileAs("attachments/{$data['type']}/{$record->getKey()}", $file, Str::random(32).'.'.$ext);

        // Nama asli hanya untuk tampilan: buang karakter aneh, batasi panjang.
        $name = preg_replace('/[^\p{L}\p{N}\s._()\-]/u', '', basename($file->getClientOriginalName())) ?: 'lampiran.'.$ext;
        $name = Str::limit($name, 150, '');

        $record->attachments()->create([
            'path' => $path,
            'original_name' => $name,
            'mime' => $file->getMimeType() ?: 'application/octet-stream',
            'size' => $file->getSize(),
            'uploaded_by' => $request->user()->id,
        ]);

        ActivityLogger::log('attachment.added', "Melampirkan {$name} pada {$data['type']} #{$record->getKey()}");

        return back()->with('success', 'Lampiran ditambahkan.');
    }

    public function show(Attachment $attachment)
    {
        abort_unless(Storage::disk('local')->exists($attachment->path), 404, 'Berkas lampiran tidak ditemukan.');

        return Storage::disk('local')->response($attachment->path, $attachment->original_name, [
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    public function destroy(Request $request, Attachment $attachment)
    {
        $user = $request->user();
        abort_unless($user->hasRole('admin') || $attachment->uploaded_by === $user->id, 403, 'Hanya Manager atau pengunggahnya yang bisa menghapus lampiran.');

        $name = $attachment->original_name;
        $attachment->delete();

        ActivityLogger::log('attachment.deleted', "Menghapus lampiran {$name}");

        return back()->with('success', 'Lampiran dihapus.');
    }
}
