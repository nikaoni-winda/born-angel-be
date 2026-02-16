<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        // 1. Check if user is logged in (Sanctum usually handles this first, but good for safety)
        if (!$request->user()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // 2. Check if user's role is in the allowed list
        // Example usage: middleware('role:admin,super_admin') -> $roles = ['admin', 'super_admin']
        if (!in_array($request->user()->role, $roles)) {
            return response()->json(['message' => 'Unauthorized access.'], 403);
        }

        return $next($request);
    }
}
