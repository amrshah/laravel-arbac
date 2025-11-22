<?php

namespace Amrshah\Arbac\Observers;

use Spatie\Permission\Models\Permission;
use Amrshah\Arbac\Facades\Arbac;

class PermissionObserver
{
    /**
     * Handle permission updated event
     */
    public function updated(Permission $permission): void
    {
        if (config('arbac.cache.auto_invalidate', true)) {
            Arbac::flushAllCache();
        }
    }

    /**
     * Handle permission deleted event
     */
    public function deleted(Permission $permission): void
    {
        if (config('arbac.cache.auto_invalidate', true)) {
            Arbac::flushAllCache();
        }
    }
}
