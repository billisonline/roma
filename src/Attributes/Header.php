<?php

namespace BYanelli\Roma\Attributes;

use Attribute;
use BYanelli\Roma\Data\Source;
use BYanelli\Roma\Data\Sources;

#[Attribute(Attribute::TARGET_PARAMETER|Attribute::TARGET_PROPERTY)]
readonly class Header implements SourceAttribute, KeyAttribute
{
    public function __construct(public string $name) {}

    public function getKey(): string
    {
        return $this->name;
    }

    public function getSource(): Source
    {
        return new Sources\Header;
    }
}
