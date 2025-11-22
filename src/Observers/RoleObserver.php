<?php

namespace Amrshah\Arbac\Observers;

use Spatie\Permission\Models\Role;
use Amrshah\Arbac\Facades\Arbac;

class RoleObserver
{
    /**
     * Handle role updated event
     */
    public function updated(Role $role): void
    {
        if (config('arbac.cache.auto_invalidate', true)) {
            Arbac::flushAllCache();
        }
    }

    /**
     * Handle role deleted event
     */
    public function deleted(Role $role): void
    {
        if (config('arbac.cache.auto_invalidate', true)) {
            Arbac::flushAllCache();
        }
    }
}
