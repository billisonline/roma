<?php

namespace BYanelli\Roma\Request;

use BYanelli\Roma\Request\Data\ClassDefinitionBuilder;
use BYanelli\Roma\Request\Data\ClassRequestMapping;
use Illuminate\Contracts\Validation\Factory as ValidatorFactory;
use Illuminate\Validation\ValidationException;
use ReflectionProperty;

readonly class RequestMapper implements Contracts\RequestMapper
{
    public function __construct(
        private Contracts\RequestResolver $requestResolver,
        private ValidatorFactory                                $validatorFactory,
        private ClassDefinitionBuilder                          $classDefinitionBuilder = new ClassDefinitionBuilder,
    ) {}

    private function mapValuesToNestedClasses(array $values): array
    {
        return collect($values)
            ->map(fn($v) => $v instanceof ClassRequestMapping ? $this->mapClass($v) : $v)
            ->all();
    }

    /**
     * @throws \ReflectionException
     */
    private function mapClass(ClassRequestMapping $mapping): mixed
    {
        $className = $mapping->getClassName();
        $constructorValues = $this->mapValuesToNestedClasses($mapping->getConstructorValuesArray());
        $classProperties = $this->mapValuesToNestedClasses($mapping->getClassPropertiesMap());

        $instance = new $className(...$constructorValues);

        foreach ($classProperties as $name => $value) {
            new ReflectionProperty($className, $name)->setValue($instance, $value);
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
        $class = $this->classDefinitionBuilder->buildClassDefinition($className);
        $request = $this->requestResolver->get();

        $mapping = new ClassRequestMapping($class, $request);

        // todo validate nested objects
        $this->validatorFactory
            ->make($mapping->data(), $mapping->rules())
            ->validate();

        return $this->mapClass($mapping);
    }
}
