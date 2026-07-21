<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\ViewData\Account;

use YiiRocks\Voyti\Model\UserSessions;
use YiiRocks\Voyti\ViewData\Shared\SessionRow as SharedSessionRow;
use Yiisoft\Router\UrlGeneratorInterface;

/**
 * A single row on the account-sessions screen: the shared session facts plus the
 * self-service actions available there (terminate this session, "this device" badge).
 */
final readonly class SessionRow
{
    private function __construct(
        public SharedSessionRow $session,
        public bool $isCurrentSession,
        public string $formSubmitUrl,
    ) {}

    public static function create(
        UserSessions $session,
        ?string $currentSessionId,
        ?string $timezone,
        string $locale,
        UrlGeneratorInterface $url,
    ): self {
        return new self(
            session: SharedSessionRow::create($session, $timezone, $locale),
            isCurrentSession: $session->getSessionId() === $currentSessionId,
            formSubmitUrl: $url->generate('voyti/user-account-sessions-terminate', ['sessionId' => $session->getSessionId()]),
        );
    }
}
