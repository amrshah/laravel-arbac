<?php

namespace Amrshah\Arbac\Tests;

use Amrshah\Arbac\ArbacServiceProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Orchestra\Testbench\TestCase as Orchestra;
use Spatie\Permission\PermissionServiceProvider;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        // Create class alias so tests can use \App\Models\User
        if (! class_exists(\App\Models\User::class)) {
            class_alias(\Workbench\App\Models\User::class, \App\Models\User::class);
        }

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'Amrshah\\Arbac\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );

        // Clear permission cache
        $this->app->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    protected function getPackageProviders($app)
    {
        return [
            PermissionServiceProvider::class,
            ArbacServiceProvider::class,
        ];
    }

    protected function defineDatabaseMigrations()
    {
        $this->loadMigrationsFrom(__DIR__.'/../tests/database/migrations');

        // Run Spatie permission migrations
        $permissionMigration = include __DIR__.'/../vendor/spatie/laravel-permission/database/migrations/create_permission_tables.php.stub';
        $permissionMigration->up();

        // Run ARBAC migrations
        $arbacMigration = include __DIR__.'/../database/migrations/create_arbac_table.php.stub';
        $arbacMigration->up();

        $auditMigration = include __DIR__.'/../database/migrations/create_arbac_audit_logs_table.php.stub';
        $auditMigration->up();

        $groupsMigration = include __DIR__.'/../database/migrations/create_permission_groups_table.php.stub';
        $groupsMigration->up();
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');

        // Use in-memory SQLite database for testing
        config()->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Use array cache driver for testing
        config()->set('cache.default', 'array');
        config()->set('cache.stores.array', [
            'driver' => 'array',
            'serialize' => false,
        ]);

        // Configure Spatie Permission to use array cache
        config()->set('permission.cache.store', 'array');

        // Configure ARBAC to use array cache
        config()->set('arbac.cache.store', 'array');

        // Set the User model for authentication
        config()->set('auth.providers.users.model', \Workbench\App\Models\User::class);

        // Configure auth guards
        config()->set('auth.defaults.guard', 'web');
        config()->set('auth.guards.web', [
            'driver' => 'session',
            'provider' => 'users',
        ]);
        config()->set('auth.providers.users', [
            'driver' => 'eloquent',
            'model' => \Workbench\App\Models\User::class,
        ]);
    }
}
