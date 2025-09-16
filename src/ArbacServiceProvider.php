<?php

namespace Amrshah\Arbac;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Amrshah\Arbac\Commands\ArbacCommand;

class ArbacServiceProvider extends PackageServiceProvider
{
    public function boot(): void
    {
        // publish config
        $this->publishes([
            __DIR__.'/../config/arbac.php' => config_path('arbac.php'),
        ], 'arbac-config');

        // load routes/views 
        // $this->loadViewsFrom(__DIR__.'/../resources/views', 'arbac');
        // $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
    }

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
            ->hasCommand(ArbacCommand::class);
    }
}
