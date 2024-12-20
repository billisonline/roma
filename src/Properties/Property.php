<?php

namespace BYanelli\Roma\Properties;

use Closure;
use Illuminate\Http\Resources\MissingValue;

readonly class Property
{
    public bool $isRequired;

    public function __construct(
        public string $name,
        public string $key,
        public Type   $type,
        public Role   $role,
        public mixed  $default,
        public Source $source,
        public Closure $accessor,
        public array $rules,
    ) {
        $this->isRequired = $default instanceof MissingValue;
    }
}
