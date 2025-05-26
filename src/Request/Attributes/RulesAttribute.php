<?php

namespace BYanelli\Roma\Request\Attributes;

interface RulesAttribute
{
    public function getRules(AttributeTarget $target): array;
}
