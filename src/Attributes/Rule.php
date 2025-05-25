<?php

namespace BYanelli\Roma\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY|Attribute::TARGET_PARAMETER)]
class Rule implements RulesAttribute
{
    public function __construct(private mixed $rule) {}

    public function getRules(AttributeTarget $target): array
    {
        return [$this->rule];
    }
}
