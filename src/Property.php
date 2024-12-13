<?php

namespace BYanelli\Roma;

use BYanelli\Roma\Attributes\NameAttribute;
use BYanelli\Roma\Attributes\SourceAttribute;
use ReflectionParameter;
use ReflectionProperty;

readonly class Property
{
    /**
     * @param \ReflectionAttribute[] $attributes
     * @return Source
     */
    private static function getSourceFromAttributes(array $attributes): Source
    {
        foreach ($attributes as $attribute) {
            $instance = $attribute->newInstance();

            if ($instance instanceof SourceAttribute) {
                return $instance->getSource();
            }
        }

        return Source::QueryOrBody;
    }

    /**
     * @param \ReflectionAttribute[] $attributes
     * @return string|null
     */
    private static function getNameFromAttributes(array $attributes): ?string
    {
        foreach ($attributes as $attribute) {
            $instance = $attribute->newInstance();

            if ($instance instanceof NameAttribute) {
                return $instance->getName();
            }
        }

        return null;
    }

    public static function fromReflectionObject(ReflectionParameter|ReflectionProperty $obj): self
    {
        $attributes = $obj->getAttributes();

        $name = self::getNameFromAttributes($attributes) ?: $obj->getName();
        $type = Type::fromReflectionType($obj->getType());
        $default = $obj instanceof ReflectionParameter
            ? ($obj->isOptional() ? $obj->getDefaultValue() : null)
            : ($obj->hasDefaultValue() ? $obj->getDefaultValue() : null);
        $source = self::getSourceFromAttributes($attributes);

        return new Property(
            name: $name,
            type: $type,
            default: $default,
            source: $source,
        );
    }

    public function __construct(
        public string $name,
        public Type $type,
        public mixed $default,
        public Source $source,
    ) {}
}
