<?php

namespace BYanelli\Roma;

use ReflectionParameter;

readonly class Property
{
    public static function fromReflectionParameter(ReflectionParameter $parameter): self
    {
        $key = $parameter->getName();
        $type = Type::fromReflectionType($parameter->getType());
        $default = $parameter->isOptional() ? $parameter->getDefaultValue() : null;

        return new Property(
            name: $key,
            type: $type,
            default: $default,
            source: Source::QueryOrBody, // todo
        );
    }

    public static function fromReflectionProperty(\ReflectionProperty $property): self
    {
        $key = $property->getName();
        $type = Type::fromReflectionType($property->getType());
        $default = $property->hasDefaultValue() ? $property->getDefaultValue() : null;

        return new Property(
            name: $key,
            type: $type,
            default: $default,
            source: Source::QueryOrBody, // todo
        );
    }

    public function __construct(
        public string $name,
        public Type $type,
        public mixed $default,
        public Source $source,
    ) {}
}
