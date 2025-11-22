<?php

namespace Amrshah\Arbac;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Amrshah\Arbac\Commands\ArbacCommand;
use Amrshah\Arbac\Commands\MakeRuleCommand;
use Amrshah\Arbac\ArbacManager;
use Illuminate\Support\Facades\Blade;
use Illuminate\Routing\Router;

class ArbacServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('arbac')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_arbac_table')
            ->hasMigration('create_arbac_audit_logs_table')
            ->hasMigration('create_permission_groups_table')
            ->hasCommand(\Amrshah\Arbac\Commands\ArbacCommand::class)
            ->hasCommand(\Amrshah\Arbac\Commands\MakeRuleCommand::class);
    }

    public function packageRegistered(): void
    {
        // Bind ArbacManager as singleton
        $this->app->singleton('arbac', function ($app) {
            $manager = new ArbacManager();
            $manager->loadAttributeRulesFromConfig();
            return $manager;
        });

        // Alias for easier access
        $this->app->alias('arbac', ArbacManager::class);
    }

    public function packageBooted(): void
    {
        // Register observers for automatic cache invalidation
        if (config('arbac.cache.auto_invalidate', true)) {
            \Spatie\Permission\Models\Role::observe(\Amrshah\Arbac\Observers\RoleObserver::class);
            \Spatie\Permission\Models\Permission::observe(\Amrshah\Arbac\Observers\PermissionObserver::class);
        }

        // Register middleware
        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('arbac', \Amrshah\Arbac\Http\Middleware\CheckPermission::class);
        $router->aliasMiddleware('role', \Amrshah\Arbac\Http\Middleware\CheckRole::class);
        
        // Context-aware middleware
        $router->aliasMiddleware('arbac.context', \Amrshah\Arbac\Http\Middleware\CheckPermissionWithContext::class);
        $router->aliasMiddleware('arbac.ip', \Amrshah\Arbac\Http\Middleware\CheckIpRestricted::class);
        $router->aliasMiddleware('arbac.time', \Amrshah\Arbac\Http\Middleware\CheckTimeRestricted::class);

        // Register Blade directives
        $this->registerBladeDirectives();
    }

    protected function registerBladeDirectives(): void
    {
        // @arbac('edit post', ['post' => $post])
        Blade::directive('arbac', function ($expression) {
            return "<?php if(app('arbac')->check(auth()->user(), {$expression})): ?>";
        });

        Blade::directive('endarbac', function () {
            return "<?php endif; ?>";
        });

        // @hasrole('admin')
        Blade::directive('hasrole', function ($expression) {
            return "<?php if(auth()->check() && auth()->user()->hasRole({$expression})): ?>";
        });

        Blade::directive('endhasrole', function () {
            return "<?php endif; ?>";
        });

        // @haspermission('users.create')
        Blade::directive('haspermission', function ($expression) {
            return "<?php if(auth()->check() && auth()->user()->can({$expression})): ?>";
        });

        Blade::directive('endhaspermission', function () {
            return "<?php endif; ?>";
        });

        // @unlessrole('admin')
        Blade::directive('unlessrole', function ($expression) {
            return "<?php if(auth()->check() && !auth()->user()->hasRole({$expression})): ?>";
        });

        Blade::directive('endunlessrole', function () {
            return "<?php endif; ?>";
        });
    }
}
