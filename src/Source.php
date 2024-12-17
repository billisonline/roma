<?php

namespace BYanelli\Roma;

enum Source
{
    case Query;
    case Body;
    case Input;
    case Header;
    case File;
}
