<?php

namespace BYanelli\Roma\Attributes\Accessors;

use Attribute;
use BYanelli\Roma\Attributes\Accessor;

#[Attribute(Attribute::TARGET_PARAMETER|Attribute::TARGET_PROPERTY)]
readonly class Method extends Accessor
{
    //
}
