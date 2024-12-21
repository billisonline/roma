<?php

namespace BYanelli\Roma\Properties;

enum Source
{
    case Object;
    case Query;
    case Body;
    case Input;
    case Header;
    case File;

    public function getKey(): string
    {
        return match ($this) {
            self::Object => 'request',
            default => strtolower($this->name),
        };
    }
}
