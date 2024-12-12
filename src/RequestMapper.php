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

    private function getValueForProperty(Request $request, Property $property) {
        $key = $property->name;

        return !$request->has($key) ? $property->default : match ($property->type) {
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
            $property = Property::fromReflectionParameter($constructorParameter);

            $constructorValues[] = $this->getValueForProperty($request, $property);
        }

        $instance = new ($class->getName())(...$constructorValues);

        $classProperties = $class->getProperties(\ReflectionProperty::IS_PUBLIC);

        foreach ($classProperties as $classProperty) {
            if ($classProperty->isStatic() || $classProperty->isPromoted()) { continue; }

            $property = Property::fromReflectionProperty($classProperty);

            $classProperty->setValue($instance, $this->getValueForProperty($request, $property));
        }

        return $instance;
    }
}
