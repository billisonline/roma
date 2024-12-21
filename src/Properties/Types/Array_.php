<?php

namespace BYanelli\Roma\Properties\Types;

use BYanelli\Roma\Properties\Type;

final readonly class Array_ extends Type
{
    public function __construct(public Type $type) {}
}
