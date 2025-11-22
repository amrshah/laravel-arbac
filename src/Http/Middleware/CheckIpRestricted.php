<?php

namespace Amrshah\Arbac\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Amrshah\Arbac\Facades\Arbac;
use Symfony\Component\HttpFoundation\Response;

class CheckIpRestricted
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  $permission  Permission to check (should start with 'ip-restricted.')
     * @param  string|null  $configKey  Config key for allowed IPs (default: 'arbac.ip_whitelist')
     */
    public function handle(Request $request, Closure $next, string $permission, ?string $configKey = null): Response
    {
        $guard = config('arbac.guard', 'web');

        if (!auth($guard)->check()) {
            abort(403, 'Unauthenticated.');
        }

        $user = auth($guard)->user();
        $configKey = $configKey ?? 'arbac.ip_whitelist';
        $allowedIps = config($configKey, []);

        if (!Arbac::check($user, $permission, ['allowed_ips' => $allowedIps])) {
            abort(403, 'Access denied from this IP address.');
        }

        return $next($request);
    }
}
