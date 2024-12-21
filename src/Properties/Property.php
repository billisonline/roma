<?php

namespace BYanelli\Roma\Properties;

use Closure;
use Illuminate\Http\Resources\MissingValue;
use Illuminate\Support\Str;

class Property
{
    public readonly bool $isRequired;

    public function __construct(
        public readonly string  $name,
        public readonly string  $key,
        public readonly Type    $type,
        public readonly Role    $role,
        public readonly mixed   $default,
        public readonly Source  $source,
        public readonly Closure $accessor,
        public readonly array   $rules,
    ) {
        $this->isRequired = $default instanceof MissingValue;
    }

    private function getNormalizedKey(): string
    {
        return ($this->source == Source::Header)
            ? Str::of($this->key)->upper()->replace('-', '_')->toString()
            : $this->key;
    }

    public function getFullKey(): string
    {
        return "{$this->source->getKey()}.{$this->getNormalizedKey()}";
    }
}
