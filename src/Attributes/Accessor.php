<?php

namespace BYanelli\Roma\Attributes;

use BYanelli\Roma\Data\Source;
use BYanelli\Roma\Data\Sources\Object_;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

readonly abstract class Accessor implements SourceAttribute, KeyAttribute, AccessorAttribute, RulesAttribute
{
    public function getKey(): string
    {
        return Str::camel(class_basename($this));
    }

    public function getSource(): Source
    {
        return new Object_;
    }

    public function getAccessor(): Closure
    {
        return fn(Request $request) => $this->getFromRequest($request);
    }

    public function getRules(): array
    {
        return [];
    }

    protected function getFromRequest(Request $request): mixed
    {
        return $request->{$this->getKey()}();
    }
}
