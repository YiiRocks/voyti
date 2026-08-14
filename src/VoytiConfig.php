<?php

declare(strict_types=1);

namespace YiiRocks\Voyti;

use LogicException;
use YiiRocks\Voyti\Enum\EmailChangeConfirmation;
use YiiRocks\Voyti\Enum\ProfileVisibility;
use YiiRocks\Voyti\Enum\RecaptchaVersion;
use YiiRocks\Voyti\Enum\WebTheme;
use Yiisoft\Router\RouteNotFoundException;
use Yiisoft\Router\UrlGeneratorInterface;

/**
 * Single source of truth for all module settings: an immutable value object injected into
 * services instead of raw params.
 */
final readonly class VoytiConfig
{
    public const string DEFAULT_MAIL_PATH = __DIR__ . '/../resources/mail';

    public function __construct(
        public string $appName,
        public RecaptchaVersion $recaptchaVersion,
        /**
         * Extra account-menu links contributed by packages (e.g. yiirocks/voyti-2fa), merged in via
         * the `accountMenuItems` param so core needs no knowledge of them.
         *
         * @psalm-var list<array{label: string, category: string, route: string}>
         */
        public array $accountMenuItems,
        public bool $enableRegistration,
        public bool $enableSocialNetworkRegistration,
        public bool $enableEmailConfirmation,
        public bool $enableSwitchIdentities,
        public string $homeRoute,
        public ?string $mailAdminOnRegister,
        public bool $enablePasswordComplexity,
        public int $passwordHistoryLimit,
        public bool $allowPasswordRecovery,
        public bool $allowAdminPasswordRecovery,
        public bool $allowAccountDelete,
        /**
         * Extra links contributed by privacy-related packages (e.g. yiirocks/voyti-gdpr) to the
         * Privacy settings hub, merged in via the `privacyMenuItems` param so core needs no
         * knowledge of them. Privacy itself is shown whenever this is non-empty or
         * {@see self::$allowAccountDelete} is true.
         *
         * @psalm-var list<array{label: string, category: string, route: string}>
         */
        public array $privacyMenuItems,
        public EmailChangeConfirmation $emailChangeConfirmation,
        public int $rememberLoginLifespan,
        public int $tokenConfirmationLifespan,
        public int $tokenRecoveryLifespan,
        public string $administratorPermissionName,
        public ProfileVisibility $profileVisibility,
        public int $maxPasswordAge,
        public WebTheme $webTheme,
        public ?string $viewPath,
        public string $mailPath,
        public bool $enableAuditLog,
    ) {}

    /**
     * @throws LogicException if homeRoute is not registered
     */
    public function getHomeUrl(UrlGeneratorInterface $url): string
    {
        try {
            return $url->generate($this->homeRoute);
        } catch (RouteNotFoundException $exception) {
            throw new LogicException(
                sprintf(
                    '"homeRoute" is set to "%s", but no such route is registered. '
                    . 'Configure "homeRoute" in the "yiirocks/voyti" params to point to a route the '
                    . 'application actually defines.',
                    $this->homeRoute,
                ),
                0,
                $exception,
            );
        }
    }
}
