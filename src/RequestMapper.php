<?php

namespace BYanelli\Roma;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Validation\Factory;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use ReflectionClass;
use RuntimeException;

readonly class RequestMapper
{
    public function __construct(private Container $container) {}

    /**
     * @param ReflectionClass $class
     * @return Property[]
     */
    private function getPropertiesFromConstructorParameters(ReflectionClass $class): array
    {
        $result = [];

        if (($constructor = $class->getConstructor()) != null) {
            $constructorParameters = $constructor->getParameters();

            foreach ($constructorParameters as $constructorParameter) {
                $result[] = Property::fromReflectionObject($constructorParameter);
            }
        }

        return $result;
    }

    /**
     * @param ReflectionClass $class
     * @return Property[]
     */
    private function getPropertiesFromClassProperties(ReflectionClass $class): array
    {
        $result = [];

        $classProperties = $class->getProperties(\ReflectionProperty::IS_PUBLIC);

        foreach ($classProperties as $classProperty) {
            if ($classProperty->isStatic() || $classProperty->isPromoted()) { continue; }

            $result[] = Property::fromReflectionObject($classProperty);
        }

        return $result;
    }

    private function flattenRequest(Request $request): array
    {
        return [
            'input' => $request->input(),
            'query' => $request->query->all(),
            'header' => $request->server->getHeaders(),
            'body' => $request->isJson() ? $request->json()->all() : $request->request->all(),
        ];
    }

    /**
     * @template T
     * @param class-string<T> $className
     * @return T
     * @throws BindingResolutionException
     * @throws \ReflectionException|ValidationException
     */
    public function mapRequest(string $className)
    {
        $request = $this->flattenRequest($this->container->make('request'));

        /** @var Factory $validator */
        $validator = $this->container->make('validator');

        $class = new ReflectionClass($className);

        $constructorParameters = $this->getPropertiesFromConstructorParameters($class);
        $classProperties = $this->getPropertiesFromClassProperties($class);

        $validationRules = $this->getValidationRulesFromProperties([...$constructorParameters, ...$classProperties]);

        $request = $this->castRequestData($request, [...$constructorParameters, ...$classProperties]);

        $validator->make($request, $validationRules)->validate();

        $constructorValues = [];

        foreach ($constructorParameters as $constructorParameter) {
            $constructorValues[] = Arr::get($request, $this->getAccessKey($constructorParameter));
        }

        $instance = new ($class->getName())(...$constructorValues);

        foreach ($classProperties as $classProperty) {
            $modifier = new \ReflectionProperty($instance, $classProperty->name);

            $modifier->setValue($instance, Arr::get($request, $this->getAccessKey($classProperty)));
        }

        return $instance;
    }

    private function getAccessKey(Property $property): string
    {
        $source = match ($property->source) {
            Source::Input => 'input',
            Source::Query => 'query',
            Source::Body => 'body',
            Source::Header => 'header',
            Source::File => 'file',
        };

        $key = ($property->source == Source::Header)
            ? Str::of($property->key)->upper()->replace('-', '_')->toString()
            : $property->key;

        return $source.'.'.$key;
    }

    /**
     * @param Property[] $properties
     * @return void
     */
    private function getValidationRulesFromProperties(array $properties): array
    {
        $rules = [];

        foreach ($properties as $property) {
            $propertyRules = [];

            if ($property->type != Type::Mixed) {
                $propertyRules[] = match ($property->type) {
                    Type::Bool => 'boolean',
                    Type::Int => 'integer',
                    Type::Float => 'numeric',
                    Type::Date => 'date',
                    Type::String => 'string',
                    default => throw new RuntimeException("Unsupported type: {$property->type->name}"),
                };
            }

            if ($property->isRequired) {
                $propertyRules[] = 'required';
            }

            $rules[$this->getAccessKey($property)] = $propertyRules;
        }

        return $rules;
    }

    private function toBoolean(string $val): bool
    {
        return ($val == 'true')
            ? true
            : (($val == 'false')
                ? false
                : throw new RuntimeException("Invalid boolean: $val"));
    }

    private function toInteger(string $val): int
    {
        return (is_numeric($val) && !str_contains($val, '.'))
            ? intval($val)
            : throw new RuntimeException("Invalid integer: $val");
    }

    private function toFloat(string $val): float
    {

        return is_numeric($val)
            ? floatval($val)
            : throw new RuntimeException("Invalid integer: $val");
    }

    /**
     * @param array $request
     * @param Property[] $properties
     * @return array
     */
    private function castRequestData(array $request, array $properties): array
    {
        $result = $request;

        foreach ($properties as $property) {
            if ($property->type == Type::Mixed) { continue; }

            $key = $this->getAccessKey($property);

            if (!Arr::has($result, $key)) { continue; }

            $rawValue = Arr::get($request, $key);

            try {
                $typedValue = match ($property->type) {
                    Type::Bool => $this->toBoolean($rawValue),
                    Type::Int => $this->toInteger($rawValue),
                    Type::Float => $this->toFloat($rawValue),
                    Type::Date => Carbon::parse($rawValue), // todo immutable
                    Type::String => $rawValue,
                    default => throw new RuntimeException("Unsupported type: {$property->type->name}"),
                };
            } catch (\Exception $e) {
                $typedValue = $rawValue;
            }

            Arr::set($result, $key, $typedValue);
        }

        return $result;
    }
}
