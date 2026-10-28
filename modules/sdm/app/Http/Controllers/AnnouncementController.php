<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AnnouncementController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:announcements.manage')->except(['index']);
    }

    public function index()
    {
        return Inertia::render('Announcements/Index', [
            'announcements' => Announcement::with('publisher:id,name')
                ->orderByDesc('is_pinned')->orderByDesc('published_at')
                ->paginate(10),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:200',
            'body' => 'required|string|max:5000',
            'is_pinned' => 'nullable|boolean',
        ]);

        $announcement = Announcement::create($data + ['published_by' => auth()->id(), 'published_at' => now()]);
        ActivityLogger::log('announcement.published', "Mempublikasikan pengumuman: {$announcement->title}", $announcement, auth()->id());

        return back()->with('success', 'Pengumuman berhasil dipublikasikan.');
    }

    public function destroy(Announcement $announcement)
    {
        $announcement->delete();

        return back()->with('success', 'Pengumuman berhasil dihapus.');
    }
}
