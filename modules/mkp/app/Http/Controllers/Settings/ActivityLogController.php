<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $logs = ActivityLog::with('user')
            ->when($request->action, fn ($q, $a) => $q->where('action', $a))
            ->when($request->search, fn ($q, $s) => $q->where('description', 'like', "%{$s}%"))
            ->orderByDesc('created_at')
            ->paginate(30)
            ->withQueryString();

        $actionOptions = ActivityLog::select('action')->distinct()->orderBy('action')->pluck('action');

        return Inertia::render('Settings/ActivityLog', [
            'logs' => $logs,
            'filters' => $request->only(['action', 'search']),
            'actionOptions' => $actionOptions,
        ]);
    }
}
