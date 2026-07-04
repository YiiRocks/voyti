<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Validator;

use YiiRocks\Voyti\Helper\TimezoneHelper;

final readonly class TimeZoneValidator
{
    public function __construct(
        private string $timezone,
    ) {
    }

    public function validate(): bool
    {
        return TimezoneHelper::isValid($this->timezone);
    }
}
