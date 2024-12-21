<?php

namespace BYanelli\Roma\Data\Sources;

use BYanelli\Roma\Data\Source;

final readonly class Header extends Source
{
    public function __construct()
    {
        parent::__construct();
    }

    protected function getOwnKey(): string
    {
        return 'header';
    }
}
