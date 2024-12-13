<?php

namespace BYanelli\Roma\Attributes;

use Attribute;
use BYanelli\Roma\Source;

#[Attribute(Attribute::TARGET_PROPERTY)]
readonly class Header implements SourceAttribute, NameAttribute
{
    public function __construct(public string $name) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function getSource(): Source
    {
        return Source::Header;
    }
}
