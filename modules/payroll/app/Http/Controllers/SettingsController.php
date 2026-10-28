<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\AuditLedger;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SettingsController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:settings.manage');
    }

    public function edit()
    {
        return Inertia::render('Settings/Company', ['values' => ['name' => Setting::get('company.name'), 'address' => Setting::get('company.address'), 'npwp' => Setting::get('company.npwp'), 'work_end' => Setting::get('work.end_time', '17:00')]]);
    }

    public function update(Request $request)
    {
        $d = $request->validate(['name' => 'required|string|max:150', 'address' => 'nullable|string|max:300', 'npwp' => 'nullable|string|max:30', 'work_end' => 'required|date_format:H:i']);
        Setting::put('company.name', $d['name'], $request->user()->id);
        Setting::put('company.address', $d['address'] ?? '', $request->user()->id);
        Setting::put('company.npwp', $d['npwp'] ?? '', $request->user()->id);
        Setting::put('work.end_time', $d['work_end'], $request->user()->id);
        AuditLedger::record('settings.updated', null, $d, 'Profil perusahaan');

        return back()->with('success', 'Pengaturan disimpan.');
    }
}
