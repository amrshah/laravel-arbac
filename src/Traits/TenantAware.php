<?php

namespace Amrshah\Arbac\Traits;

trait TenantAware
{
    /**
     * Scope query to current tenant
     */
    public function scopeTenant($query)
    {
        if (function_exists('tenant') && tenant()) {
            return $query->where('tenant_id', tenant('id'));
        }
        
        return $query;
    }

    /**
     * Get tenant ID for current context
     */
    public function getCurrentTenantId(): ?string
    {
        if (function_exists('tenant') && tenant()) {
            return tenant('id');
        }
        
        return null;
    }

    /**
     * Check if multi-tenancy is enabled
     */
    public function isMultiTenancyEnabled(): bool
    {
        return config('arbac.multi_tenancy.enabled', false);
    }
}
