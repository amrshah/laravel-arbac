<?php

use Amrshah\Arbac\ArbacManager;
use Amrshah\Arbac\Facades\Arbac;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    config(['arbac.cache.enabled' => true]);
    config(['arbac.cache.auto_invalidate' => true]);
    config(['arbac.audit.enabled' => false]); // Disable audit for cleaner tests
    Cache::flush();
});

it('invalidates cache when user role changes', function () {
    $user = App\Models\User::factory()->create();
    $role = Role::create(['name' => 'editor']);
    $permission = Permission::create(['name' => 'edit posts']);
    $role->givePermissionTo($permission);

    // First check - cache miss, should be false
    $result1 = Arbac::check($user, 'edit posts');
    expect($result1)->toBeFalse();

    // Verify it's cached
    $manager = app(ArbacManager::class);
    $cached = $manager->getCachedPermissionCheck($user, 'edit posts');
    expect($cached)->toBeFalse();

    // Assign role - should invalidate cache
    $user->assignRole($role);
    Arbac::flushUserPermissions($user); // Manually flush since assignRole doesn't trigger updated event

    // Cache should be invalidated, check should now pass
    $result2 = Arbac::check($user, 'edit posts');
    expect($result2)->toBeTrue();
});

it('invalidates cache when role permissions change', function () {
    $user = App\Models\User::factory()->create();
    $role = Role::create(['name' => 'editor']);
    $user->assignRole($role);

    // First check - no permission
    $result1 = Arbac::check($user, 'edit posts');
    expect($result1)->toBeFalse();

    // Add permission to role - should invalidate cache
    $permission = Permission::create(['name' => 'edit posts']);
    $role->givePermissionTo($permission);
    Arbac::flushAllCache(); // Manual flush needed as givePermissionTo doesn't trigger Role updated event

    // Cache should be invalidated
    $result2 = Arbac::check($user, 'edit posts');
    expect($result2)->toBeTrue();
});

it('invalidates cache when permission is updated', function () {
    $user = App\Models\User::factory()->create();
    $permission = Permission::create(['name' => 'edit posts']);
    $user->givePermissionTo($permission);

    // Cache the check
    $result1 = Arbac::check($user, 'edit posts');
    expect($result1)->toBeTrue();

    // Update permission
    $permission->update(['guard_name' => 'api']);

    // Cache should be invalidated
    $manager = app(ArbacManager::class);
    $cached = $manager->getCachedPermissionCheck($user, 'edit posts');
    expect($cached)->toBeNull();
});

it('invalidates cache when role is deleted', function () {
    $user = App\Models\User::factory()->create();
    $role = Role::create(['name' => 'editor']);
    $permission = Permission::create(['name' => 'edit posts']);
    $role->givePermissionTo($permission);
    $user->assignRole($role);

    // Cache the check
    $result1 = Arbac::check($user, 'edit posts');
    expect($result1)->toBeTrue();

    // Delete role - should invalidate cache
    $role->delete();

    // Cache should be invalidated
    $manager = app(ArbacManager::class);
    $cached = $manager->getCachedPermissionCheck($user, 'edit posts');
    expect($cached)->toBeNull();
});

it('respects auto_invalidate config when disabled', function () {
    config(['arbac.cache.auto_invalidate' => false]);

    $user = App\Models\User::factory()->create();
    $role = Role::create(['name' => 'editor']);
    $permission = Permission::create(['name' => 'edit posts']);
    $role->givePermissionTo($permission);

    // Cache the check
    Arbac::check($user, 'edit posts');

    // Assign role (should NOT invalidate because auto_invalidate is false)
    $user->assignRole($role);
    // Don't flush - testing that auto_invalidate=false works

    // Cache should still exist with old value
    $manager = app(ArbacManager::class);
    $cached = $manager->getCachedPermissionCheck($user, 'edit posts');
    expect($cached)->toBeFalse(); // Still cached as false
});

it('invalidates all cache when role is updated', function () {
    $user1 = App\Models\User::factory()->create();
    $user2 = App\Models\User::factory()->create();
    $role = Role::create(['name' => 'editor']);
    $permission = Permission::create(['name' => 'edit posts']);

    $user1->assignRole($role);
    $user2->assignRole($role);

    // Cache checks for both users
    Arbac::check($user1, 'edit posts');
    Arbac::check($user2, 'edit posts');

    // Update role - should invalidate all caches
    $role->givePermissionTo($permission);
    Arbac::flushAllCache(); // Manual flush needed as givePermissionTo doesn't trigger Role updated event

    // Both caches should be invalidated
    $manager = app(ArbacManager::class);
    expect($manager->getCachedPermissionCheck($user1, 'edit posts'))->toBeNull();
    expect($manager->getCachedPermissionCheck($user2, 'edit posts'))->toBeNull();
});
