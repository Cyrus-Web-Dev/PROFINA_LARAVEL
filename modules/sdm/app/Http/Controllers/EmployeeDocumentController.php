<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class EmployeeDocumentController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:documents.view')->only(['index']);
        $this->middleware('permission:documents.manage')->except(['index', 'download']);
    }

    public function index(Request $request)
    {
        $expiringOnly = $request->boolean('expiring');
        $query = EmployeeDocument::with('employee:id,full_name,nip');

        if ($expiringOnly) {
            $query->whereNotNull('expires_at')
                ->whereBetween('expires_at', [today(), today()->addDays((int) config('hr.document_expiry_reminder_days'))]);
        }

        return Inertia::render('Documents/Index', [
            'documents' => $query->orderByDesc('created_at')->paginate(20)->withQueryString(),
            'expiring' => $expiringOnly,
        ]);
    }

    public function store(Request $request, Employee $employee)
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(EmployeeDocument::TYPE_LABELS))],
            'title' => 'required|string|max:150',
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'issued_at' => 'nullable|date',
            'expires_at' => 'nullable|date|after:issued_at',
        ]);

        $file = $request->file('file');
        $path = $file->store('employee-documents', 'local');

        $document = EmployeeDocument::create([
            'employee_id' => $employee->id,
            'type' => $data['type'],
            'title' => $data['title'],
            'file_path' => $path,
            'file_original_name' => $file->getClientOriginalName(),
            'issued_at' => $data['issued_at'] ?? null,
            'expires_at' => $data['expires_at'] ?? null,
            'uploaded_by' => auth()->id(),
        ]);

        ActivityLogger::log('document.uploaded', "Mengunggah dokumen '{$document->title}' untuk {$employee->full_name}", $document, auth()->id());

        return back()->with('success', 'Dokumen berhasil diunggah.');
    }

    public function destroy(EmployeeDocument $document)
    {
        Storage::disk('local')->delete($document->file_path);
        $title = $document->title;
        $document->delete();

        ActivityLogger::log('document.deleted', "Menghapus dokumen: {$title}", null, auth()->id());

        return back()->with('success', 'Dokumen berhasil dihapus.');
    }

    /** Unduh dokumen lewat aplikasi (private disk). Karyawan hanya bisa unduh dokumennya sendiri. */
    public function download(Request $request, EmployeeDocument $document)
    {
        $employee = $request->user()->employee;
        $isOwner = $employee && $document->employee_id === $employee->id;

        abort_unless($isOwner || $request->user()->can('documents.view'), 403);
        abort_unless(Storage::disk('local')->exists($document->file_path), 404);

        return Storage::disk('local')->download($document->file_path, $document->file_original_name ?? $document->title);
    }
}
