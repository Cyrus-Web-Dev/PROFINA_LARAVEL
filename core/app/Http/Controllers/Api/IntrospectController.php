<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ModuleAssignment;
use App\Models\SsoSession;
use App\Services\ModuleCatalog;
use Illuminate\Http\Request;
use Profina\Sso\Token;

/**
 * Modul menanyakan: "sesi SSO ini masih berlaku? role-nya apa sekarang?".
 * Ditandatangani dengan kunci per-modul, jadi modul A tidak bisa menanyakan sesi milik modul B.
 */
class IntrospectController extends Controller
{
    public function __invoke(Request $request)
    {
        $module = (string) $request->header('X-Profina-Module');
        $ts = (string) $request->header('X-Profina-Timestamp');
        $sig = (string) $request->header('X-Profina-Signature');
        $sid = (string) $request->input('sid');
        $email = (string) $request->input('email');

        abort_unless(ModuleCatalog::find($module) && (string) config('portal.master_key') !== '', 401);
        abort_unless(
            ctype_digit($ts) && abs(time() - (int) $ts) <= 60
                && hash_equals(Token::requestSignature(ModuleCatalog::secretFor($module), $module, $ts, $sid.'|'.$email), $sig),
            401,
            'Tanda tangan tidak valid.'
        );

        $session = SsoSession::with('user')->where('id', $sid)->where('module', $module)->first();
        $deny = fn (string $reason) => response()->json(['active' => false, 'reason' => $reason]);

        if (! $session || ! $session->user || strcasecmp($session->user->email, $email) !== 0) {
            return $deny('Sesi tidak dikenal. Silakan masuk lewat Portal.');
        }
        if ($session->revoked_at) {
            return $deny('Sesi berakhir ('.($session->revoked_reason ?: 'dicabut').'). Silakan masuk lewat Portal.');
        }
        if ($session->created_at->lt(now()->subHours((int) config('portal.sso_session_hours', 12)))) {
            $session->update(['revoked_at' => now(), 'revoked_reason' => 'kedaluwarsa']);

            return $deny('Sesi sudah terlalu lama. Silakan masuk ulang lewat Portal.');
        }
        if (! $session->user->isActive()) {
            return $deny('Akun kamu tidak aktif.');
        }

        $assignment = ModuleAssignment::where('user_id', $session->user_id)->where('module', $module)->first();
        if (! $assignment) {
            return $deny('Penugasan kamu ke modul ini sudah dicabut.');
        }

        $session->forceFill(['last_seen_at' => now()])->save();

        $out = ['active' => true, 'role' => $assignment->role];
        if (($perms = ModuleCatalog::permissionsClaim($module, $assignment->permissions)) !== null) {
            $out['permissions'] = $perms;
        }

        return response()->json($out);
    }
}
