<?php

namespace App\Http\Controllers;

use App\Services\ModuleCatalog;
use App\Services\SsoIssuer;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * "1 pintu masuk, 5 pintu tugas": setelah login, user hanya melihat/dimasuki pintu yang ditugaskan.
 * Satu pintu -> langsung diarahkan. Beberapa -> pilih. Nol -> halaman "belum ada penugasan".
 */
class PortalController extends Controller
{
    public function home(Request $request)
    {
        return redirect()->route($request->user() ? 'portal' : 'login');
    }

    public function index(Request $request)
    {
        $doors = ModuleCatalog::doorsFor($request->user());

        if (count($doors) === 1 && ! $request->boolean('stay') && ! $request->query('sso_error')) {
            return $doors[0]['code'] === 'core'
                ? redirect()->route('dashboard')
                : redirect()->route('portal.launch', $doors[0]['code']);
        }

        return Inertia::render('Portal/Index', [
            'doors' => $doors,
            'ssoError' => $request->query('sso_error'),
        ]);
    }

    public function launch(Request $request, SsoIssuer $issuer, string $module)
    {
        if ($module === 'core') {
            abort_unless($request->user()->hasCoreAccess(), 403, 'Kamu tidak memiliki akses ke modul Inti.');

            return redirect()->route('dashboard');
        }

        return redirect()->away($issuer->urlFor($request->user(), $module, $request));
    }

    /** Dipanggil modul saat user keluar dari modul: ikut keluar dari Portal (GET agar bisa dari redirect). */
    public function ssoLogout(Request $request)
    {
        if ($user = $request->user()) {
            SsoIssuer::revokeUser($user, 'logout-modul');
            \App\Services\ActivityLogger::log('logout', "Logout (dari modul): {$user->name}", null, $user->id);
        }
        \Illuminate\Support\Facades\Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'Kamu sudah keluar dari PROFINA.');
    }
}
