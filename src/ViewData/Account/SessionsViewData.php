<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\ViewData\Account;

use YiiRocks\Voyti\Model\UserSessions;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\ViewData\Shared\MenuViewData;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;

/**
 * Data for the `account/sessions` screen.
 */
final readonly class SessionsViewData
{
    /**
     * @param list<SessionRow> $sessions
     */
    private function __construct(
        public MenuViewData $menu,
        public array $sessions,
    ) {}

    /**
     * @param list<UserSessions> $sessions
     */
    public static function create(
        array $sessions,
        ?string $currentSessionId,
        ?string $timezone,
        ModuleConfig $config,
        UrlGeneratorInterface $url,
        TranslatorInterface $translator,
    ): self {
        $locale = $translator->getLocale();
        $rows = array_map(
            static fn(UserSessions $session): SessionRow => SessionRow::create($session, $currentSessionId, $timezone, $locale, $url),
            $sessions,
        );

        return new self(
            menu: MenuViewData::forAccount($config, $url, $translator),
            sessions: $rows,
        );
    }
}
