<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserRole
{
    /**
     * Batasi akses endpoint berdasarkan role user.
     * Pemakaian: ->middleware('role:merchant') atau 'role:customer,merchant'.
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! in_array($user->role, $roles, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Akun Anda tidak memiliki akses ke fitur ini.',
                'errors' => ['code' => 'ROLE_NOT_ALLOWED'],
            ], 403);
        }

        return $next($request);
    }
}
