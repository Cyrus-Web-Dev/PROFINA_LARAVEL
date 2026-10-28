<?php

namespace Profina\Sso\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Profina\Sso\Token;
use Throwable;

class HealthController extends Controller
{
    public function __invoke(Request $request)
    {
        $module = (string) config('profina.module');
        $secret = (string) config('profina.secret');
        $ts = (string) $request->header('X-Profina-Timestamp');
        $sig = (string) $request->header('X-Profina-Signature');

        abort_unless(
            $secret !== '' && ctype_digit($ts) && abs(time() - (int) $ts) <= 60
                && hash_equals(Token::requestSignature($secret, $module, $ts), $sig),
            401,
            'Tanda tangan tidak valid.'
        );

        $db = true;
        try {
            DB::select('select 1');
        } catch (Throwable) {
            $db = false;
        }

        $queue = null;
        $failed = null;
        try {
            if (Schema::hasTable('jobs')) {
                $queue = DB::table('jobs')->count();
            }
            if (Schema::hasTable('failed_jobs')) {
                $failed = DB::table('failed_jobs')->count();
            }
        } catch (Throwable) {
        }

        $metrics = [];
        if (($class = config('profina.metrics')) && class_exists($class)) {
            try {
                $metrics = (new $class)();
            } catch (Throwable $e) {
                $metrics = ['error' => $e->getMessage()];
            }
        }

        return response()->json([
            'module' => $module,
            'app' => config('app.name'),
            'laravel' => app()->version(),
            'php' => PHP_VERSION,
            'time' => now()->toIso8601String(),
            'db' => $db,
            'queue_pending' => $queue,
            'queue_failed' => $failed,
            'metrics' => $metrics,
        ]);
    }
}
