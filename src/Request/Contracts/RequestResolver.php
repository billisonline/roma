<?php

namespace BYanelli\Roma\Request\Contracts;

use Illuminate\Http\Request;

interface RequestResolver
{
    public function get(): Request;
}
