<?php

namespace BYanelli\Roma\Request\Attributes;

use Closure;

interface AccessorAttribute
{
    public function getAccessor(): Closure;
}
