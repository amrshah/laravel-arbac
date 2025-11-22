<?php

namespace Amrshah\Arbac\Http\Middleware;

use Amrshah\Arbac\Facades\Arbac;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermissionWithContext
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  $permission  Permission to check
     * @param  string|null  $contextConfig  Config key containing context (e.g., 'arbac.admin_context')
     */
    public function handle(Request $request, Closure $next, string $permission, ?string $contextConfig = null): Response
    {
        $guard = config('arbac.guard', 'web');

        if (! auth($guard)->check()) {
            abort(403, 'Unauthenticated.');
        }

        $user = auth($guard)->user();

        // Load context from config if specified, otherwise use request data
        $context = $contextConfig
            ? config($contextConfig, [])
            : $request->all();

        if (! Arbac::check($user, $permission, $context)) {
            abort(403, 'Unauthorized action.');
        }

        return $next($request);
    }
}
