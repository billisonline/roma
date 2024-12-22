<?php

namespace BYanelli\Roma\Data\Sources;

use BYanelli\Roma\Data\Source;

final readonly class Object_ extends Source
{
    public function __construct()
    {
        parent::__construct();
    }

    public function getOwnKey(): string
    {
        return 'request';
    }
}

