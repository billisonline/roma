<?php

namespace BYanelli\Roma\Contracts;

use Illuminate\Http\Request;

interface RequestResolver
{
    public function get(): Request;
}
