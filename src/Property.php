<?php

namespace BYanelli\Roma;

use BYanelli\Roma\Attributes\AccessorAttribute;
use BYanelli\Roma\Attributes\KeyAttribute;
use BYanelli\Roma\Attributes\SourceAttribute;
use Closure;
use Illuminate\Http\Resources\MissingValue;
use ReflectionAttribute;
use ReflectionParameter;
use ReflectionProperty;

readonly class Property
{
    private static function getSourceFromAttributes(array $attributes): Source
    {
        return collect($attributes)
            ->whereInstanceOf(SourceAttribute::class)
            ->map(fn (SourceAttribute $attr) => $attr->getSource())
            ->first() ?: Source::Input;
    }

    private static function getKeyFromAttributes(array $attributes): ?string
    {
        return collect($attributes)
            ->whereInstanceOf(KeyAttribute::class)
            ->map(fn (KeyAttribute $attr) => $attr->getKey())
            ->first();
    }

    private static function getAccessorFromAttributes(array $attributes): ?Closure
    {
        return collect($attributes)
            ->whereInstanceOf(AccessorAttribute::class)
            ->map(fn (AccessorAttribute $attr) => $attr->getAccessor())
            ->first();
    }

    private static function getDefault(ReflectionParameter|ReflectionProperty $obj): mixed
    {
        return $obj instanceof ReflectionParameter
            ? ($obj->isOptional() ? $obj->getDefaultValue() : new MissingValue())
            : ($obj->hasDefaultValue() ? $obj->getDefaultValue() : new MissingValue());
    }

    public static function fromReflectionObject(ReflectionParameter|ReflectionProperty $obj): self
    {
        $attributes = collect($obj->getAttributes())
            ->map(fn(ReflectionAttribute $attr) => $attr->newInstance())
            ->all();

        return new Property(
            name: $obj->getName(),
            key: self::getKeyFromAttributes($attributes) ?: $obj->getName(),
            type: Type::fromReflectionType($obj->getType()),
            default: self::getDefault($obj),
            source: self::getSourceFromAttributes($attributes),
            accessor: self::getAccessorFromAttributes($attributes) ?: fn() => null,
        );
    }

    public bool $isRequired;

    public function __construct(
        public string $name,
        public string $key,
        public Type   $type,
        public mixed  $default,
        public Source $source,
        public Closure $accessor,
    ) {
        $this->isRequired = $default instanceof MissingValue;
    }
}
