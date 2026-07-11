<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Enum;

enum RecaptchaVersion: string
{
    case V2 = 'v2';
    case V3 = 'v3';
}
