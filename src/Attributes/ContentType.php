<?php

namespace BYanelli\Roma\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
readonly class ContentType extends Header
{
    public function __construct()
    {
        parent::__construct('Content-Type');
    }
}
