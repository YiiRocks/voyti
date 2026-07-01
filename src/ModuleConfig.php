<?php

declare(strict_types=1);

namespace YiiRocks\Voyti;

final class ModuleConfig
{
    /**
     * @return (array|bool|int|null|string)[]
     *
     * @psalm-return array{appName: string, recaptchaVersion: null|string, enableSessionHistory: bool, numberSessionHistory: false|int, enableGdprCompliance: bool, gdprExportProperties: list<string>, gdprAnonymizePrefix: string, enableTwoFactorAuthentication: bool, twoFactorAuthenticationForcedPermissions: array<array-key, string>, enableRegistration: bool, enableSocialNetworkRegistration: bool, socialNetworkClients: array<string, array<string, mixed>>, enableEmailConfirmation: bool, enableSwitchIdentities: bool, switchIdentitySessionKey: null|string, loginRoute: string, accountSettingsRoute: string, mailAdminOnRegister: null|string, enablePasswordExpiration: bool, generatePasswords: bool, allowPasswordRecovery: bool, allowAdminPasswordRecovery: bool, allowAccountDelete: bool, emailChangeStrategy: int, rememberLoginLifespan: int, tokenConfirmationLifespan: int, tokenRecoveryLifespan: int, administratorPermissionName: null|string, profileVisibility: int, maxPasswordAge: int|null, disableIpLogging: bool, viewPath: string, mailPath: string, mailParams: array<string, string>, enableRestApi: bool, adminRestPrefix: string}
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

        return new self(...array_replace($defaults, array_intersect_key($config, $defaults)));
    }

    public function __construct(
        public readonly string $appName = 'Voyti',
        public readonly ?string $recaptchaVersion = null,
        public readonly bool $enableSessionHistory = true,
        public readonly int|false $numberSessionHistory = 50,
        public readonly bool $enableGdprCompliance = false,
        /** @psalm-var list<string> */
        public readonly array $gdprExportProperties = [
            'email',
            'username',
            'userProfile.public_email',
            'userProfile.name',
            'userProfile.gravatar_email',
            'userProfile.location',
            'userProfile.website',
            'userProfile.bio',
        ],
        public readonly string $gdprAnonymizePrefix = 'GDPR',
        public readonly bool $enableTwoFactorAuthentication = false,
        /** @psalm-var array<array-key, string> */
        public readonly array $twoFactorAuthenticationForcedPermissions = [],
        public readonly bool $enableRegistration = true,
        public readonly bool $enableSocialNetworkRegistration = true,
        /** @psalm-var array<string, array<string, mixed>> */
        public readonly array $socialNetworkClients = [],
        public readonly bool $enableEmailConfirmation = true,
        public readonly bool $enableSwitchIdentities = true,
        public readonly ?string $switchIdentitySessionKey = 'voyti_original_user',
        public readonly string $loginRoute = 'voyti/login',
        public readonly string $accountSettingsRoute = 'voyti/settings-account',
        public readonly ?string $mailAdminOnRegister = null,
        public readonly bool $enablePasswordExpiration = false,
        public readonly bool $generatePasswords = false,
        public readonly bool $allowPasswordRecovery = true,
        public readonly bool $allowAdminPasswordRecovery = true,
        public readonly bool $allowAccountDelete = false,
        public readonly int $emailChangeStrategy = 1,
        public readonly int $rememberLoginLifespan = 2592000,
        public readonly int $tokenConfirmationLifespan = 86400,
        public readonly int $tokenRecoveryLifespan = 21600,
        public readonly ?string $administratorPermissionName = 'admin',
        public readonly int $profileVisibility = 2,
        public readonly ?int $maxPasswordAge = null,
        public readonly bool $disableIpLogging = false,
        public readonly string $viewPath = __DIR__ . '/resources/views/bootstrap5',
        public readonly string $mailPath = __DIR__ . '/resources/mail',
        /** @psalm-var array<string, string> */
        public readonly array $mailParams = [
            'welcomeMailSubject' => 'Welcome to {app}',
            'confirmationMailSubject' => 'Confirm account on {app}',
            'reconfirmationMailSubject' => 'Confirm email change on {app}',
            'recoveryMailSubject' => 'Complete password reset on {app}',
            'twoFactorMailSubject' => 'Code for two factor authentication on {app}',
        ],
        public readonly bool $enableRestApi = false,
        public readonly string $adminRestPrefix = 'api/v1',
    ) {
    }

    public function hasNumberSessionHistory(): bool
    {
        return $this->numberSessionHistory !== false && $this->numberSessionHistory > 0;
    }
}
