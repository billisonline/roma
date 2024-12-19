<?php

namespace BYanelli\Roma;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Validation\Factory as ValidatorFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\DateFactory;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use ReflectionClass;
use RuntimeException;

readonly class RequestMapper
{
    public function __construct(
        private Container        $container,
        private DateFactory      $dateFactory,
        private ValidatorFactory $validatorFactory,
    ) {}

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

    private function getValue(array $requestData, Property $property): mixed
    {
        return Arr::get($requestData, $this->getAccessKey($property));
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
        $request = $this->container->make('request');
        $requestData = $this->flattenRequest($request);

        $class = new ReflectionClass($className);

        $constructorParameters = $this->getPropertiesFromConstructorParameters($class);
        $classProperties = $this->getPropertiesFromClassProperties($class);

        $allProperties = [...$constructorParameters, ...$classProperties];

        $validationRules = $this->getValidationRulesFromProperties($allProperties);

        $this->castRequestData($requestData, $allProperties);
        $this->addRequestObjectValuesToRequestData($request, $requestData, $allProperties);

        $this->validatorFactory
            ->make($requestData, $validationRules)
            ->validate();

        $constructorValues = [];

        foreach ($constructorParameters as $constructorParameter) {
            $constructorValues[] = $this->getValue($requestData, $constructorParameter);
        }

        $instance = new ($class->getName())(...$constructorValues);

        foreach ($classProperties as $classProperty) {
            $modifier = new \ReflectionProperty($instance, $classProperty->name);

            $modifier->setValue($instance, $this->getValue($requestData, $classProperty));
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
            Source::Object => 'request',
        };

        $key = ($property->source == Source::Header)
            ? Str::of($property->key)->upper()->replace('-', '_')->toString()
            : $property->key;

        return $source.'.'.$key;
    }

    /**
     * @param Property[] $properties
     * @return array
     */
    private function getValidationRulesFromProperties(array $properties): array
    {
        $rules = [];

        foreach ($properties as $property) {
            $propertyRules = $property->rules;

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
     * @return void
     */
    private function castRequestData(array &$request, array $properties): void
    {
        foreach ($properties as $property) {
            if ($property->source == Source::Object) { continue; }
            if ($property->type == Type::Mixed) { continue; }

            $key = $this->getAccessKey($property);

            if (!Arr::has($request, $key)) { continue; }

            $rawValue = Arr::get($request, $key);

            try {
                $typedValue = match ($property->type) {
                    Type::Bool => $this->toBoolean($rawValue),
                    Type::Int => $this->toInteger($rawValue),
                    Type::Float => $this->toFloat($rawValue),
                    Type::Date => $this->dateFactory->parse($rawValue),
                    Type::String => $rawValue,
                    default => throw new RuntimeException("Unsupported type: {$property->type->name}"),
                };
            } catch (\Exception $e) {
                $typedValue = $rawValue;
            }

            Arr::set($request, $key, $typedValue);
        }
    }

    /**
     * @param Request $request
     * @param array $requestData
     * @param Property[] $properties
     * @return void
     */
    private function addRequestObjectValuesToRequestData(Request $request, array &$requestData, array $properties): void
    {
        foreach ($properties as $property) {
            if ($property->source != Source::Object) { continue; }

            $key = $this->getAccessKey($property);
            $value = call_user_func($property->accessor, $request);

            Arr::set($requestData, $key, $value);
        }
    }
}
