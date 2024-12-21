<?php

namespace BYanelli\Roma\Data;

readonly abstract class Source
{
    public function __construct(private ?Source $parent = null) {}

    abstract protected function getOwnKey(): string;

    public function getKey(): string
    {
        $parentKey = $this->parent?->getKey() ?? '';

        return empty($parentKey)
            ? $this->getOwnKey()
            : "$parentKey.{$this->getOwnKey()}";
    }
}
