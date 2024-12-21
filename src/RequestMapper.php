<?php

namespace BYanelli\Roma;

use BYanelli\Roma\Properties\Property;
use BYanelli\Roma\Properties\PropertyFinder;
use BYanelli\Roma\Properties\Role;
use Illuminate\Contracts\Validation\Factory as ValidatorFactory;
use Illuminate\Validation\ValidationException;
use ReflectionProperty;

readonly class RequestMapper implements Contracts\RequestMapper
{
    public function __construct(
        private Contracts\RequestResolver $requestResolver,
        private ValidatorFactory          $validatorFactory,
        private PropertyFinder            $propertyFinder = new PropertyFinder,
    ) {}

    /**
     * @param RequestData $data
     * @param list<Property> $properties
     * @return list<mixed>
     */
    private function getConstructorValues(RequestData $data, array $properties): array
    {
        return collect($properties)
            ->filter(fn(Property $p) => $p->role == Role::Constructor)
            ->map($data->getValue(...))
            ->all();
    }

    /**
     * @param RequestData $data
     * @param list<Property> $properties
     * @return array<string, mixed>
     */
    public function getClassProperties(RequestData $data, array $properties): array
    {
        return collect($properties)
            ->filter(fn(Property $p) => $p->role == Role::Property)
            ->mapWithKeys(fn(Property $p) => [$p->name => $data->getValue($p)])
            ->all();
    }

    /**
     * @template T
     * @param class-string<T> $className
     * @return T
     * @throws \ReflectionException|ValidationException
     */
    public function mapRequest(string $className)
    {
        $properties = $this->propertyFinder->getAllFromClass($className);

        $data = new RequestData($this->requestResolver->get(), $properties);
        $rules = new RequestValidationRules($properties);

        $this->validatorFactory
            ->make($data->toArray(), $rules->toArray())
            ->validate();

        $constructorValues = $this->getConstructorValues($data, $properties);
        $classProperties = $this->getClassProperties($data, $properties);

        $instance = new $className(...$constructorValues);

        foreach ($classProperties as $name => $value) {
            new ReflectionProperty($className, $name)->setValue($instance, $value);
        }

        return $instance;
    }
}
