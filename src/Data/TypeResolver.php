<?php

namespace BYanelli\Roma\Data;

use BYanelli\Roma\Attributes\AccessorAttribute;
use BYanelli\Roma\Attributes\KeyAttribute;
use BYanelli\Roma\Attributes\RulesAttribute;
use BYanelli\Roma\Attributes\SourceAttribute;
use BYanelli\Roma\Data\Sources\Input;
use BYanelli\Roma\Data\Sources\Property as PropertySource;
use BYanelli\Roma\Data\Types\Class_;
use BYanelli\Roma\Data\Types\Mixed_;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\Resources\MissingValue;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;
use RuntimeException;

readonly class TypeResolver
{
    public function __construct(
        private ?Source $parentSource = null,
        private PhpDocTypeParser $phpDocTypeParser = new PhpDocTypeParser,
    ) {}

    private function getSourceFromAttributes(array $attributes): Source
    {
        return collect($attributes)
            ->whereInstanceOf(SourceAttribute::class)
            ->map(fn (SourceAttribute $attr) => $attr->getSource())
            ->first() ?: new Input;
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

    private function prependParentSource(Source $source): Source
    {
        return ($source instanceof PropertySource && $this->parentSource != null)
            ? new PropertySource($this->parentSource, $source->getOwnKey())
            : $source;
    }

    private function getFromReflectionObject(ReflectionParameter|ReflectionProperty $obj): Property
    {
        $attributes = collect($obj->getAttributes())
            ->map(fn(ReflectionAttribute $attr) => $attr->newInstance())
            ->all();

        $parent = $this->parentSource ?: $this->getSourceFromAttributes($attributes);
        $key = $this->getKeyFromAttributes($attributes) ?: $obj->getName();

        return new Property(
            name: $obj->getName(),
            key: $key,
            type: $this->getTypeFromReflectionObject($parent, $key, $obj),
            role: $this->getRole($obj),
            default: $this->getDefault($obj),
            parent: $parent,
            accessor: $this->getAccessorFromAttributes($attributes) ?: fn() => null,
            rules: $this->getRules($attributes),
        );
    }


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
                $result[] = $this->getFromReflectionObject($constructorParameter);
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

    /**
     * @param ReflectionClass $class
     * @return list<Property>
     */
    private function getAllPropertiesFromClass(ReflectionClass $class): array
    {
        return [
            ...$this->getPropertiesFromConstructorParameters($class),
            ...$this->getPropertiesFromClassProperties($class),
        ];
    }

    private function getTypeByName(
        Source $parent,
        string $key,
        ReflectionParameter|ReflectionProperty $obj,
        string $name,
    ): Type {
        return match ($name) {
            'string' => new Types\String_,
            'int' => new Types\Integer,
            'bool' => new Types\Boolean,
            'float' => new Types\Float_,
            'array' => new Types\Array_($this->getTypeByName($parent, $key, $obj, $this->phpDocTypeParser->getArrayElementTypeName($obj))),
            \DateTimeInterface::class, Carbon::class, CarbonImmutable::class => new Types\Date,
            default => match (true) {
                enum_exists($name) => new Types\Enum($name),
                class_exists($name) => (new TypeResolver(new PropertySource($parent, $key)))->resolveClass($name),
                default => throw new RuntimeException("Unsupported type $name"),
            },
        };
    }

    public function getTypeFromReflectionObject(
        Source $parent,
        string $key,
        ReflectionParameter|ReflectionProperty $obj,
    ): Type {
        return ($obj->getType() instanceof ReflectionNamedType)
            ? $this->getTypeByName($parent, $key, $obj, $obj->getType()->getName())
            : new Mixed_;
    }

    public function resolveClass(string|ReflectionClass $class): Class_
    {
        if (is_string($class)) {
            $class = new ReflectionClass($class);
        }

        return new Types\Class_($class->getName(), $this->getAllPropertiesFromClass($class));
    }
}
