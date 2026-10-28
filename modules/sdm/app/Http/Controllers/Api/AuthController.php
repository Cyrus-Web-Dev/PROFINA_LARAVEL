<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Auth API berbasis token (Sanctum), TERPISAH dari login web (yang pakai OTP email 2 langkah).
 * Ditujukan untuk aplikasi mobile / integrasi pihak ketiga yang terpercaya. Token dibuat per-perangkat
 * (field "device_name") sehingga bisa dicabut satu per satu tanpa mengganggu perangkat lain.
 */
class AuthController extends Controller
{
    public function login(Request $request)
    {
        // PROFINA: satu-satunya tempat login adalah Portal. Login password langsung untuk aplikasi mobile
        // dimatikan secara default (PROFINA_ALLOW_API_LOGIN=false) supaya tidak menjadi pintu belakang RBAC.
        if (! config('profina.allow_api_login', false)) {
            return response()->json(['message' => 'Login langsung dinonaktifkan. Masuk lewat Portal PROFINA.'], 403);
        }

        $data = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
            'device_name' => 'required|string|max:100',
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => ['Email atau password salah.']]);
        }

        // Sama seperti login web: naikkan otomatis ke algoritme hashing default saat ini (argon2id)
        // kalau password user masih pakai algoritme/parameter lama. Aman - lihat penjelasan di
        // AuthenticatedSessionController.
        if (Hash::needsRehash($user->password)) {
            $user->password = $data['password'];
            $user->save();
        }

        if ($user->status === 'pending') {
            throw ValidationException::withMessages(['email' => ['Akun kamu masih menunggu persetujuan Admin HR.']]);
        }
        if ($user->status !== 'active') {
            throw ValidationException::withMessages(['email' => ['Akun kamu tidak aktif. Hubungi Admin HR.']]);
        }
        if (! $user->roles()->exists()) {
            throw ValidationException::withMessages(['email' => ['Akun kamu belum diberi role. Hubungi Admin HR.']]);
        }

        $token = $user->createToken($data['device_name'])->plainTextToken;

        $user->forceFill(['last_login_at' => now(), 'last_login_ip' => $request->ip()])->save();
        ActivityLogger::log('api.login', "Login API dari perangkat: {$data['device_name']}", null, $user->id);

        return response()->json([
            'token' => $token,
            'user' => $this->userPayload($user),
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Berhasil logout.']);
    }

    /** Cabut SEMUA token (logout dari semua perangkat). */
    public function logoutAll(Request $request)
    {
        $request->user()->tokens()->delete();

        return response()->json(['message' => 'Berhasil logout dari semua perangkat.']);
    }

    public function me(Request $request)
    {
        return response()->json(['user' => $this->userPayload($request->user())]);
    }

    private function userPayload(User $user): array
    {
        $user->loadMissing('employee.department', 'employee.position');

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'roles' => $user->getRoleNames(),
            'permissions' => $user->getAllPermissions()->pluck('name'),
            'employee' => $user->employee ? [
                'id' => $user->employee->id,
                'nip' => $user->employee->nip,
                'full_name' => $user->employee->full_name,
                'department' => $user->employee->department?->name,
                'position' => $user->employee->position?->title,
            ] : null,
        ];
    }
}
