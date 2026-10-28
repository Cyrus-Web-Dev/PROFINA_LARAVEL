<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\ActivityLogger;
use App\Support\ImageProcessor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class ProfileController extends Controller
{
    public function edit(Request $request)
    {
        $u = $request->user();

        return Inertia::render('Profile/Edit', [
            'profile' => [
                'name' => $u->name,
                'email' => $u->email,
                'username' => $u->username,
                'phone' => $u->phone,
                'employee_id' => $u->employee_id,
                'job_title' => $u->job_title,
                'department' => $u->department,
                'birth_date' => $u->birth_date?->format('Y-m-d'),
                'address' => $u->address,
                'bio' => $u->bio,
                'education' => $u->education ?? [],
                'avatar_url' => $u->avatar_url,
                'role' => $u->roleLabel(),
                'position' => $u->positionLabel(),
                'member_since' => $u->created_at?->format('d/m/Y'),
            ],
        ]);
    }

    public function update(Request $request)
    {
        $user = $request->user();

        // Email & username disimpan huruf kecil (di PostgreSQL pembandingan email peka huruf besar/kecil).
        $request->merge([
            'email' => Str::lower(trim((string) $request->input('email'))),
            'username' => $request->filled('username') ? Str::lower(trim($request->input('username'))) : null,
        ]);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'username' => ['nullable', 'string', 'min:3', 'max:50', 'regex:/^[a-z0-9._-]+$/', Rule::unique('users', 'username')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-\s()]+$/'],
            'employee_id' => 'nullable|string|max:50',
            'job_title' => 'nullable|string|max:100',
            'department' => 'nullable|string|max:100',
            'birth_date' => 'nullable|date|before:today|after:1900-01-01',
            'address' => 'nullable|string|max:500',
            'bio' => 'nullable|string|max:1000',
            'education' => 'nullable|array|max:10',
            'education.*.level' => 'nullable|string|max:30',
            'education.*.institution' => 'required|string|max:150',
            'education.*.major' => 'nullable|string|max:150',
            'education.*.start_year' => 'nullable|integer|between:1950,2100',
            'education.*.end_year' => 'nullable|integer|between:1950,2100',
        ], [
            'username.regex' => 'Username hanya boleh huruf kecil, angka, titik, garis bawah, dan strip.',
            'phone.regex' => 'Nomor telepon hanya boleh berisi angka, spasi, +, -, dan tanda kurung.',
        ]);

        // Mengganti email = mengganti tujuan kode OTP login, jadi wajib konfirmasi password saat ini.
        if ($data['email'] !== Str::lower($user->email)) {
            $request->validate(['current_password' => ['required', 'current_password']], [
                'current_password.required' => 'Masukkan password saat ini untuk mengganti email.',
                'current_password.current_password' => 'Password saat ini salah.',
            ]);
        }

        $education = collect($data['education'] ?? [])->map(fn ($e) => [
            'level' => $e['level'] ?? null,
            'institution' => trim($e['institution']),
            'major' => $e['major'] ?? null,
            'start_year' => $e['start_year'] ?? null,
            'end_year' => $e['end_year'] ?? null,
        ])->values()->all();

        foreach ($education as $i => $e) {
            if ($e['start_year'] && $e['end_year'] && $e['end_year'] < $e['start_year']) {
                throw ValidationException::withMessages(["education.{$i}.end_year" => 'Tahun selesai tidak boleh sebelum tahun mulai.']);
            }
        }

        $data['education'] = $education;
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

        $request->user()->update(['password' => $data['password']]); // di-hash otomatis oleh cast "hashed"

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

        // Diolah ulang (potong persegi, maks 512px, tanpa metadata). Bila GD tidak ada, simpan apa adanya.
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

    /** Menyajikan foto lewat aplikasi (bukan symlink public/storage yang sering gagal dibuat di Windows). */
    public function photo(User $user)
    {
        abort_unless($user->avatar_path && Storage::disk('local')->exists($user->avatar_path), 404);

        return Storage::disk('local')->response($user->avatar_path, null, ['Cache-Control' => 'private, max-age=86400']);
    }
}
