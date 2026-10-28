<?php

namespace Profina\Sso\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

class LogoutController extends Controller
{
    /** Keluar dari modul sekaligus dari Portal (satu pintu masuk = satu pintu keluar). */
    public function destroy(Request $request)
    {
        Auth::guard(config('profina.guard', 'web'))->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $url = config('profina.core_url').'/sso/logout';

        if ($request->header('X-Inertia')) {
            return response('', 409, ['X-Inertia-Location' => $url]);
        }

        return redirect()->away($url);
    }
}
