<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Helper;

enum RecaptchaVersion: string
{
    case V2 = 'v2';
    case V3 = 'v3';
}
