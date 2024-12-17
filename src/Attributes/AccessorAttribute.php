<?php

namespace BYanelli\Roma\Attributes;

use Closure;

interface AccessorAttribute
{
    public function getAccessor(): Closure;
}
