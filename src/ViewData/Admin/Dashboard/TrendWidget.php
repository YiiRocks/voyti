<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\ViewData\Admin\Dashboard;

/**
 * A card of {@see TrendPeriod} columns on the admin dashboard (e.g. "New registrations").
 */
final readonly class TrendWidget
{
    /**
     * @param string $titleKey a raw translation message key - pass to `$translator->translate()`
     *        yourself, unlike most other ViewData string fields which are already translated
     * @param list<TrendPeriod> $periods
     */
    public function __construct(
        public string $titleKey,
        public array $periods,
    ) {}
}
