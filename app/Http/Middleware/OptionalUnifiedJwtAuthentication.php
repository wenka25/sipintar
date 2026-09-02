<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class OptionalUnifiedJwtAuthentication
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->bearerToken()) {
            return $next($request);
        }

        return app(UnifiedJwtAuthentication::class)->handle($request, $next);
    }
}
