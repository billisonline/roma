<?php

namespace BYanelli\Roma;

enum Source
{
    case Query;
    case Body;
    case QueryOrBody;
    case Header;
    case File;
}
