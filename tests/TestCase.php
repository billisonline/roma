<?php

namespace BYanelli\Roma\Tests;

use BYanelli\Roma\Request\Contracts\RequestMapper;
use BYanelli\Roma\Request\RomaServiceProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Orchestra\Testbench\TestCase as Orchestra;

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
    }

    public function setRequest(
        array $query=[],
        array $headers=[],
        ?array $json=null,
    ): void {
        $server = collect($headers)->mapWithKeys(function ($value, $key) {
            $key = (($key != 'Content-Type') ? 'HTTP_' : '')
                . str_replace('-', '_', strtoupper($key));

            return [$key => $value];
        })->toArray();

        $request = ($json != null)
            ? new Request(query: $query, server: $server, content: json_encode($json))
            : new Request(query: $query, server: $server);

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
