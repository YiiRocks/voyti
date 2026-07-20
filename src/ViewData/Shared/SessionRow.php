<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\ViewData\Shared;

use YiiRocks\Voyti\Helper\TimezoneHelper;
use YiiRocks\Voyti\Model\UserSessions;

/**
 * The display facts common to any session row (account-sessions and admin user-sessions
 * screens), independent of what actions a screen offers per row.
 */
final readonly class SessionRow
{
    /**
     * @param string|null $revokedAtDisplay always null unless $isRevoked
     */
    private function __construct(
        public string $ip,
        public string $userAgent,
        public string $lastSeenDisplay,
        public bool $isRevoked,
        public ?string $revokedAtDisplay,
    ) {}

    public static function create(UserSessions $session, ?string $timezone, string $locale): self
    {
        $isRevoked = $session->isRevoked();

        return new self(
            ip: $session->getIp() ?? '',
            userAgent: $session->getUserAgent() ?? '',
            lastSeenDisplay: TimezoneHelper::formatLocalized($session->getUpdatedAt(), $locale, $timezone),
            isRevoked: $isRevoked,
            revokedAtDisplay: $isRevoked ? TimezoneHelper::formatLocalized($session->getRevokedAt() ?? 0, $locale, $timezone) : null,
        );
    }
}
