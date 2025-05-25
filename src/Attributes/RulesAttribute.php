<?php

namespace BYanelli\Roma\Attributes;

interface RulesAttribute
{
    public function getRules(AttributeTarget $target): array;
}
