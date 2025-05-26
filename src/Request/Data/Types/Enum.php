<?php

namespace BYanelli\Roma\Request\Data\Types;

use BYanelli\Roma\Request\Data\Type;

final readonly class Enum extends Type
{
    public function __construct(public string $class) {}
}
