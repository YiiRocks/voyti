<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\ViewData\Admin\User;

use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Model\UserSessions;
use YiiRocks\Voyti\ViewData\Shared\MenuViewData;
use YiiRocks\Voyti\ViewData\Shared\SessionRow;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;

/**
 * Data for the `admin/user/_sessions` screen.
 */
final readonly class SessionsViewData
{
    /**
     * @param list<SessionRow> $sessions
     */
    private function __construct(
        public MenuViewData $menu,
        public array $sessions,
        public string $formSubmitUrl,
    ) {}

    /**
     * @param list<UserSessions> $sessions
     */
    public static function create(
        User $user,
        array $sessions,
        UrlGeneratorInterface $url,
        TranslatorInterface $translator,
        ?string $viewerTimezone,
    ): self {
        $locale = $translator->getLocale();

        return new self(
            menu: MenuViewData::forAdmin($url, $translator),
            sessions: array_map(
                static fn(UserSessions $session): SessionRow => SessionRow::create($session, $viewerTimezone, $locale),
                $sessions,
            ),
            formSubmitUrl: $url->generate('voyti/admin-users-terminate-sessions', ['id' => $user->getId()]),
        );
    }
}
