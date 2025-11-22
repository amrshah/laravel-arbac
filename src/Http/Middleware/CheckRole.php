<?php

namespace Amrshah\Arbac\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $role, string $guard = null): Response
    {
        $guard = $guard ?: config('arbac.guard', 'web');

        if (!auth($guard)->check()) {
            abort(403, 'Unauthenticated.');
        }

        $user = auth($guard)->user();

        if (!$user->hasRole($role)) {
            abort(403, 'Unauthorized action.');
        }

        return $next($request);
    }
}
