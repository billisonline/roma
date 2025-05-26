<?php

namespace BYanelli\Roma\Request\Data;

enum Role
{
    case Constructor;
    case Property;
    case ValidationOnly;
}
