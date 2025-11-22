<?php

namespace Amrshah\Arbac\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Amrshah\Arbac\Facades\Arbac;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $permission, string $guard = null): Response
    {
        $guard = $guard ?: config('arbac.guard', 'web');

        if (!auth($guard)->check()) {
            abort(403, 'Unauthenticated.');
        }

        $user = auth($guard)->user();
        $context = $request->all();

        if (!Arbac::check($user, $permission, $context)) {
            abort(403, 'Unauthorized action.');
        }

        return $next($request);
    }
}
