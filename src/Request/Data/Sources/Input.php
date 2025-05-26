<?php

namespace BYanelli\Roma\Request\Data\Sources;

use BYanelli\Roma\Request\Data\Source;

final readonly class Input extends Source
{
    public function __construct()
    {
        parent::__construct();
    }

    public function getOwnKey(): string
    {
        return 'input';
    }
}
