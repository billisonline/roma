<?php

namespace BYanelli\Roma\Properties;

use BYanelli\Roma\Properties\Types\Boolean;
use BYanelli\Roma\Properties\Types\Date;
use BYanelli\Roma\Properties\Types\Enum;
use BYanelli\Roma\Properties\Types\Float_;
use BYanelli\Roma\Properties\Types\Integer;
use BYanelli\Roma\Properties\Types\Mixed_;
use BYanelli\Roma\Properties\Types\String_;
use ReflectionNamedType;
use RuntimeException;

readonly abstract class Type
{
    public static function fromReflectionType(?\ReflectionType $type): Type
    {
        return match (true) {
            $type == null => new Mixed_,
            $type instanceof ReflectionNamedType => match ($type->getName()) {
                'string' => new String_,
                'int' => new Integer,
                'bool' => new Boolean,
                'float' => new Float_,
                \DateTimeInterface::class => new Date,
                default => match (true) {
                    enum_exists($type->getName()) => new Enum($type->getName()),
                    default => throw new RuntimeException('Unsupported type'),
                },
            },
            default => throw new RuntimeException('Unsupported type'),
        };
    }
}
