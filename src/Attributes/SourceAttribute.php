<?php

namespace BYanelli\Roma\Attributes;

use BYanelli\Roma\Properties\Source;

interface SourceAttribute
{
    public function getSource(): Source;
}
