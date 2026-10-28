<?php

namespace App\Http\Middleware;

use App\Models\IntegrationClient;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Bearer token klien integrasi (mis. PAYBRIDGE). Parameter = scope yang dibutuhkan. */
class AuthenticateIntegrationClient
{
    public function handle(Request $request, Closure $next, ?string $scope = null): Response
    {
        $token = $request->bearerToken();
        if (! $token) {
            return $this->fail(401, 'unauthenticated', 'Sertakan header Authorization: Bearer <token>.');
        }
        $client = IntegrationClient::where('token_hash', hash('sha256', $token))->first();
        if (! $client || ! $client->is_active) {
            return $this->fail(401, 'invalid_token', 'Token tidak dikenal atau klien dinonaktifkan.');
        }
        if ($scope && ! $client->can($scope)) {
            return $this->fail(403, 'insufficient_scope', "Token ini tidak punya izin '{$scope}'.");
        }
        if (! $client->last_used_at || $client->last_used_at->lt(now()->subMinute())) {
            $client->forceFill(['last_used_at' => now(), 'last_used_ip' => $request->ip()])->saveQuietly();
        }
        $request->attributes->set('integration_client', $client);

        return $next($request);
    }

    private function fail(int $status, string $code, string $message): Response
    {
        return response()->json(['error' => ['code' => $code, 'message' => $message]], $status);
    }
}
