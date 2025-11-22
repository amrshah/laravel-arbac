<?php

namespace Amrshah\Arbac\Rules;

use Amrshah\Arbac\Contracts\AttributeRuleInterface;
use Illuminate\Contracts\Auth\Authenticatable;

class IpWhitelistRule implements AttributeRuleInterface
{
    /**
     * Check if this rule supports the given permission
     */
    public function supports(string $permission): bool
    {
        return str_starts_with($permission, 'ip-restricted.');
    }

    /**
     * Check if the user's IP is in the allowed list
     */
    public function check(Authenticatable $user, string $permission, array $context = []): bool
    {
        // Must have RBAC permission first
        if (! $user->can($permission)) {
            return false;
        }

        $allowedIps = $context['allowed_ips'] ?? config('arbac.ip_whitelist', []);
        $userIp = request()->ip();

        // Support CIDR notation
        foreach ($allowedIps as $allowedIp) {
            if ($this->ipMatches($userIp, $allowedIp)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if an IP matches the allowed IP (supports CIDR)
     */
    protected function ipMatches(string $ip, string $allowedIp): bool
    {
        // Exact match
        if ($ip === $allowedIp) {
            return true;
        }

        // CIDR notation check
        if (strpos($allowedIp, '/') !== false) {
            return $this->ipInRange($ip, $allowedIp);
        }

        return false;
    }

    /**
     * Check if IP is in CIDR range
     */
    protected function ipInRange(string $ip, string $range): bool
    {
        list($subnet, $mask) = explode('/', $range);
        
        $ip_long = ip2long($ip);
        $subnet_long = ip2long($subnet);
        $mask_long = -1 << (32 - (int)$mask);
        $subnet_long &= $mask_long;
        
        return ($ip_long & $mask_long) === $subnet_long;
    }
}
