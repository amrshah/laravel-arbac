<?php

namespace Amrshah\Arbac;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Amrshah\Arbac\Commands\ArbacCommand;
use Amrshah\Arbac\Commands\MakeRuleCommand;
use Amrshah\Arbac\ArbacManager;
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
            ->hasCommand(\Amrshah\Arbac\Commands\ArbacCommand::class)
            ->hasCommand(\Amrshah\Arbac\Commands\MakeRuleCommand::class);
    }

}
