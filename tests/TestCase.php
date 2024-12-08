<?php

namespace BYanelli\Roma\Tests;

use BYanelli\Roma\RequestMapper;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Http\Request;
use Orchestra\Testbench\TestCase as Orchestra;
use BYanelli\Roma\RomaServiceProvider;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'VendorName\\Skeleton\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );
    }

    protected function getPackageProviders($app)
    {
        return [
            RomaServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');

        /*
        $migration = include __DIR__.'/../database/migrations/create_skeleton_table.php.stub';
        $migration->up();
        */
    }

    protected function bindRequest(array $query=[]): void {
        $request = new Request(query: $query);

        $this->app->bind('request', fn() => $request);
    }

    protected function getRequestMapper(): RequestMapper
    {
        return $this->app->make(RequestMapper::class);
    }
}
