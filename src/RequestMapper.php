<?php

namespace BYanelli\Roma;

use BYanelli\Roma\Data\ClassData;
use BYanelli\Roma\Data\TypeResolver;
use BYanelli\Roma\Validation\ValidationRules;
use Illuminate\Contracts\Validation\Factory as ValidatorFactory;
use Illuminate\Validation\ValidationException;
use ReflectionProperty;

readonly class RequestMapper implements Contracts\RequestMapper
{
    public function __construct(
        private Contracts\RequestResolver $requestResolver,
        private ValidatorFactory          $validatorFactory,
        private TypeResolver              $typeResolver = new TypeResolver,
    ) {}

    private function mapClassesForValues(array $values): array
    {
        return collect($values)
            ->map(fn($val) => $val instanceof ClassData
                ? $this->mapClass($val)
                : $val)
            ->all();
    }

    private function mapClass(ClassData $data): mixed
    {
        $className = $data->getClassName();
        $constructorValues = $this->mapClassesForValues($data->getConstructorValues());
        $classProperties = $this->mapClassesForValues($data->getClassProperties());

        $instance = new $className(...$constructorValues);

        foreach ($classProperties as $name => $value) {
            (new ReflectionProperty($className, $name))->setValue($instance, $value);
        }

        return $instance;
    }

    /**
     * @template T
     * @param class-string<T> $className
     * @return T
     * @throws \ReflectionException|ValidationException
     */
    public function mapRequest(string $className)
    {
        $request = $this->requestResolver->get();
        $class = $this->typeResolver->resolveClass($className);

        $data = new ClassData($request, $class);
        $rules = new ValidationRules($class->properties);

        // todo validate nested objects

        $this->validatorFactory
            ->make($data->toArray(), $rules->toArray())
            ->validate();

        return $this->mapClass($data);
    }
}
