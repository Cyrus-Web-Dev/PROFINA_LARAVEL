<?php

namespace App\Http\Controllers;

use App\Models\JobVacancy;
use Inertia\Inertia;

class CareersController extends Controller
{
    /** Halaman publik daftar lowongan terbuka - TIDAK butuh login. */
    public function index()
    {
        return Inertia::render('Careers/Index', [
            'vacancies' => JobVacancy::where('status', 'open')
                ->where(function ($q) {
                    $q->whereNull('closes_at')->orWhere('closes_at', '>=', today());
                })
                ->with('department:id,name')
                ->orderByDesc('created_at')
                ->get(['id', 'title', 'slug', 'department_id', 'employment_type', 'created_at']),
        ]);
    }

    /** Halaman publik detail lowongan + form lamar - TIDAK butuh login. */
    public function show(string $slug)
    {
        $vacancy = JobVacancy::where('slug', $slug)->where('status', 'open')->with('department', 'position')->firstOrFail();

        return Inertia::render('Careers/Show', [
            'vacancy' => $vacancy,
        ]);
    }
}
