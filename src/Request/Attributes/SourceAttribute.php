<?php

namespace BYanelli\Roma\Request\Attributes;

use BYanelli\Roma\Request\Data\Source;

interface SourceAttribute
{
    public function getSource(): Source;
}
