<?php

namespace BYanelli\Roma\Attributes;

use Attribute;
use BYanelli\Roma\Properties\Source;

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
        return Source::Header;
    }
}
