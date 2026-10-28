<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\PerformanceReview;
use App\Models\PerformanceReviewCycle;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use App\Support\TeamScope;
use Inertia\Inertia;

class PerformanceController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:performance.view')->only(['index']);
        $this->middleware('permission:performance.manage')->only(['storeCycle', 'store', 'update']);
    }

    public function index(Request $request)
    {
        $cycle = $request->input('cycle_id')
            ? PerformanceReviewCycle::find($request->input('cycle_id'))
            : PerformanceReviewCycle::orderByDesc('start_date')->first();

        $user = $request->user();
        $reviews = $cycle
            ? TeamScope::constrain(PerformanceReview::where('cycle_id', $cycle->id)->with('employee:id,full_name,nip,department_id'), $user)->get()
            : collect();

        return Inertia::render('Performance/Index', [
            'cycles' => PerformanceReviewCycle::orderByDesc('start_date')->get(),
            'currentCycle' => $cycle,
            'reviews' => $reviews,
            'employeesWithoutReview' => $cycle
                ? TeamScope::constrainEmployees(Employee::where('employment_status', 'active'), $user)->whereDoesntHave('performanceReviews', fn ($q) => $q->where('cycle_id', $cycle->id))->get(['id', 'full_name', 'nip'])
                : collect(),
        ]);
    }

    public function storeCycle(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
        ]);

        $cycle = PerformanceReviewCycle::create($data + ['created_by' => auth()->id()]);
        ActivityLogger::log('performance.cycle_created', "Membuat siklus penilaian: {$cycle->name}", $cycle, auth()->id());

        return back()->with('success', 'Siklus penilaian dibuat.');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'cycle_id' => 'required|exists:performance_review_cycles,id',
            'employee_id' => 'required|exists:employees,id',
            'kpi_items' => 'nullable|array',
            'kpi_items.*.indicator' => 'required_with:kpi_items|string|max:150',
            'kpi_items.*.target' => 'required_with:kpi_items|numeric',
            'kpi_items.*.achieved' => 'nullable|numeric',
            'kpi_items.*.weight' => 'required_with:kpi_items|numeric|min:0|max:100',
            'final_score' => 'nullable|numeric|min:0|max:100',
            'rating' => 'nullable|in:A,B,C,D,E',
            'strengths' => 'nullable|string|max:2000',
            'improvements' => 'nullable|string|max:2000',
        ]);

        TeamScope::assertCanAct($request->user(), Employee::findOrFail($data['employee_id']));

        $review = PerformanceReview::updateOrCreate(
            ['cycle_id' => $data['cycle_id'], 'employee_id' => $data['employee_id']],
            $data + ['reviewer_id' => auth()->id(), 'status' => 'submitted', 'submitted_at' => now()]
        );

        ActivityLogger::log('performance.reviewed', "Menilai kinerja karyawan (siklus #{$data['cycle_id']})", $review, auth()->id());

        return back()->with('success', 'Penilaian kinerja berhasil disimpan.');
    }

    /** Karyawan memberikan tanggapan atas penilaiannya sendiri. */
    public function acknowledge(Request $request, PerformanceReview $performanceReview)
    {
        $employee = $request->user()->employee;
        abort_unless($employee && $performanceReview->employee_id === $employee->id, 403);

        $data = $request->validate(['employee_comment' => 'nullable|string|max:2000']);

        $performanceReview->update([
            'employee_comment' => $data['employee_comment'] ?? null,
            'status' => 'acknowledged',
            'acknowledged_at' => now(),
        ]);

        return back()->with('success', 'Tanggapan berhasil dikirim.');
    }

    public function myReviews(Request $request)
    {
        $employee = $request->user()->employee;
        abort_unless($employee, 403);

        return Inertia::render('Performance/MyReviews', [
            'reviews' => PerformanceReview::where('employee_id', $employee->id)->with('cycle', 'reviewer:id,name')->orderByDesc('created_at')->get(),
        ]);
    }
}
