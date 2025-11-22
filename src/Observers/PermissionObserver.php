<?php

namespace Amrshah\Arbac\Observers;

use Amrshah\Arbac\Facades\Arbac;
use Spatie\Permission\Models\Permission;

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
