<?php

use Amrshah\Arbac\ArbacManager;
use Illuminate\Support\Facades\Cache;




use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Cache::flush();
    config(['arbac.cache.enabled' => true]);
    config(['arbac.cache.ttl' => 3600]);
    
    Permission::create(['name' => 'edit posts']);
    Permission::create(['name' => 'delete posts']);
});

it('caches permission checks', function () {
    $user = App\Models\User::factory()->create();
    $user->givePermissionTo('edit posts');
    
    $manager = app(ArbacManager::class);
    
    // First check - hits database
    $result1 = $manager->check($user, 'edit posts');
    
    // Second check - hits cache
    $result2 = $manager->check($user, 'edit posts');
    
    expect($result1)->toBeTrue();
    expect($result2)->toBeTrue();
});

it('respects cache enabled configuration', function () {
    config(['arbac.cache.enabled' => false]);
    
    $user = App\Models\User::factory()->create();
    $user->givePermissionTo('edit posts');
    
    $manager = app(ArbacManager::class);
    $manager->check($user, 'edit posts');
    
    // Cache should not be used
    $cached = $manager->getCachedPermissionCheck($user, 'edit posts');
    expect($cached)->toBeNull();
});

it('can flush user permissions cache', function () {
    $user = App\Models\User::factory()->create();
    $user->givePermissionTo('edit posts');
    
    $manager = app(ArbacManager::class);
    
    // Cache the permission
    $manager->check($user, 'edit posts');
    
    // Flush cache
    $manager->flushUserPermissions($user);
    
    // Cache should be empty
    $cached = $manager->getCachedPermissionCheck($user, 'edit posts');
    expect($cached)->toBeNull();
});

it('caches both granted and denied permissions', function () {
    $user = App\Models\User::factory()->create();
    $user->givePermissionTo('edit posts');
    
    $manager = app(ArbacManager::class);
    
    // Cache granted permission
    $granted = $manager->check($user, 'edit posts');
    expect($granted)->toBeTrue();
    
    // Cache denied permission
    $denied = $manager->check($user, 'delete posts');
    expect($denied)->toBeFalse();
    
    // Both should be cached
    expect($manager->getCachedPermissionCheck($user, 'edit posts'))->toBeTrue();
    expect($manager->getCachedPermissionCheck($user, 'delete posts'))->toBeFalse();
});

it('generates unique cache keys for different users', function () {
    $user1 = App\Models\User::factory()->create();
    $user2 = App\Models\User::factory()->create();
    
    $user1->givePermissionTo('edit posts');
    
    $manager = app(ArbacManager::class);
    
    $manager->check($user1, 'edit posts');
    $manager->check($user2, 'edit posts');
    
    // User 1 should have cached true
    expect($manager->getCachedPermissionCheck($user1, 'edit posts'))->toBeTrue();
    
    // User 2 should have cached false
    expect($manager->getCachedPermissionCheck($user2, 'edit posts'))->toBeFalse();
});
