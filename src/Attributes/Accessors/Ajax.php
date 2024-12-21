<?php

namespace BYanelli\Roma\Attributes\Accessors;

use Attribute;
use BYanelli\Roma\Attributes\Accessor;

#[Attribute(Attribute::TARGET_PARAMETER|Attribute::TARGET_PROPERTY)]
readonly class Ajax extends Accessor
{
    public function __construct(private ?bool $allowed=null) {}

    public function getRules(): array
    {
        return match ($this->allowed) {
            true => ['accepted'],
            false => ['declined'],
            null => [],
        };
    }
}
