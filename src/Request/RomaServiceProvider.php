<?php

namespace BYanelli\Roma\Request;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class RomaServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package->name('roma');

        $this->app->bind(Contracts\RequestResolver::class, fn () => $this->app->make(RequestResolver::class));
        $this->app->bind(Contracts\RequestMapper::class, fn () => $this->app->make(RequestMapper::class));
    }
}
