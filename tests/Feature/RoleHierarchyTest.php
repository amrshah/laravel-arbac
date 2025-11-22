<?php

use Spatie\Permission\Models\Role;




beforeEach(function () {
    config(['arbac.role_hierarchy' => [
        'super_admin' => ['admin', 'manager', 'member'],
        'admin' => ['manager', 'member'],
        'manager' => ['member'],
    ]]);
});

it('checks one-level hierarchy with hasRoleOrHigher', function () {
    $user = App\Models\User::factory()->create();
    $admin = Role::create(['name' => 'admin']);
    $user->assignRole($admin);
    
    // Add trait to user
    $user = new class extends App\Models\User {
        use \Amrshah\Arbac\Traits\HasRoleHierarchy;
    };
    // $admin role already created above
    $user->setRelation('roles', collect([$admin]));
    
    expect($user->hasRoleOrHigher('admin'))->toBeTrue();
    expect($user->hasRoleOrHigher('manager'))->toBeTrue();
    expect($user->hasRoleOrHigher('member'))->toBeTrue();
    expect($user->hasRoleOrHigher('super_admin'))->toBeFalse();
});

it('checks transitive hierarchy with hasRoleOrHigherTransitive', function () {
    config(['arbac.role_hierarchy' => [
        'super_admin' => ['admin'],
        'admin' => ['manager'],
        'manager' => ['member'],
    ]]);
    
    $user = new class extends \Illuminate\Foundation\Auth\User {
        use \Amrshah\Arbac\Traits\HasTransitiveRoleHierarchy;
        use \Spatie\Permission\Traits\HasRoles;
        
        protected $guarded = [];
    };
    
    $superAdmin = Role::create(['name' => 'super_admin']);
    $user->setRelation('roles', collect([$superAdmin]));
    
    // Transitive checks
    expect($user->hasRoleOrHigherTransitive('super_admin'))->toBeTrue();
    expect($user->hasRoleOrHigherTransitive('admin'))->toBeTrue();
    expect($user->hasRoleOrHigherTransitive('manager'))->toBeTrue();
    expect($user->hasRoleOrHigherTransitive('member'))->toBeTrue();
});

it('prevents infinite recursion in hierarchy', function () {
    // Malformed config with cycle
    config(['arbac.role_hierarchy' => [
        'role_a' => ['role_b'],
        'role_b' => ['role_a'], // Cycle!
    ]]);
    
    $user = new class extends \Illuminate\Foundation\Auth\User {
        use \Amrshah\Arbac\Traits\HasTransitiveRoleHierarchy;
        use \Spatie\Permission\Traits\HasRoles;
        
        protected $guarded = [];
    };
    
    $roleA = Role::create(['name' => 'role_a']);
    $user->setRelation('roles', collect([$roleA]));
    
    // Should not hang or crash
    expect($user->hasRoleOrHigherTransitive('role_b', 5))->toBeTrue();
});

it('handles deep hierarchy correctly', function () {
    config(['arbac.role_hierarchy' => [
        'level_1' => ['level_2'],
        'level_2' => ['level_3'],
        'level_3' => ['level_4'],
        'level_4' => ['level_5'],
    ]]);
    
    $user = new class extends \Illuminate\Foundation\Auth\User {
        use \Amrshah\Arbac\Traits\HasTransitiveRoleHierarchy;
        use \Spatie\Permission\Traits\HasRoles;
        
        protected $guarded = [];
    };
    
    $level1 = Role::create(['name' => 'level_1']);
    $user->setRelation('roles', collect([$level1]));
    
    expect($user->hasRoleOrHigherTransitive('level_5'))->toBeTrue();
    expect($user->hasRoleOrHigherTransitive('level_3'))->toBeTrue();
    expect($user->hasRoleOrHigherTransitive('nonexistent'))->toBeFalse();
});

it('respects max depth limit', function () {
    config(['arbac.role_hierarchy' => [
        'level_1' => ['level_2'],
        'level_2' => ['level_3'],
        'level_3' => ['level_4'],
        'level_4' => ['level_5'],
        'level_5' => ['level_6'],
    ]]);
    
    $user = new class extends \Illuminate\Foundation\Auth\User {
        use \Amrshah\Arbac\Traits\HasTransitiveRoleHierarchy;
        use \Spatie\Permission\Traits\HasRoles;
        
        protected $guarded = [];
    };
    
    $level1 = Role::create(['name' => 'level_1']);
    $user->setRelation('roles', collect([$level1]));
    
    // With max depth of 3, should not reach level_6
    expect($user->hasRoleOrHigherTransitive('level_3', 3))->toBeTrue();
    expect($user->hasRoleOrHigherTransitive('level_6', 3))->toBeFalse();
});

it('handles user with multiple roles', function () {
    $user = new class extends \Illuminate\Foundation\Auth\User {
        use \Amrshah\Arbac\Traits\HasTransitiveRoleHierarchy;
        use \Spatie\Permission\Traits\HasRoles;
        
        protected $guarded = [];
    };
    
    $admin = Role::create(['name' => 'admin']);
    $manager = Role::create(['name' => 'manager']);
    $user->setRelation('roles', collect([$admin, $manager]));
    
    expect($user->hasRoleOrHigherTransitive('admin'))->toBeTrue();
    expect($user->hasRoleOrHigherTransitive('manager'))->toBeTrue();
    expect($user->hasRoleOrHigherTransitive('member'))->toBeTrue();
});

it('returns false for empty hierarchy', function () {
    config(['arbac.role_hierarchy' => []]);
    
    $user = new class extends \Illuminate\Foundation\Auth\User {
        use \Amrshah\Arbac\Traits\HasTransitiveRoleHierarchy;
        use \Spatie\Permission\Traits\HasRoles;
        
        protected $guarded = [];
    };
    
    $admin = Role::create(['name' => 'admin']);
    $user->setRelation('roles', collect([$admin]));
    
    expect($user->hasRoleOrHigherTransitive('manager'))->toBeFalse();
    expect($user->hasRoleOrHigherTransitive('admin'))->toBeTrue(); // Direct match still works
});
