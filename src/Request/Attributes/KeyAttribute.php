<?php

namespace BYanelli\Roma\Request\Attributes;

use BYanelli\Roma\Request\Data\Type;

interface KeyAttribute
{
    public function getKey(): string;

    public function getFullKey(): string;

    public function getType(): Type;
}
