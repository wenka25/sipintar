<?php

namespace App\Http\Middleware;

use App\Models\AkunWarga;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use Throwable;
use Tymon\JWTAuth\Facades\JWTAuth;

class UnifiedJwtAuthentication
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $payload = JWTAuth::parseToken()->getPayload();
            $accountType = $payload->get('account_type', 'staff');
            $subject = $payload->get('sub');

            $principal = $accountType === 'warga'
                ? AkunWarga::find($subject)
                : User::find($subject);

            if (!$principal) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated.',
                ], 401);
            }

            Auth::guard('api')->setUser($principal);
            $request->setUserResolver(fn () => $principal);

            return $next($request);
        } catch (Throwable) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }
    }
}
