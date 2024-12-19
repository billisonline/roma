<?php

namespace BYanelli\Roma\Attributes;

use Illuminate\Validation\Rule;

interface RulesAttribute
{
    public function getRules(): array;
}
