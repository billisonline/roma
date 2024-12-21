<?php

namespace BYanelli\Roma\Data\Types;

use BYanelli\Roma\Data\Property;
use BYanelli\Roma\Data\Type;

final readonly class Class_ extends Type
{
    /**
     * @param string $class
     * @param list<Property> $properties
     */
    public function __construct(
        public string $class,
        public array $properties,
    ) {}
}
