<?php

namespace BYanelli\Roma\Attributes;

use Attribute;
use BYanelli\Roma\Data\Source;
use BYanelli\Roma\Data\Sources;
use BYanelli\Roma\Data\Type;
use BYanelli\Roma\Data\Types\String_;
use Illuminate\Support\Str;

#[Attribute(Attribute::TARGET_PARAMETER|Attribute::TARGET_PROPERTY)]
readonly class Header implements SourceAttribute, KeyAttribute, RulesAttribute
{
    public function __construct(public string $name) {}

    public function getKey(): string
    {
        // We need to combine both to turn e.g. "Content-Type" into "content_type"
        return Str::snake(Str::camel($this->name));
    }

    public function getSource(): Source
    {
        return new Sources\Header;
    }

    public function getRules(AttributeTarget $target): array
    {
        return [];
    }

    public function getType(): Type
    {
        return new String_();
    }

    public function getFullKey(): string
    {
        return "{$this->getSource()->getKey()}.{$this->getKey()}";
    }
}
