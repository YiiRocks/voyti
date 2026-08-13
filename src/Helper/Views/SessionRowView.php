<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Helper\Views;

use YiiRocks\Voyti\Helper\TimezoneHelper;
use YiiRocks\Voyti\Model\UserSessions;

/**
 * Builds the display facts common to any session row (account-sessions and admin user-sessions
 * screens), independent of what actions a screen offers per row.
 */
final class SessionRowView
{
    /**
     * @return array{
     *     ip: string,
     *     userAgent: string,
     *     lastSeenDisplay: string,
     *     isRevoked: bool,
     *     revokedAtDisplay: string|null,
     * }
     */
    public static function create(UserSessions $session, ?string $timezone, string $locale): array
    {
        $revokedAt = $session->getRevokedAt();

        return [
            'ip' => $session->getIp() ?? '',
            'userAgent' => $session->getUserAgent() ?? '',
            'lastSeenDisplay' => TimezoneHelper::formatLocalized($session->getUpdatedAt(), $locale, $timezone),
            'isRevoked' => $revokedAt !== null,
            'revokedAtDisplay' => $revokedAt !== null ? TimezoneHelper::formatLocalized($revokedAt, $locale, $timezone) : null,
        ];
    }
}
