<?php

namespace Amrshah\Arbac\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Amrshah\Arbac\Facades\Arbac;
use Symfony\Component\HttpFoundation\Response;

class CheckTimeRestricted
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  $permission  Permission to check (should start with 'time-restricted.')
     * @param  string|null  $configKey  Config key for time window (default: 'arbac.time_window')
     */
    public function handle(Request $request, Closure $next, string $permission, ?string $configKey = null): Response
    {
        $guard = config('arbac.guard', 'web');

        if (!auth($guard)->check()) {
            abort(403, 'Unauthenticated.');
        }

        $user = auth($guard)->user();
        $configKey = $configKey ?? 'arbac.time_window';
        $timeWindow = config($configKey, [
            'start_time' => '09:00',
            'end_time' => '17:00',
            'timezone' => config('app.timezone', 'UTC'),
        ]);

        if (!Arbac::check($user, $permission, $timeWindow)) {
            abort(403, 'Access denied outside allowed time window.');
        }

        return $next($request);
    }
}
