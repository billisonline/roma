<?php

namespace BYanelli\Roma\Properties\Types;

use BYanelli\Roma\Properties\Type;

final readonly class Enum extends Type
{
    public function __construct(public string $class) {}
}
