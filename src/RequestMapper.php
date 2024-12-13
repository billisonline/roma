<?php

namespace BYanelli\Roma;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Support\Fluent;
use Illuminate\Support\Str;
use ReflectionClass;
use RuntimeException;

readonly class RequestMapper
{
    public function __construct(private Container $container) {}

    private function getValueFromData(Fluent $data, Property $property, bool $isHeader): mixed
    {
        $key = $isHeader
            ? Str::of($property->name)->upper()->replace('-', '_')->toString()
            : $property->name;

        return !$data->has($key) ? $property->default : match ($property->type) {
            Type::String => $data->string($key)->toString(),
            Type::Bool => $data->boolean($key),
            Type::Int => $data->integer($key),
            Type::Float => $data->float($key),
            Type::Date => $data->date($key),
            Type::Mixed => $data->get($key),
        };
    }

    private function getValueForProperty(Request $request, Property $property) {
        $data = match ($property->source) {
            Source::QueryOrBody => new Fluent($request->input()),
            Source::Query => new Fluent($request->query->all()),
            Source::Body => new Fluent($request->isJson() ? $request->json()->all() : $request->request->all()),
            Source::Header => new Fluent($request->server->getHeaders()),
            Source::File => throw new RuntimeException("Unsupported source type: {$property->type->name}"), // todo
        };

        return $this->getValueFromData($data, $property, $property->source == Source::Header);
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

        $constructorValues = [];

        if (($constructor = $class->getConstructor()) != null) {
            $constructorParameters = $constructor->getParameters();

            foreach ($constructorParameters as $constructorParameter) {
                $property = Property::fromReflectionObject($constructorParameter);

                $constructorValues[] = $this->getValueForProperty($request, $property);
            }
        }

        $instance = new ($class->getName())(...$constructorValues);

        $classProperties = $class->getProperties(\ReflectionProperty::IS_PUBLIC);

        foreach ($classProperties as $classProperty) {
            if ($classProperty->isStatic() || $classProperty->isPromoted()) { continue; }

            $property = Property::fromReflectionObject($classProperty);

            $classProperty->setValue($instance, $this->getValueForProperty($request, $property));
        }

        return $instance;
    }
}
