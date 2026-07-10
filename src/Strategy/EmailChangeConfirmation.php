<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Strategy;

enum EmailChangeConfirmation: string
{
    case BOTH = 'both';
    case NEW = 'new';
    case NONE = 'none';
}
