<?php

namespace BYanelli\Roma\Attributes;

use BYanelli\Roma\Data\Source;
use BYanelli\Roma\Data\Sources\RequestObject_;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

readonly abstract class Accessor implements SourceAttribute, KeyAttribute, AccessorAttribute, RulesAttribute
{
    public function getKey(): string
    {
        return Str::camel(class_basename($this));
    }

    public function getFullKey(): string
    {
        return $this->getKey();
    }

    public function getSource(): Source
    {
        return new RequestObject_;
    }

    public function getAccessor(): Closure
    {
        return fn(Request $request) => $this->getFromRequest($request);
    }

    public function getRules(AttributeTarget $target): array
    {
        return [];
    }

    protected function getFromRequest(Request $request): mixed
    {
        return $request->{$this->getKey()}();
    }
}
