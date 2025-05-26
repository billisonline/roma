<?php

namespace BYanelli\Roma\Request\Data\Sources;

use BYanelli\Roma\Request\Data\Source;

final readonly class Property extends Source
{
    public function __construct(Source $parent, private string $key)
    {
        parent::__construct($parent);
    }

    public function getOwnKey(): string
    {
        return $this->key;
    }
}
