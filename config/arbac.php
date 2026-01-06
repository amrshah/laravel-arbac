<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Models
    |--------------------------------------------------------------------------
    |
    | Here you can override the models ARBAC uses for users, roles, and permissions.
    | By default we assume you’re using Laravel’s default User model.
    |
    */

    'models' => [
        'user' => null, // Leave null to use default auth user model
        'role' => Amrshah\Arbac\Models\Role::class,
        'permission' => Amrshah\Arbac\Models\Permission::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Settings
    |--------------------------------------------------------------------------
    |
    | ARBAC caches roles/permissions/attributes to improve performance.
    | You can change the cache store or TTL here.
    |
    */

    'cache' => [
        'enabled' => env('ARBAC_CACHE_ENABLED', true),
        'auto_invalidate' => env('ARBAC_CACHE_AUTO_INVALIDATE', true),
        'store' => env('ARBAC_CACHE_STORE', 'default'),
        'ttl' => env('ARBAC_CACHE_TTL', 3600), // seconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Multi-Tenancy Settings
    |--------------------------------------------------------------------------
    |
    | Enable multi-tenancy support for tenant-aware permissions.
    | When enabled, permissions will be scoped to the current tenant.
    |
    */

    'multi_tenancy' => [
        'enabled' => env('ARBAC_MULTI_TENANCY_ENABLED', false),
        'bypass_roles' => ['super_admin'], // Roles that bypass tenant checks
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit Logging
    |--------------------------------------------------------------------------
    |
    | Enable audit logging to track all permission checks.
    | You can configure what to log (granted, denied, or both).
    |
    */

    'audit' => [
        'enabled' => env('ARBAC_AUDIT_ENABLED', false),
        'log_granted' => env('ARBAC_AUDIT_LOG_GRANTED', true),
        'log_denied' => env('ARBAC_AUDIT_LOG_DENIED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Attribute-Based Rules
    |--------------------------------------------------------------------------
    |
    | You can register attribute-based rules here. Each rule is a class that
    | implements Amrshah\Arbac\Contracts\AttributeRuleInterface.
    | Example:
    | 'post_owner' => \App\ArbacRules\PostOwnerRule::class
    |
    */

    'attribute_rules' => [
        // \App\Arbac\Rules\PostOwnerRule::class,
        // \App\Arbac\Rules\DepartmentRule::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Role Hierarchy
    |--------------------------------------------------------------------------
    |
    | Define role hierarchy where higher roles inherit permissions from lower roles.
    | Example: 'super_admin' => ['admin', 'manager', 'member']
    |
    */

    'role_hierarchy' => [
        // 'super_admin' => ['admin', 'manager', 'member'],
        // 'admin' => ['manager', 'member'],
        // 'manager' => ['member'],
    ],

    /*
    |--------------------------------------------------------------------------
    | UI Settings
    |--------------------------------------------------------------------------
    |
    | When using ARBAC's built-in UI components, these options control styling,
    | middleware, and access. Leave null to use defaults.
    |
    */

    'ui' => [
        'enabled' => true,
        'middleware' => ['web', 'auth'],
        'blade_prefix' => 'arbac::', // where Blade views are published
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Guard
    |--------------------------------------------------------------------------
    |
    | This is the guard name ARBAC will use for roles/permissions.
    | Typically this is "web", but you can change it if needed.
    |
    */

    'guard' => 'web',

    /*
    |--------------------------------------------------------------------------
    | IP Whitelist
    |--------------------------------------------------------------------------
    |
    | Default IP whitelist for ip-restricted permissions.
    | Can be overridden per-route or per-permission.
    |
    */

    'ip_whitelist' => env('ARBAC_IP_WHITELIST')
        ? explode(',', env('ARBAC_IP_WHITELIST'))
        : [],

    /*
    |--------------------------------------------------------------------------
    | Time Window
    |--------------------------------------------------------------------------
    |
    | Default time window for time-restricted permissions.
    |
    */

    'time_window' => [
        'start_time' => env('ARBAC_TIME_START', '09:00'),
        'end_time' => env('ARBAC_TIME_END', '17:00'),
        'timezone' => env('ARBAC_TIMEZONE', config('app.timezone', 'UTC')),
    ],

];
