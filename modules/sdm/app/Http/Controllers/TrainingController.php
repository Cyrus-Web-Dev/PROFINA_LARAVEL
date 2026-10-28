<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Training;
use App\Models\TrainingParticipant;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TrainingController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:trainings.view')->only(['index', 'show']);
        $this->middleware('permission:trainings.manage')->only(['store', 'update', 'destroy', 'addParticipant', 'updateParticipant']);
    }

    public function index()
    {
        return Inertia::render('Trainings/Index', [
            'trainings' => Training::withCount('participants')->orderByDesc('start_date')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:150',
            'description' => 'nullable|string|max:3000',
            'trainer' => 'nullable|string|max:150',
            'provider' => 'nullable|string|max:150',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'location' => 'nullable|string|max:150',
            'quota' => 'nullable|integer|min:1',
        ]);

        $training = Training::create($data + ['created_by' => auth()->id(), 'status' => 'scheduled']);
        ActivityLogger::log('training.created', "Menjadwalkan pelatihan: {$training->title}", $training, auth()->id());

        return back()->with('success', 'Pelatihan berhasil dijadwalkan.');
    }

    public function show(Training $training)
    {
        return Inertia::render('Trainings/Show', [
            'training' => $training,
            'participants' => $training->participants()->with('employee:id,full_name,nip')->get(),
            'availableEmployees' => Employee::where('employment_status', 'active')
                ->whereDoesntHave('trainingParticipations', fn ($q) => $q->where('training_id', $training->id))
                ->get(['id', 'full_name', 'nip']),
        ]);
    }

    public function update(Request $request, Training $training)
    {
        $data = $request->validate([
            'status' => 'required|in:scheduled,ongoing,completed,cancelled',
        ]);

        $training->update($data);

        return back()->with('success', 'Status pelatihan diperbarui.');
    }

    public function destroy(Training $training)
    {
        $title = $training->title;
        $training->delete();
        ActivityLogger::log('training.deleted', "Menghapus pelatihan: {$title}", null, auth()->id());

        return back()->with('success', 'Pelatihan berhasil dihapus.');
    }

    public function addParticipant(Request $request, Training $training)
    {
        $data = $request->validate(['employee_id' => 'required|exists:employees,id']);

        if ($training->quota && $training->participants()->count() >= $training->quota) {
            return back()->with('error', 'Kuota pelatihan sudah penuh.');
        }

        TrainingParticipant::firstOrCreate([
            'training_id' => $training->id,
            'employee_id' => $data['employee_id'],
        ], ['status' => 'registered']);

        return back()->with('success', 'Peserta berhasil ditambahkan.');
    }

    public function updateParticipant(Request $request, TrainingParticipant $participant)
    {
        $data = $request->validate([
            'status' => 'required|in:registered,attended,absent,completed',
            'score' => 'nullable|numeric|min:0|max:100',
        ]);

        $participant->update($data);

        return back()->with('success', 'Data peserta diperbarui.');
    }

    public function myTrainings(Request $request)
    {
        $employee = $request->user()->employee;
        abort_unless($employee, 403);

        return Inertia::render('Trainings/MyTrainings', [
            'participations' => TrainingParticipant::where('employee_id', $employee->id)->with('training')->get(),
        ]);
    }
}
