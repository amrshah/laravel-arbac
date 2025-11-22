<?php

namespace Amrshah\Arbac\Traits;

use Illuminate\Support\Facades\Cache;

trait HasCache
{
    /**
     * Generate cache key for permission check
     */
    protected function getCacheKey(string $type, $identifier): string
    {
        $tenantId = function_exists('tenant') && tenant() ? tenant('id') : 'global';

        return "arbac:{$tenantId}:{$type}:{$identifier}";
    }

    /**
     * Cache permission check result
     */
    protected function cachePermissionCheck($user, string $permission, bool $result): void
    {
        if (! config('arbac.cache.enabled', true)) {
            return;
        }

        $key = $this->getCacheKey('permission', "{$user->id}:{$permission}");
        $ttl = config('arbac.cache.ttl', 3600);
        $store = config('arbac.cache.store', 'default');

        $cache = Cache::store($store);

        if (method_exists($cache, 'tags')) {
            $cache->tags(["user:{$user->id}", 'arbac'])->put($key, $result, $ttl);
        } else {
            $cache->put($key, $result, $ttl);
        }
    }

    /**
     * Get cached permission check result
     */
    public function getCachedPermissionCheck($user, string $permission): ?bool
    {
        if (! config('arbac.cache.enabled', true)) {
            return null;
        }

        $key = $this->getCacheKey('permission', "{$user->id}:{$permission}");
        $store = config('arbac.cache.store', 'default');

        $cache = Cache::store($store);

        if (method_exists($cache, 'tags')) {
            return $cache->tags(["user:{$user->id}", 'arbac'])->get($key);
        }

        return $cache->get($key);
    }

    /**
     * Flush all permission cache for a user
     */
    public function flushUserPermissions($user): void
    {
        if (! config('arbac.cache.enabled', true)) {
            return;
        }

        $tenantId = function_exists('tenant') && tenant() ? tenant('id') : 'global';
        $pattern = "arbac:{$tenantId}:permission:{$user->id}:*";

        // Note: This requires Redis or a cache driver that supports pattern deletion
        $store = config('arbac.cache.store', 'default');
        $cache = Cache::store($store);

        if (method_exists($cache, 'tags')) {
            $cache->tags(["user:{$user->id}"])->flush();
        }
    }

    /**
     * Flush all ARBAC cache
     */
    public function flushAllCache(): void
    {
        if (! config('arbac.cache.enabled', true)) {
            return;
        }

        $store = config('arbac.cache.store', 'default');
        $cache = Cache::store($store);

        if (method_exists($cache, 'tags')) {
            $cache->tags(['arbac'])->flush();
        }
    }
}
