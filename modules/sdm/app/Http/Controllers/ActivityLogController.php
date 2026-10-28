<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Inertia\Inertia;

/** Penampil audit trail. Hanya untuk pemegang izin settings.manage (super-admin/hr-admin). */
class ActivityLogController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:settings.manage');
    }

    public function index(Request $request)
    {
        $query = ActivityLog::query()->with('user:id,name,email');

        if ($action = $request->input('action')) {
            $query->where('action', 'ilike', "%{$action}%");
        }
        if ($userId = $request->input('user_id')) {
            $query->where('user_id', $userId);
        }
        if ($date = $request->input('date')) {
            $query->whereDate('created_at', $date);
        }

        return Inertia::render('Settings/ActivityLog', [
            'logs' => $query->orderByDesc('created_at')->paginate(30)->withQueryString(),
            'filters' => $request->only(['action', 'user_id', 'date']),
            'users' => \App\Models\User::orderBy('name')->get(['id', 'name']),
        ]);
    }
}
