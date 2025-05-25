<?php

namespace BYanelli\Roma\Attributes\Accessors;

use Attribute;
use BYanelli\Roma\Attributes\Accessor;
use BYanelli\Roma\Data\Type;
use BYanelli\Roma\Data\Types\String_;

#[Attribute(Attribute::TARGET_PARAMETER|Attribute::TARGET_PROPERTY)]
readonly class Method extends Accessor
{
    public function getType(): Type
    {
        return new String_();
    }
}
