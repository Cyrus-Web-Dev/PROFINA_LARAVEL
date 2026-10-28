<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AuditController extends Controller
{
    public function index(Request $request)
    {
        $logs = ActivityLog::with('user:id,name')
            ->when($request->filled('action'), fn ($q) => $q->where('action', 'like', $request->input('action').'%'))
            ->when($request->filled('q'), fn ($q) => $q->where('description', 'ilike', '%'.$request->input('q').'%'))
            ->latest('created_at')->paginate(25)->withQueryString()
            ->through(fn ($l) => ['id' => $l->id, 'at' => $l->created_at?->format('Y-m-d H:i:s'), 'user' => $l->user?->name, 'action' => $l->action, 'description' => $l->description, 'ip' => $l->ip_address]);

        return Inertia::render('Admin/Audit/Index', ['logs' => $logs, 'filters' => $request->only('action', 'q')]);
    }
}
