<?php

namespace BYanelli\Roma\Request\Data\Sources;

use BYanelli\Roma\Request\Data\Source;

final readonly class RequestObject_ extends Source
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
