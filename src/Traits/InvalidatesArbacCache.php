<?php

namespace Amrshah\Arbac\Traits;

use Amrshah\Arbac\Facades\Arbac;

/**
 * Add this trait to your User model to automatically
 * invalidate ARBAC cache when user roles change
 *
 * Usage:
 * class User extends Authenticatable
 * {
 *     use InvalidatesArbacCache;
 * }
 */
trait InvalidatesArbacCache
{
    /**
     * Boot the trait
     */
    public static function bootInvalidatesArbacCache(): void
    {
        // When user is updated (including role changes)
        static::updated(function ($model) {
            if (config('arbac.cache.auto_invalidate', true)) {
                Arbac::flushUserPermissions($model);
            }
        });

        // When user is deleted
        static::deleted(function ($model) {
            if (config('arbac.cache.auto_invalidate', true)) {
                Arbac::flushUserPermissions($model);
            }
        });
    }
}
