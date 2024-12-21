<?php

namespace BYanelli\Roma\Properties;

use BYanelli\Roma\Attributes\AccessorAttribute;
use BYanelli\Roma\Attributes\KeyAttribute;
use BYanelli\Roma\Attributes\RulesAttribute;
use BYanelli\Roma\Attributes\SourceAttribute;
use Closure;
use Illuminate\Http\Resources\MissingValue;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionParameter;
use ReflectionProperty;
use RuntimeException;

class PropertyFinder
{
    public function __construct(private TypeResolver $typeResolver = new TypeResolver) {}

    private function getSourceFromAttributes(array $attributes): Source
    {
        return collect($attributes)
            ->whereInstanceOf(SourceAttribute::class)
            ->map(fn (SourceAttribute $attr) => $attr->getSource())
            ->first() ?: Source::Input;
    }

    private function getKeyFromAttributes(array $attributes): ?string
    {
        return collect($attributes)
            ->whereInstanceOf(KeyAttribute::class)
            ->map(fn (KeyAttribute $attr) => $attr->getKey())
            ->first();
    }

    private function getAccessorFromAttributes(array $attributes): ?Closure
    {
        return collect($attributes)
            ->whereInstanceOf(AccessorAttribute::class)
            ->map(fn (AccessorAttribute $attr) => $attr->getAccessor())
            ->first();
    }

    private function getDefault(ReflectionParameter|ReflectionProperty $obj): mixed
    {
        return $obj instanceof ReflectionParameter
            ? ($obj->isOptional() ? $obj->getDefaultValue() : new MissingValue())
            : ($obj->hasDefaultValue() ? $obj->getDefaultValue() : new MissingValue());
    }


    private function getRules(array $attributes): array
    {
        return collect($attributes)
            ->whereInstanceOf(RulesAttribute::class)
            ->flatMap(fn (RulesAttribute $attr) => $attr->getRules())
            ->all();
    }

    private function getFromReflectionObject(ReflectionParameter|ReflectionProperty $obj): Property
    {
        $attributes = collect($obj->getAttributes())
            ->map(fn(ReflectionAttribute $attr) => $attr->newInstance())
            ->all();

        return new Property(
            name: $obj->getName(),
            key: $this->getKeyFromAttributes($attributes) ?: $obj->getName(),
            type: $this->typeResolver->getTypeFromReflectionObject($obj),
            role: $this->getRole($obj),
            default: $this->getDefault($obj),
            source: $this->getSourceFromAttributes($attributes),
            accessor: $this->getAccessorFromAttributes($attributes) ?: fn() => null,
            rules: $this->getRules($attributes),
        );
    }


    /**
     * @param ReflectionClass $class
     * @return Property[]
     */
    private function getFromConstructorParameters(ReflectionClass $class): array
    {
        $result = [];

        if (($constructor = $class->getConstructor()) != null) {
            $constructorParameters = $constructor->getParameters();

            foreach ($constructorParameters as $constructorParameter) {
                $result[] = $this->getFromReflectionObject($constructorParameter);
            }
        }

        return $result;
    }


    /**
     * @param ReflectionClass $class
     * @return Property[]
     */
    private function getFromClassProperties(ReflectionClass $class): array
    {
        $result = [];

        $classProperties = $class->getProperties(\ReflectionProperty::IS_PUBLIC);

        foreach ($classProperties as $classProperty) {
            if ($classProperty->isStatic() || $classProperty->isPromoted()) { continue; }

            $result[] = $this->getFromReflectionObject($classProperty);
        }

        return $result;
    }

    private function getRole(ReflectionParameter|ReflectionProperty $obj): Role
    {
        return match (true) {
            ($obj instanceof ReflectionParameter) => Role::Constructor,
            ($obj instanceof ReflectionProperty) => Role::Property,
            default => throw new RuntimeException('Unexpected object_type: '.get_class($obj)),
        };
    }

    public function getAllFromClass(string|ReflectionClass $class): array
    {
        if (is_string($class)) {
            $class = new ReflectionClass($class);
        }

        return [
            ...$this->getFromConstructorParameters($class),
            ...$this->getFromClassProperties($class),
        ];
    }
}
