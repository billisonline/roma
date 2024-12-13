<?php

namespace BYanelli\Roma\Attributes;

use BYanelli\Roma\Source;

interface SourceAttribute
{
    public function getSource(): Source;
}
