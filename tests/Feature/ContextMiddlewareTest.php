<?php

use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    config(['arbac.cache.enabled' => false]); // Disable cache for tests
    config(['arbac.audit.enabled' => false]); // Disable audit for tests

    // Register TimeBasedRule and IpWhitelistRule
    config(['arbac.attribute_rules' => [
        \Amrshah\Arbac\Rules\TimeBasedRule::class,
        \Amrshah\Arbac\Rules\IpWhitelistRule::class,
    ]]);

    // Reload manager with new rules
    app()->forgetInstance('arbac');
});

it('allows request with valid IP', function () {
    config(['arbac.ip_whitelist' => ['127.0.0.1']]);

    $user = App\Models\User::factory()->create();
    Permission::create(['name' => 'ip-restricted.admin']);
    $user->givePermissionTo('ip-restricted.admin');

    $this->actingAs($user);

    Route::get('/test-ip', fn () => 'OK')->middleware('arbac.ip:ip-restricted.admin');

    $response = $this->get('/test-ip');
    $response->assertStatus(200);
    $response->assertSee('OK');
});

it('blocks request with invalid IP', function () {
    config(['arbac.ip_whitelist' => ['192.168.1.1']]);

    $user = App\Models\User::factory()->create();
    Permission::create(['name' => 'ip-restricted.admin']);
    $user->givePermissionTo('ip-restricted.admin');

    $this->actingAs($user);

    Route::get('/test-ip', fn () => 'OK')->middleware('arbac.ip:ip-restricted.admin');

    $response = $this->get('/test-ip');
    $response->assertStatus(403);
});

it('allows request within time window', function () {
    config(['arbac.time_window' => [
        'start_time' => '00:00',
        'end_time' => '23:59',
        'timezone' => 'UTC',
    ]]);

    $user = App\Models\User::factory()->create();
    Permission::create(['name' => 'time-restricted.access']);
    $user->givePermissionTo('time-restricted.access');

    $this->actingAs($user);

    Route::get('/test-time', fn () => 'OK')->middleware('arbac.time:time-restricted.access');

    $response = $this->get('/test-time');
    $response->assertStatus(200);
    $response->assertSee('OK');
});

it('blocks request outside time window', function () {
    // Set time window that's definitely not now (future)
    config(['arbac.time_window' => [
        'start_time' => '23:00',
        'end_time' => '23:01',
        'timezone' => 'UTC',
    ]]);

    $user = App\Models\User::factory()->create();
    Permission::create(['name' => 'time-restricted.access']);
    $user->givePermissionTo('time-restricted.access');

    $this->actingAs($user);

    Route::get('/test-time', fn () => 'OK')->middleware('arbac.time:time-restricted.access');

    $response = $this->get('/test-time');
    $response->assertStatus(403);
});

it('uses custom config for IP whitelist', function () {
    config(['custom.admin_ips' => ['127.0.0.1']]);

    $user = App\Models\User::factory()->create();
    Permission::create(['name' => 'ip-restricted.admin']);
    $user->givePermissionTo('ip-restricted.admin');

    $this->actingAs($user);

    Route::get('/test-custom-ip', fn () => 'OK')
        ->middleware('arbac.ip:ip-restricted.admin,custom.admin_ips');

    $response = $this->get('/test-custom-ip');
    $response->assertStatus(200);
});

it('uses custom config for time window', function () {
    config(['custom.business_hours' => [
        'start_time' => '00:00',
        'end_time' => '23:59',
        'timezone' => 'UTC',
    ]]);

    $user = App\Models\User::factory()->create();
    Permission::create(['name' => 'time-restricted.access']);
    $user->givePermissionTo('time-restricted.access');

    $this->actingAs($user);

    Route::get('/test-custom-time', fn () => 'OK')
        ->middleware('arbac.time:time-restricted.access,custom.business_hours');

    $response = $this->get('/test-custom-time');
    $response->assertStatus(200);
});

it('context middleware passes request data as context', function () {
    $user = App\Models\User::factory()->create();
    Permission::create(['name' => 'edit post']);
    $user->givePermissionTo('edit post');

    $this->actingAs($user);

    Route::post('/test-context', fn () => 'OK')
        ->middleware('arbac.context:edit post');

    $response = $this->post('/test-context', ['post_id' => 123]);
    $response->assertStatus(200);
});

it('blocks unauthenticated requests', function () {
    Route::get('/test-auth', fn () => 'OK')->middleware('arbac.ip:ip-restricted.admin');

    $response = $this->get('/test-auth');
    $response->assertStatus(403);
});
