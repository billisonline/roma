<?php

namespace BYanelli\Roma\Request\Data;

use BYanelli\Roma\Request\Attributes\AccessorAttribute;
use BYanelli\Roma\Request\Attributes\AttributeTarget;
use BYanelli\Roma\Request\Attributes\KeyAttribute;
use BYanelli\Roma\Request\Attributes\RulesAttribute;
use BYanelli\Roma\Request\Attributes\SourceAttribute;
use BYanelli\Roma\Request\Data\Sources\Input;
use BYanelli\Roma\Request\Data\Sources\Property as PropertySource;
use BYanelli\Roma\Request\Data\Types\Class_;
use BYanelli\Roma\Request\Data\Types\Mixed_;
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

readonly class ClassDefinitionBuilder
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


    private function getRulesForParameterOrProperty(array $attributes): array
    {
        return collect($attributes)
            ->whereInstanceOf(RulesAttribute::class)
            ->flatMap(function (RulesAttribute $attr) {
                return $attr->getRules(AttributeTarget::Property);
            })
            ->all();
    }

    private function prependParentSource(Source $source): Source
    {
        return ($source instanceof PropertySource && $this->parentSource != null)
            ? new PropertySource($this->parentSource, $source->getOwnKey())
            : $source;
    }

    /**
     * @param list<ReflectionAttribute> $attributes
     * @return array
     */
    private function getAttributeInstances(array $attributes): array {
        return collect($attributes)
            ->map(fn(ReflectionAttribute $attr) => $attr->newInstance())
            ->all();
    }

    private function getFromReflectionParameterOrProperty(ReflectionParameter|ReflectionProperty $obj): Property
    {
        $attributes = $this->getAttributeInstances($obj->getAttributes());

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
            rules: $this->getRulesForParameterOrProperty($attributes),
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
                $result[] = $this->getFromReflectionParameterOrProperty($constructorParameter);
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

            $result[] = $this->getFromReflectionParameterOrProperty($classProperty);
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
    private function getConstructorParameterAndClassProperties(ReflectionClass $class): array
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
                class_exists($name) => (new ClassDefinitionBuilder(new PropertySource($parent, $key)))->buildClassDefinition($name),
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

    public function buildClassDefinition(string|ReflectionClass $class): Class_
    {
        if (is_string($class)) {
            $class = new ReflectionClass($class);
        }

        return new Types\Class_(
            class: $class->getName(),
            properties: [
                ...$this->getConstructorParameterAndClassProperties($class),
                ...$this->getValidationOnlyProperties($class),
            ],
        );
    }

    private function getValidationOnlyProperties(ReflectionClass $class): array
    {
        $attributes = $this->getAttributeInstances($class->getAttributes());

        return collect($attributes)
            ->whereInstanceOf([
                KeyAttribute::class,
                RulesAttribute::class,
                SourceAttribute::class,
            ])
            ->map(function (KeyAttribute&RulesAttribute&SourceAttribute $attr) {
                return new Property(
                    name: $attr->getKey(),
                    key: $attr->getKey(),
                    type: $attr->getType(),
                    role: Role::ValidationOnly,
                    default: new MissingValue(),
                    parent: $attr->getSource(),
                    accessor: ($attr instanceof AccessorAttribute)
                        ? $attr->getAccessor()
                        : fn() => null,
                    rules: $attr->getRules(AttributeTarget::Class_),
                );
            })
            ->all();
    }
}
