<?php

namespace BYanelli\Roma;

use BYanelli\Roma\Contracts\RequestResolver as RequestResolverContract;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class RomaServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package->name('roma');

        $this->app->bind(RequestResolverContract::class, fn() => $this->app->make(RequestResolver::class));
    }
}
