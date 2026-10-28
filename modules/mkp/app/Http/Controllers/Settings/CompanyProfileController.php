<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\CompanyProfile;
use App\Support\ImageProcessor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class CompanyProfileController extends Controller
{
    public function edit()
    {
        return Inertia::render('Settings/CompanyProfile', [
            'profile' => CompanyProfile::current(),
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'legal_name' => 'nullable|string|max:255',
            'npwp' => 'nullable|string|max:30',
            'address' => 'nullable|string',
            'phone' => 'nullable|string|max:30',
            'email' => 'nullable|email',
        ]);

        $profile = CompanyProfile::first();
        if ($profile) {
            $profile->update($validated);
        } else {
            CompanyProfile::create($validated);
        }

        return back()->with('success', 'Profil perusahaan berhasil diperbarui.');
    }

    public function uploadLogo(Request $request)
    {
        $request->validate([
            'logo' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:max_width=6000,max_height=6000'],
        ]);

        $file = $request->file('logo');
        $binary = ImageProcessor::logoPng($file->getRealPath()) ?? file_get_contents($file->getRealPath());
        $path = 'company/logo-'.Str::random(12).'.png';
        Storage::disk('local')->put($path, $binary);

        $profile = CompanyProfile::first() ?? CompanyProfile::create(['name' => 'Nama Perusahaan Anda', 'currency' => 'IDR']);
        $old = $profile->logo_path;
        $profile->update(['logo_path' => $path]);
        if ($old && $old !== $path) {
            Storage::disk('local')->delete($old);
        }

        return back()->with('success', 'Logo perusahaan diperbarui.');
    }

    public function deleteLogo()
    {
        $profile = CompanyProfile::first();
        if ($profile && $profile->logo_path) {
            Storage::disk('local')->delete($profile->logo_path);
            $profile->update(['logo_path' => null]);
        }

        return back()->with('success', 'Logo perusahaan dihapus.');
    }

    public function logo()
    {
        $path = CompanyProfile::first()?->logo_path;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, ['Cache-Control' => 'private, max-age=86400']);
    }
}
