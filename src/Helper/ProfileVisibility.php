<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Helper;

enum ProfileVisibility: int
{
    case ADMIN = 1;
    case OWNER = 0;
    case PUBLIC = 3;
    case USERS = 2;
}
