<?php

namespace BYanelli\Roma\Attributes\Headers;

use Attribute;
use BYanelli\Roma\Attributes\Header;

#[Attribute(Attribute::TARGET_PARAMETER|Attribute::TARGET_PROPERTY)]
readonly class ContentType extends Header
{
    public function __construct()
    {
        parent::__construct('Content-Type');
    }
}
