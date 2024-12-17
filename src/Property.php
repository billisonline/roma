<?php

namespace BYanelli\Roma;

use BYanelli\Roma\Attributes\NameAttribute;
use BYanelli\Roma\Attributes\SourceAttribute;
use Illuminate\Http\Resources\MissingValue;
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

        return Source::Input;
    }

    /**
     * @param \ReflectionAttribute[] $attributes
     * @return string|null
     */
    private static function getKeyFromAttributes(array $attributes): ?string
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

        $name = $obj->getName();
        $key = self::getKeyFromAttributes($attributes) ?: $obj->getName();
        $type = Type::fromReflectionType($obj->getType());
        $default = $obj instanceof ReflectionParameter
            ? ($obj->isOptional() ? $obj->getDefaultValue() : new MissingValue())
            : ($obj->hasDefaultValue() ? $obj->getDefaultValue() : new MissingValue());
        $source = self::getSourceFromAttributes($attributes);

        return new Property(
            name: $name,
            key: $key,
            type: $type,
            default: $default,
            source: $source,
        );
    }

    public bool $isRequired;

    public function __construct(
        public string $name,
        public string $key,
        public Type   $type,
        public mixed  $default,
        public Source $source,
    ) {
        $this->isRequired = $default instanceof MissingValue;
    }
}
