<?php

namespace BYanelli\Roma\Data\Types;

use BYanelli\Roma\Data\Type;

final readonly class Enum extends Type
{
    public function __construct(public string $class) {}
}
