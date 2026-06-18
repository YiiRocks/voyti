<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Validator;

use YiiRocks\Voyti\Helper\TimezoneHelper;

final class TimeZoneValidator
{
    public function __construct(
        private readonly string $timezone,
    ) {
    }

    public function validate(): bool
    {
        return TimezoneHelper::isValid($this->timezone);
    }
}
