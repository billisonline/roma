<?php

namespace BYanelli\Roma\Attributes;

use Attribute;
use BYanelli\Roma\Source;
use Closure;
use Illuminate\Http\Request;

abstract readonly class BaseAccessor implements SourceAttribute, AccessorAttribute
{
    public function getSource(): Source
    {
        return Source::Object;
    }

    public function getAccessor(): Closure
    {
        return fn(Request $request) => $this->getFromRequest($request);
    }

    abstract protected function getFromRequest(Request $request): mixed;
}
