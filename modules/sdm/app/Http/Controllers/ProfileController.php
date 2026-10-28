<?php

namespace App\Http\Controllers;

use App\Services\ActivityLogger;
use App\Support\ImageProcessor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Inertia\Inertia;

class ProfileController extends Controller
{
    public function edit(Request $request)
    {
        $u = $request->user()->load('employee.department', 'employee.position');

        return Inertia::render('Profile/Edit', [
            'profile' => [
                'name' => $u->name,
                'email' => $u->email,
                'avatar_url' => $u->avatar_url,
                'roles' => $u->getRoleNames(),
                'employee' => $u->employee,
                'member_since' => $u->created_at?->format('d/m/Y'),
                'must_change_password' => (bool) $u->must_change_password,
            ],
        ]);
    }

    public function update(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
        ]);

        // Mengganti email = mengganti tujuan kode OTP login, jadi wajib konfirmasi password saat ini.
        if ($data['email'] !== $user->email) {
            $request->validate(['current_password' => ['required', 'current_password']], [
                'current_password.required' => 'Masukkan password saat ini untuk mengganti email.',
                'current_password.current_password' => 'Password saat ini salah.',
            ]);
        }

        $user->update($data);

        ActivityLogger::log('profile.updated', "Memperbarui profil: {$user->name}", null, $user->id);

        return back()->with('success', 'Profil berhasil diperbarui.');
    }

    public function updatePassword(Request $request)
    {
        $data = $request->validateWithBag('updatePassword', [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ], [
            'current_password.required' => 'Password saat ini wajib diisi.',
            'current_password.current_password' => 'Password saat ini salah.',
        ]);

        $request->user()->update(['password' => $data['password'], 'must_change_password' => false]);
        // Token API lama (mis. di HP yang hilang) tidak boleh tetap berlaku setelah password diganti.
        $request->user()->tokens()->delete();

        ActivityLogger::log('profile.password_changed', 'Mengganti password akun', null, $request->user()->id);

        return back()->with('success', 'Password berhasil diganti.');
    }

    public function uploadPhoto(Request $request)
    {
        $request->validate([
            'photo' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:max_width=6000,max_height=6000'],
        ]);

        $user = $request->user();
        $file = $request->file('photo');

        $binary = ImageProcessor::squareJpeg($file->getRealPath()) ?? file_get_contents($file->getRealPath());
        $path = 'avatars/'.$user->id.'-'.Str::random(12).'.jpg';

        Storage::disk('local')->put($path, $binary);

        $old = $user->avatar_path;
        $user->update(['avatar_path' => $path]);
        if ($old && $old !== $path) {
            Storage::disk('local')->delete($old);
        }

        return back()->with('success', 'Foto profil diperbarui.');
    }

    public function deletePhoto(Request $request)
    {
        $user = $request->user();

        if ($user->avatar_path) {
            Storage::disk('local')->delete($user->avatar_path);
            $user->update(['avatar_path' => null]);
        }

        return back()->with('success', 'Foto profil dihapus.');
    }

    /** Menyajikan foto lewat aplikasi (private disk, bukan symlink publik). */
    public function photo(\App\Models\User $user)
    {
        abort_unless($user->avatar_path && Storage::disk('local')->exists($user->avatar_path), 404);

        return Storage::disk('local')->response($user->avatar_path, null, ['Cache-Control' => 'private, max-age=86400']);
    }
}
