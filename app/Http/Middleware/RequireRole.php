<?php

namespace App\Http\Middleware;

use App\Models\AkunWarga;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireRole
{
    public function handle(
        Request $request,
        Closure $next,
        string ...$roles
    ): Response {
        $user = $request->user();
        // Warga memakai model akun_warga dan tidak memiliki kolom role.
        // Role-nya ditentukan oleh principal JWT/account_type.
        $userRole = $user instanceof AkunWarga
            ? 'warga'
            : ($user?->role ?? null);

        if (!$user || !in_array($userRole, $roles, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden.',
            ], 403);
        }

        return $next($request);
    }
}
