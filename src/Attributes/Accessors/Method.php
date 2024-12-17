<?php

namespace BYanelli\Roma\Attributes\Accessors;

use Attribute;
use BYanelli\Roma\Attributes\BaseAccessor;
use Illuminate\Http\Request;

#[Attribute(Attribute::TARGET_PARAMETER|Attribute::TARGET_PROPERTY)]
readonly class Method extends BaseAccessor
{
    protected function getFromRequest(Request $request): string
    {
        return $request->method();
    }
}
