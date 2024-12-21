<?php

namespace BYanelli\Roma\Properties;

use ReflectionNamedType;
use RuntimeException;

readonly abstract class Type
{
    public static function fromReflectionType(?\ReflectionType $type): Type
    {
        return match (true) {
            $type == null => new Types\Mixed_,
            $type instanceof ReflectionNamedType => match ($type->getName()) {
                'string' => new Types\String_,
                'int' => new Types\Integer,
                'bool' => new Types\Boolean,
                'float' => new Types\Float_,
                'array' => new Types\Array_(),
                \DateTimeInterface::class => new Types\Date,
                default => match (true) {
                    enum_exists($type->getName()) => new Types\Enum($type->getName()),
                    default => throw new RuntimeException('Unsupported type'),
                },
            },
            default => throw new RuntimeException('Unsupported type'),
        };
    }
}
