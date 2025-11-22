<?php

use Amrshah\Arbac\ArbacManager;
use Amrshah\Arbac\Models\ArbacAuditLog;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    config(['arbac.audit.enabled' => true]);
    config(['arbac.audit.log_granted' => true]);
    config(['arbac.audit.log_denied' => true]);

    Permission::create(['name' => 'edit posts']);
    Permission::create(['name' => 'delete posts']);
});

it('logs permission checks when audit is enabled', function () {
    $user = App\Models\User::factory()->create();
    $user->givePermissionTo('edit posts');

    $manager = app(ArbacManager::class);
    $manager->check($user, 'edit posts');

    expect(ArbacAuditLog::count())->toBe(1);

    $log = ArbacAuditLog::first();
    expect($log->permission)->toBe('edit posts');
    expect($log->action)->toBe('granted');
    expect($log->user_id)->toBe($user->id);
});

it('logs denied permission checks', function () {
    $user = App\Models\User::factory()->create();

    $manager = app(ArbacManager::class);
    $manager->check($user, 'delete posts');

    expect(ArbacAuditLog::count())->toBe(1);

    $log = ArbacAuditLog::first();
    expect($log->action)->toBe('denied');
    expect($log->permission)->toBe('delete posts');
});

it('does not log when audit is disabled', function () {
    config(['arbac.audit.enabled' => false]);

    $user = App\Models\User::factory()->create();
    $user->givePermissionTo('edit posts');

    $manager = app(ArbacManager::class);
    $manager->check($user, 'edit posts');

    expect(ArbacAuditLog::count())->toBe(0);
});

it('respects log_granted configuration', function () {
    config(['arbac.audit.log_granted' => false]);

    $user = App\Models\User::factory()->create();
    $user->givePermissionTo('edit posts');

    $manager = app(ArbacManager::class);
    $manager->check($user, 'edit posts');

    expect(ArbacAuditLog::count())->toBe(0);
});

it('respects log_denied configuration', function () {
    config(['arbac.audit.log_denied' => false]);

    $user = App\Models\User::factory()->create();

    $manager = app(ArbacManager::class);
    $manager->check($user, 'delete posts');

    expect(ArbacAuditLog::count())->toBe(0);
});

it('stores context in audit logs', function () {
    $user = App\Models\User::factory()->create();
    $user->givePermissionTo('edit posts');

    $manager = app(ArbacManager::class);
    $manager->check($user, 'edit posts', ['post_id' => 123]);

    $log = ArbacAuditLog::first();
    expect($log->context)->toHaveKey('post_id');
    expect($log->context['post_id'])->toBe(123);
});

it('can query audit logs by user', function () {
    $user1 = App\Models\User::factory()->create();
    $user2 = App\Models\User::factory()->create();

    $user1->givePermissionTo('edit posts');

    $manager = app(ArbacManager::class);
    $manager->check($user1, 'edit posts');
    $manager->check($user2, 'delete posts');

    $user1Logs = ArbacAuditLog::forUser($user1)->get();
    expect($user1Logs)->toHaveCount(1);
    expect($user1Logs->first()->user_id)->toBe($user1->id);
});

it('can query audit logs by permission', function () {
    $user = App\Models\User::factory()->create();
    $user->givePermissionTo('edit posts');

    $manager = app(ArbacManager::class);
    $manager->check($user, 'edit posts');
    $manager->check($user, 'delete posts');

    $editLogs = ArbacAuditLog::forPermission('edit posts')->get();
    expect($editLogs)->toHaveCount(1);
    expect($editLogs->first()->permission)->toBe('edit posts');
});

it('can filter granted and denied logs', function () {
    $user = App\Models\User::factory()->create();
    $user->givePermissionTo('edit posts');

    $manager = app(ArbacManager::class);
    $manager->check($user, 'edit posts');
    $manager->check($user, 'delete posts');

    expect(ArbacAuditLog::granted()->count())->toBe(1);
    expect(ArbacAuditLog::denied()->count())->toBe(1);
});
