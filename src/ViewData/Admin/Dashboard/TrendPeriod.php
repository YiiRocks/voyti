<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\ViewData\Admin\Dashboard;

/**
 * A single period column (e.g. "last 1 day") within a {@see TrendWidget}.
 */
final readonly class TrendPeriod
{
    /**
     * @param string $labelKey a raw translation message key - pass to `$translator->translate()`
     *        yourself, unlike most other ViewData string fields which are already translated
     * @param array<string, mixed> $params the parameters to pass alongside $labelKey to `translate()`
     */
    public function __construct(
        public string $labelKey,
        public int $value,
        public array $params = [],
    ) {}
}
