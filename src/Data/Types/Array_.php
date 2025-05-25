<?php

namespace BYanelli\Roma\Data\Types;

use BYanelli\Roma\Data\Type;

final readonly class Array_ extends Type
{
    public function __construct(public Type $memberType) {}
}
