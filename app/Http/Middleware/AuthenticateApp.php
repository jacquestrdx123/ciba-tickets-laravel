<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApp
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->authenticated($request)) {
            return $next($request);
        }

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        return redirect()->route('login');
    }

    private function authenticated(Request $request): bool
    {
        if (session('authenticated')) {
            return true;
        }

        $configuredToken = config('app.api_token');
        if (! $configuredToken) {
            return false;
        }

        $providedToken = $this->bearerToken($request);

        return $providedToken !== null && hash_equals($configuredToken, $providedToken);
    }

    private function bearerToken(Request $request): ?string
    {
        $header = $request->header('Authorization', '');

        if (! str_starts_with($header, 'Bearer ')) {
            return null;
        }

        $token = trim(substr($header, 7));

        return $token !== '' ? $token : null;
    }
}
