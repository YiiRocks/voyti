<?php

declare(strict_types=1);

namespace YiiRocks\Voyti;

final readonly class ModuleConfig
{

    public function __construct(
        public string $appName = 'Voyti',
        public ?string $recaptchaVersion = null,
        public bool $enableSessionHistory = true,
        public int|false $numberSessionHistory = 50,
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
        public ?string $switchIdentitySessionKey = 'voyti_original_user',
        public string $loginRoute = 'voyti/login',
        public string $accountSettingsRoute = 'voyti/settings-account',
        public ?string $mailAdminOnRegister = null,
        public bool $enablePasswordExpiration = false,
        public bool $generatePasswords = false,
        public bool $allowPasswordRecovery = true,
        public bool $allowAdminPasswordRecovery = true,
        public bool $allowAccountDelete = false,
        public int $emailChangeStrategy = 1,
        public int $rememberLoginLifespan = 2592000,
        public int $tokenConfirmationLifespan = 86400,
        public int $tokenRecoveryLifespan = 21600,
        public ?string $administratorPermissionName = 'admin',
        public int $profileVisibility = 2,
        public ?int $maxPasswordAge = null,
        public bool $disableIpLogging = false,
        public string $viewPath = __DIR__ . '/resources/views/bootstrap5',
        public string $mailPath = __DIR__ . '/resources/mail',
        public bool $enableRestApi = false,
        public string $adminRestPrefix = 'api/v1',
    ) {
    }

    /**
     * @return (array|bool|int|null|string)[]
     *
     * @psalm-return array{appName: string, recaptchaVersion: null|string, enableSessionHistory: bool, numberSessionHistory: false|int, enableGdprCompliance: bool, gdprExportProperties: list<string>, gdprAnonymizePrefix: string, enableTwoFactorAuthentication: bool, twoFactorAuthenticationForcedPermissions: array<array-key, string>, enableRegistration: bool, enableSocialNetworkRegistration: bool, socialNetworkClients: array<string, array<string, mixed>>, enableEmailConfirmation: bool, enableSwitchIdentities: bool, switchIdentitySessionKey: null|string, loginRoute: string, accountSettingsRoute: string, mailAdminOnRegister: null|string, enablePasswordExpiration: bool, generatePasswords: bool, allowPasswordRecovery: bool, allowAdminPasswordRecovery: bool, allowAccountDelete: bool, emailChangeStrategy: int, rememberLoginLifespan: int, tokenConfirmationLifespan: int, tokenRecoveryLifespan: int, administratorPermissionName: null|string, profileVisibility: int, maxPasswordAge: int|null, disableIpLogging: bool, viewPath: string, mailPath: string, enableRestApi: bool, adminRestPrefix: string}
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

    public function hasNumberSessionHistory(): bool
    {
        return $this->numberSessionHistory !== false && $this->numberSessionHistory > 0;
    }
}
