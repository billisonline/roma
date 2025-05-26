<?php

namespace BYanelli\Roma\Request\Data;

readonly abstract class Source
{
    public function __construct(public ?Source $parent = null) {}

    abstract public function getOwnKey(): string;

    public function getKey(): string
    {
        $parentKey = $this->parent?->getKey() ?? '';

        return empty($parentKey)
            ? $this->getOwnKey()
            : "$parentKey.{$this->getOwnKey()}";
    }
}
