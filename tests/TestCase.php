<?php

namespace BYanelli\Roma\Tests;

use BYanelli\Roma\Contracts\RequestMapper;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
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

    public function bindRequest(array $query=[], array $headers=[]): void {
        $server = collect($headers)->mapWithKeys(function ($value, $key) {
            $key = (($key != 'Content-Type') ? 'HTTP_' : '')
                . str_replace('-', '_', strtoupper($key));

            return [$key => $value];
        })->toArray();

        $request = new Request(query: $query, server: $server);

        $this->app->bind('request', fn() => $request);
    }

    public function getRequestMapper(): RequestMapper
    {
        return $this->app->make(RequestMapper::class);
    }

    /**
     * @template T
     * @param class-string<T> $class
     * @return T
     * @throws ValidationException
     * @throws \ReflectionException
     */
    public function mapRequest(string $class): mixed
    {
        return $this->getRequestMapper()->mapRequest($class);
    }
}
