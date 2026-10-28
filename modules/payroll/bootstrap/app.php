<?php

use App\Http\Middleware\AuthenticateApiClient;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\ForceHttps;
use App\Http\Middleware\ForcePasswordChange;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->append(ForceHttps::class);
        $middleware->append(\Illuminate\Http\Middleware\HandleCors::class);
        $middleware->append(SecurityHeaders::class);

        $middleware->web(append: [
            HandleInertiaRequests::class,
            \Profina\Sso\Http\Middleware\EnsureProfinaSession::class,
            EnsureUserIsActive::class,
            ForcePasswordChange::class,
        ]);

        // Di Laravel 11 alias middleware Spatie HARUS didaftarkan manual.
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            'has.role' => EnsureUserHasRole::class,
            'api.client' => AuthenticateApiClient::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Sesi habis / token CSRF kedaluwarsa (HTTP 419): kembali ke halaman sebelumnya dengan pesan
        // ramah, bukan modal HTML mentah dari Inertia.
        $exceptions->respond(function (Response $response, Throwable $e, Request $request) {
            if ($response->getStatusCode() === 419 && $request->hasSession()) {
                return back()->with('error', 'Sesi kamu sudah habis. Silakan ulangi aksi terakhir.');
            }

            return $response;
        });
    })->create();
