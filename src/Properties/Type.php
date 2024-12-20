<?php

namespace BYanelli\Roma\Properties;

use ReflectionNamedType;
use RuntimeException;

enum Type
{
    case String;
    case Int;
    case Bool;
    case Float;
    case Date;
    case Mixed;

    public static function fromReflectionType(\ReflectionType|null $type): self {
        return match (true) {
            $type == null => Type::Mixed,
            $type instanceof ReflectionNamedType => match ($type->getName()) {
                'string' => Type::String,
                'int' => Type::Int,
                'bool' => Type::Bool,
                'float' => Type::Float,
                \DateTimeInterface::class => Type::Date,
                default => throw new RuntimeException("Unsupported named type: {$type->getName()}"),
            },
            default => throw new RuntimeException('Unsupported type'),
        };
    }
}
