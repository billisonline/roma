<?php

namespace BYanelli\Roma\Request\Data\Types;

use BYanelli\Roma\Request\Data\Type;

final readonly class Array_ extends Type
{
    public function __construct(public Type $memberType) {}
}
