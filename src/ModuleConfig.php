<?php

declare(strict_types=1);

namespace YiiRocks\Voyti;

use LogicException;
use YiiRocks\Voyti\Enum\EmailChangeConfirmation;
use YiiRocks\Voyti\Enum\ProfileVisibility;
use YiiRocks\Voyti\Enum\RecaptchaVersion;
use Yiisoft\Router\RouteNotFoundException;
use Yiisoft\Router\UrlGeneratorInterface;

/**
 * Single source of truth for all module settings: an immutable value object built from the
 * host's `yiirocks/voyti` params array and injected into services instead of raw params.
 */
final readonly class ModuleConfig
{
    public const DEFAULT_VIEW_PATH = __DIR__ . '/../resources/views/bootstrap5';

    public function __construct(
        public string $appName,
        public ?RecaptchaVersion $recaptchaVersion,
        public bool $enableGdprCompliance,
        /** @psalm-var list<string> */
        public array $gdprExportProperties,
        public string $gdprAnonymizePrefix,
        public bool $enableTwoFactorAuthentication,
        /** @psalm-var array<array-key, string> */
        public array $twoFactorAuthenticationForcedPermissions,
        public bool $enableRegistration,
        public bool $enableSocialNetworkRegistration,
        /** @psalm-var array<string, array<string, mixed>> */
        public array $socialNetworkClients,
        public bool $enableEmailConfirmation,
        public bool $enableSwitchIdentities,
        public string $homeRoute,
        public ?string $mailAdminOnRegister,
        public bool $enablePasswordExpiration,
        public bool $enablePasswordComplexity,
        public int $passwordHistoryLimit,
        public bool $allowPasswordRecovery,
        public bool $allowAdminPasswordRecovery,
        public bool $allowAccountDelete,
        public EmailChangeConfirmation $emailChangeConfirmation,
        public int $rememberLoginLifespan,
        public int $tokenConfirmationLifespan,
        public int $tokenRecoveryLifespan,
        public string $administratorPermissionName,
        public ProfileVisibility $profileVisibility,
        public ?int $maxPasswordAge,
        public string $viewPath,
        public string $mailPath,
        public bool $enableRestApi,
        public string $adminRestPrefix,
        public ?int $apiTokenLifespan,
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
