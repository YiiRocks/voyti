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
 * host's `yiirocks/voyti` params array via {@see self::fromArray()} and injected into services
 * instead of raw params.
 */
final readonly class ModuleConfig
{
    public const DEFAULT_VIEW_PATH = __DIR__ . '/../resources/views/bootstrap5';

    public function __construct(
        public string $appName = 'Voyti',
        public ?RecaptchaVersion $recaptchaVersion = null,
        public bool $enableGdprCompliance = false,
        /** @psalm-var list<string> */
        public array $gdprExportProperties = [
            'email',
            'username',
            'userProfile.public_email',
            'userProfile.name',
            'userProfile.gravatar_email',
            'userProfile.location',
            'userProfile.website',
            'userProfile.bio',
            'userProfile.birthday',
            'userSessions',
            'userSocialAccount',
        ],
        public string $gdprAnonymizePrefix = 'GDPR',
        public bool $enableTwoFactorAuthentication = false,
        /** @psalm-var array<array-key, string> */
        public array $twoFactorAuthenticationForcedPermissions = [],
        public bool $enableRegistration = true,
        public bool $enableSocialNetworkRegistration = true,
        /** @psalm-var array<string, array<string, mixed>> */
        public array $socialNetworkClients = [],
        public bool $enableEmailConfirmation = true,
        public bool $enableSwitchIdentities = true,
        public string $homeRoute = 'home',
        public ?string $mailAdminOnRegister = null,
        public bool $enablePasswordExpiration = false,
        public bool $enablePasswordComplexity = false,
        public int $passwordHistoryLimit = 10,
        public bool $allowPasswordRecovery = true,
        public bool $allowAdminPasswordRecovery = false,
        public bool $allowAccountDelete = false,
        public EmailChangeConfirmation $emailChangeConfirmation = EmailChangeConfirmation::NEW,
        public int $rememberLoginLifespan = 2592000,
        public int $tokenConfirmationLifespan = 86400,
        public int $tokenRecoveryLifespan = 21600,
        public string $administratorPermissionName = 'voyti-admin',
        public ProfileVisibility $profileVisibility = ProfileVisibility::USERS,
        public ?int $maxPasswordAge = null,
        public string $viewPath = self::DEFAULT_VIEW_PATH,
        public string $mailPath = __DIR__ . '/../resources/mail',
        public bool $enableRestApi = false,
        public string $adminRestPrefix = 'api',
        public ?int $apiTokenLifespan = null,
        public bool $enableAuditLog = true,
    ) {}

    /**
     * @return (array|bool|int|null|string|EmailChangeConfirmation|ProfileVisibility|RecaptchaVersion)[]
     *
     * @psalm-return array{
     *     appName: string,
     *     recaptchaVersion: null|RecaptchaVersion,
     *     enableGdprCompliance: bool,
     *     gdprExportProperties: list<string>,
     *     gdprAnonymizePrefix: string,
     *     enableTwoFactorAuthentication: bool,
     *     twoFactorAuthenticationForcedPermissions: array<array-key, string>,
     *     enableRegistration: bool,
     *     enableSocialNetworkRegistration: bool,
     *     socialNetworkClients: array<string, array<string, mixed>>,
     *     enableEmailConfirmation: bool,
     *     enableSwitchIdentities: bool,
     *     homeRoute: string,
     *     mailAdminOnRegister: null|string,
     *     enablePasswordExpiration: bool,
     *     enablePasswordComplexity: bool,
     *     passwordHistoryLimit: int,
     *     allowPasswordRecovery: bool,
     *     allowAdminPasswordRecovery: bool,
     *     allowAccountDelete: bool,
     *     emailChangeConfirmation: EmailChangeConfirmation,
     *     rememberLoginLifespan: int,
     *     tokenConfirmationLifespan: int,
     *     tokenRecoveryLifespan: int,
     *     administratorPermissionName: string,
     *     profileVisibility: ProfileVisibility,
     *     maxPasswordAge: int|null,
     *     viewPath: string,
     *     mailPath: string,
     *     enableRestApi: bool,
     *     adminRestPrefix: string,
     *     apiTokenLifespan: int|null,
     *     enableAuditLog: bool,
     * }
     */
    public static function defaults(): array
    {
        return get_object_vars(new self());
    }

    /**
     * @param array $config
     *
     * @psalm-suppress MixedArgument
     */
    public static function fromArray(array $config): self
    {
        $defaults = self::defaults();

        return new self(...array_intersect_key($config, $defaults));
    }

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
