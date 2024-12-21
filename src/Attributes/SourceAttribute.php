<?php

namespace BYanelli\Roma\Attributes;

use BYanelli\Roma\Data\Source;

interface SourceAttribute
{
    public function getSource(): Source;
}
