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
        'user' => App\Models\User::class,
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
        'store' => env('ARBAC_CACHE_STORE', 'default'),
        'ttl'   => env('ARBAC_CACHE_TTL', 3600), // seconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Attribute-Based Rules
    |--------------------------------------------------------------------------
    |
    | You can register attribute-based rules here. Each rule is a class that
    | implements Amrshah\Arbac\Contracts\AttributeRule.
    | Example:
    | 'post_owner' => \App\ArbacRules\PostOwnerRule::class
    |
    */

    'attribute_rules' => [
        \App\Arbac\Rules\PostOwnerRule::class,
        \App\Arbac\Rules\DepartmentRule::class,
    ],


    /*
    |--------------------------------------------------------------------------
    | UI Settings
    |--------------------------------------------------------------------------
    |
    | When using ARBAC’s built-in UI components, these options control styling,
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

   


];
