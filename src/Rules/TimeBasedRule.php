<?php

namespace Amrshah\Arbac\Rules;

use Amrshah\Arbac\Contracts\AttributeRuleInterface;
use Illuminate\Contracts\Auth\Authenticatable;
use Carbon\Carbon;

class TimeBasedRule implements AttributeRuleInterface
{
    /**
     * Check if this rule supports the given permission
     */
    public function supports(string $permission): bool
    {
        return str_starts_with($permission, 'time-restricted.');
    }

    /**
     * Check if the user has permission based on time constraints
     */
    public function check(Authenticatable $user, string $permission, array $context = []): bool
    {
        // Must have RBAC permission first
        if (! $user->can($permission)) {
            return false;
        }

        $startTime = $context['start_time'] ?? '09:00';
        $endTime = $context['end_time'] ?? '17:00';
        $timezone = $context['timezone'] ?? config('app.timezone', 'UTC');

        $now = Carbon::now($timezone);
        $start = Carbon::parse($startTime, $timezone);
        $end = Carbon::parse($endTime, $timezone);

        return $now->between($start, $end);
    }
}
