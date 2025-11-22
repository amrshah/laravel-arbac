<?php

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    config(['arbac.multi_tenancy.enabled' => true]);
    config(['arbac.cache.enabled' => false]);
    config(['arbac.audit.enabled' => false]);
});

it('bypasses tenant check for super admin', function () {
    $superAdmin = App\Models\User::factory()->create();
    $superAdminRole = Role::create(['name' => 'super_admin']);
    $superAdmin->assignRole($superAdminRole);

    $manager = app(\Amrshah\Arbac\ArbacManager::class);

    expect($manager->shouldBypassTenantFor($superAdmin))->toBeTrue();
});

it('does not bypass tenant check for regular user', function () {
    $user = App\Models\User::factory()->create();
    $editorRole = Role::create(['name' => 'editor']);
    $user->assignRole($editorRole);

    $manager = app(\Amrshah\Arbac\ArbacManager::class);

    expect($manager->shouldBypassTenantFor($user))->toBeFalse();
});

it('respects bypass_roles configuration', function () {
    config(['arbac.multi_tenancy.bypass_roles' => ['admin', 'super_admin']]);

    $admin = App\Models\User::factory()->create();
    $adminRole = Role::create(['name' => 'admin']);
    $admin->assignRole($adminRole);

    $manager = app(\Amrshah\Arbac\ArbacManager::class);

    expect($manager->shouldBypassTenantFor($admin))->toBeTrue();
});

it('does not leak bypass across requests', function () {
    $superAdmin = App\Models\User::factory()->create();
    $superAdminRole = Role::create(['name' => 'super_admin']);
    $superAdmin->assignRole($superAdminRole);

    $regularUser = App\Models\User::factory()->create();

    $manager = app(\Amrshah\Arbac\ArbacManager::class);

    // Check super admin
    $bypass1 = $manager->shouldBypassTenantFor($superAdmin);
    expect($bypass1)->toBeTrue();

    // Check regular user (should not be affected by previous check)
    $bypass2 = $manager->shouldBypassTenantFor($regularUser);
    expect($bypass2)->toBeFalse();

    // Verify no state leakage
    expect($manager->shouldBypassTenantFor($superAdmin))->toBeTrue();
    expect($manager->shouldBypassTenantFor($regularUser))->toBeFalse();
});

it('bypasses tenant context injection for super admin', function () {
    // Mock tenant function
    if (! function_exists('tenant')) {
        function tenant($key = null)
        {
            return $key === 'id' ? 123 : (object) ['id' => 123];
        }
    }

    $superAdmin = App\Models\User::factory()->create();
    $superAdminRole = Role::create(['name' => 'super_admin']);
    $permission = Permission::create(['name' => 'edit posts']);
    $superAdmin->assignRole($superAdminRole);
    $superAdmin->givePermissionTo($permission);

    $manager = app(\Amrshah\Arbac\ArbacManager::class);

    // Super admin should pass without tenant_id in attributes
    $result = $manager->check($superAdmin, 'edit posts', []);
    expect($result)->toBeTrue();
});

it('includes tenant context for regular user', function () {
    // Mock tenant function
    if (! function_exists('tenant')) {
        function tenant($key = null)
        {
            return $key === 'id' ? 123 : (object) ['id' => 123];
        }
    }

    $user = App\Models\User::factory()->create();
    $editorRole = Role::create(['name' => 'editor']);
    $permission = Permission::create(['name' => 'edit posts']);
    $user->assignRole($editorRole);
    $user->givePermissionTo($permission);

    $manager = app(\Amrshah\Arbac\ArbacManager::class);

    // Regular user check should include tenant context
    $result = $manager->check($user, 'edit posts', []);
    expect($result)->toBeTrue();
});

it('handles user with no roles', function () {
    $user = App\Models\User::factory()->create();

    $manager = app(\Amrshah\Arbac\ArbacManager::class);

    expect($manager->shouldBypassTenantFor($user))->toBeFalse();
});

it('handles multiple bypass roles', function () {
    config(['arbac.multi_tenancy.bypass_roles' => ['super_admin', 'global_admin', 'system_admin']]);

    $globalAdmin = App\Models\User::factory()->create();
    $globalAdminRole = Role::create(['name' => 'global_admin']);
    $globalAdmin->assignRole($globalAdminRole);

    $manager = app(\Amrshah\Arbac\ArbacManager::class);

    expect($manager->shouldBypassTenantFor($globalAdmin))->toBeTrue();
});
