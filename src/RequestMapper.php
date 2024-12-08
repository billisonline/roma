<?php

namespace BYanelli\Roma;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use ReflectionClass;
use ReflectionParameter;
use RuntimeException;

readonly class RequestMapper
{
    public function __construct(private Container $container) {}

    private function getParameterValueFromRequest(
        ReflectionParameter $parameter,
        Request $request
    ): mixed {
        $key = $parameter->getName();
        $type = Type::fromReflectionType($parameter->getType());
        $default = $parameter->isOptional() ? $parameter->getDefaultValue() : null;

        return !$request->has($key) ? $default : match ($type) {
            Type::String => $request->string($key)->toString(),
            Type::Bool => $request->boolean($key),
            Type::Int => $request->integer($key),
            Type::Float => $request->float($key),
            Type::Date => $request->date($key),
            Type::Mixed => $request->get($key),
        };
    }

    /**
     * @template T
     * @param class-string<T> $className
     * @return T
     * @throws BindingResolutionException
     * @throws \ReflectionException
     */
    public function mapRequest(string $className)
    {
        /** @var Request $request */
        $request = $this->container->make('request');

        $class = new ReflectionClass($className);

        if (($constructor = $class->getConstructor()) == null) {
            throw new RuntimeException('Request class must have a constructor');
        }

        $constructorParameters = $constructor->getParameters();

        $constructorValues = [];

        foreach ($constructorParameters as $constructorParameter) {
            $constructorValues[] = $this->getParameterValueFromRequest($constructorParameter, $request);
        }

        return new ($class->getName())(...$constructorValues);
    }
}
