<?php

declare(strict_types=1);

namespace YiiRocks\Voyti;

use LogicException;
use YiiRocks\Voyti\Enum\EmailChangeConfirmation;
use YiiRocks\Voyti\Enum\ProfileVisibility;
use YiiRocks\Voyti\Enum\RecaptchaVersion;
use Yiisoft\Router\RouteNotFoundException;
use Yiisoft\Router\UrlGeneratorInterface;

use function sprintf;

/**
 * Single source of truth for all module settings: an immutable value object injected into
 * services instead of raw params.
 */
final readonly class VoytiConfig
{
    public const string DEFAULT_MAIL_PATH = __DIR__ . '/../resources/mail';

    public function __construct(
        // General
        public string $appName,
        public string $homeRoute,
        // Authentication & Registration
        public bool $enableRegistration,
        public bool $enableEmailConfirmation,
        public bool $allowPasswordRecovery,
        public bool $allowAdminPasswordRecovery,
        public bool $allowAccountDelete,
        public EmailChangeConfirmation $emailChangeConfirmation,
        public int $rememberLoginLifespan,
        public int $tokenConfirmationLifespan,
        public int $tokenRecoveryLifespan,
        public bool $enableSwitchIdentities,
        public ?string $mailAdminOnRegister,
        public RecaptchaVersion $recaptchaVersion,
        // Session & Security
        public int $maxPasswordAge,
        public bool $enablePasswordComplexity,
        public int $passwordHistoryLimit,
        public string $administratorPermissionName,
        public ProfileVisibility $profileVisibility,
        public bool $enableAuditLog,
        public ?string $rememberMeCookieDomain,
        // Views & Mail
        public ?string $viewPath,
        public string $mailPath,
        // Admin Dashboard
        public bool $enableRecommendations,
        // Contributed by other packages - not meant to be set by host apps directly
        /** @psalm-var list<array{label: string, category: string, route: string}> */
        public array $accountMenuItems,
        /** @psalm-var list<array{label: string, category: string, route: string}> */
        public array $privacyMenuItems,
        /**
         * A list, not a single value, so multiple views packages merge by appending instead of a config error.
         *
         * @psalm-var list<string>
         */
        public array $viewsPackagePaths,
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
