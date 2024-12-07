<?php

namespace BYanelli\Roma;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use BYanelli\Roma\Commands\SkeletonCommand;

class RomaServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        // todo: is the service provider necessary at all?
        $package->name('roma');
    }
}
