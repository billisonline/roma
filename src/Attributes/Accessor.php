<?php

namespace BYanelli\Roma\Attributes;

use BYanelli\Roma\Source;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

abstract readonly class Accessor implements SourceAttribute, KeyAttribute, AccessorAttribute, RulesAttribute
{
    public function getKey(): string
    {
        return Str::camel(class_basename($this));
    }

    public function getSource(): Source
    {
        return Source::Object;
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
