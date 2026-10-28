<?php

namespace App\Http\Controllers;

use App\Models\JobApplicant;
use App\Models\JobVacancy;
use App\Services\ActivityLogger;
use App\Services\NotificationMailer;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class RecruitmentController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:recruitment.view')->only(['index', 'show']);
        $this->middleware('permission:recruitment.manage')->only(['store', 'update', 'destroy', 'updateApplicantStage']);
    }

    public function index()
    {
        return Inertia::render('Recruitment/Index', [
            'vacancies' => JobVacancy::withCount('applicants')->with('department:id,name')->orderByDesc('created_at')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:150',
            'department_id' => 'nullable|exists:departments,id',
            'position_id' => 'nullable|exists:positions,id',
            'employment_type' => 'required|in:tetap,kontrak,magang,harian',
            'description' => 'required|string|max:5000',
            'requirements' => 'required|string|max:5000',
            'slots' => 'required|integer|min:1|max:100',
            'opens_at' => 'nullable|date',
            'closes_at' => 'nullable|date|after_or_equal:opens_at',
        ]);

        $data['slug'] = Str::slug($data['title']).'-'.Str::random(6);
        $data['posted_by'] = auth()->id();
        $data['status'] = 'open';

        $vacancy = JobVacancy::create($data);
        ActivityLogger::log('recruitment.vacancy_created', "Membuka lowongan: {$vacancy->title}", $vacancy, auth()->id());

        return back()->with('success', 'Lowongan berhasil dipublikasikan.');
    }

    public function show(JobVacancy $vacancy)
    {
        return Inertia::render('Recruitment/Show', [
            'vacancy' => $vacancy->load('department', 'position'),
            'applicants' => $vacancy->applicants()->orderByDesc('applied_at')->get(),
        ]);
    }

    public function update(Request $request, JobVacancy $vacancy)
    {
        $data = $request->validate([
            'status' => 'required|in:open,closed',
            'slots' => 'required|integer|min:1|max:100',
        ]);

        $vacancy->update($data);

        return back()->with('success', 'Lowongan berhasil diperbarui.');
    }

    public function destroy(JobVacancy $vacancy)
    {
        $vacancy->delete();

        return back()->with('success', 'Lowongan berhasil dihapus.');
    }

    /** Endpoint publik (tanpa login) - form lamar kerja. Rate-limited via routes/web.php. */
    public function apply(Request $request, JobVacancy $vacancy, \App\Services\Turnstile $turnstile)
    {
        abort_if($vacancy->status !== 'open', 404);
        $turnstile->assertPassed($request, 'email');

        $data = $request->validate([
            'full_name' => 'required|string|max:150',
            'email' => 'required|email|max:150',
            'phone' => 'nullable|string|max:30',
            'cover_letter' => 'nullable|string|max:3000',
            'resume' => 'required|file|mimes:pdf|max:5120',
        ]);

        $resumePath = $request->file('resume')->store('resumes', 'local');

        JobApplicant::create([
            'job_vacancy_id' => $vacancy->id,
            'full_name' => $data['full_name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'cover_letter' => $data['cover_letter'] ?? null,
            'resume_path' => $resumePath,
            'stage' => 'applied',
            'source' => 'website',
            'applied_at' => now(),
        ]);

        return back()->with('success', 'Lamaran berhasil dikirim. Terima kasih!');
    }

    /** HR mengunduh CV pelamar (private disk, hanya untuk pemegang izin recruitment.view). */
    public function downloadResume(Request $request, JobApplicant $applicant)
    {
        abort_unless($request->user()->can('recruitment.view'), 403);
        abort_unless($applicant->resume_path && \Illuminate\Support\Facades\Storage::disk('local')->exists($applicant->resume_path), 404);

        return \Illuminate\Support\Facades\Storage::disk('local')->download($applicant->resume_path, "CV - {$applicant->full_name}.pdf");
    }

    public function updateApplicantStage(Request $request, JobApplicant $applicant)
    {
        $data = $request->validate([
            'stage' => ['required', Rule::in(['applied', 'screening', 'interview', 'offer', 'hired', 'rejected'])],
            'notes' => 'nullable|string|max:1000',
        ]);

        $applicant->update($data);
        ActivityLogger::log('recruitment.applicant_stage_updated', "Mengubah tahap pelamar {$applicant->full_name} menjadi {$data['stage']}", $applicant, auth()->id());

        if (in_array($data['stage'], ['interview', 'offer', 'hired', 'rejected'])) {
            $messages = [
                'interview' => 'Selamat! Kamu diundang untuk wawancara. Tim HR akan menghubungimu untuk jadwal.',
                'offer' => 'Selamat! Kamu akan menerima penawaran kerja. Tim HR akan menghubungimu segera.',
                'hired' => 'Selamat! Kamu diterima bekerja. Tim HR akan menghubungimu untuk proses onboarding.',
                'rejected' => 'Terima kasih atas lamaranmu. Saat ini kami belum bisa melanjutkan proses ke tahap berikutnya.',
            ];
            app(NotificationMailer::class)->custom($applicant->email, $applicant->full_name, 'Update Lamaran Kerja Kamu', $messages[$data['stage']]);
        }

        return back()->with('success', 'Status pelamar diperbarui.');
    }
}
