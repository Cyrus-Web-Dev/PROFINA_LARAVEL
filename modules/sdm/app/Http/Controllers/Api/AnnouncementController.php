<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use Illuminate\Http\Request;

class AnnouncementController extends Controller
{
    public function index(Request $request)
    {
        $announcements = Announcement::with('publisher:id,name')
            ->orderByDesc('is_pinned')->orderByDesc('published_at')
            ->paginate(min($request->integer('per_page', 10), 50));

        return response()->json($announcements);
    }
}
