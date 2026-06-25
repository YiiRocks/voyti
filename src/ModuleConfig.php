<?php

declare(strict_types=1);

namespace YiiRocks\Voyti;

final class ModuleConfig
{
    public function __construct(
        public readonly ?string $recaptchaVersion = null,
        public readonly bool $enableSessionHistory = false,
        public readonly int|false $numberSessionHistory = false,
        public readonly int|false $timeoutSessionHistory = false,
        public readonly bool $enableGdprCompliance = false,
        public readonly ?string $gdprPrivacyPolicyUrl = null,
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
        public readonly bool $gdprRequireConsentToAll = false,
        public readonly ?string $gdprConsentMessage = null,
        public readonly array $gdprConsentExcludedUrls = ['user/settings/*'],
        public readonly bool $enableTwoFactorAuthentication = false,
        public readonly array $twoFactorAuthenticationForcedPermissions = [],
        public readonly array $twoFactorAuthenticationValidators = [],
        public readonly int $twoFactorAuthenticationCycles = 1,
        public readonly bool $enableAutoLogin = true,
        public readonly bool $enableRegistration = true,
        public readonly bool $enableSocialNetworkRegistration = true,
        public readonly bool $sendWelcomeMailAfterSocialNetworkRegistration = true,
        public readonly bool $enableEmailConfirmation = true,
        public readonly bool $enableFlashMessages = true,
        public readonly bool $enableSwitchIdentities = true,
        public readonly ?string $switchIdentitySessionKey = Voyti::SWITCH_IDENTITY_SESSION_KEY,
        public readonly string $loginPath = '/user/login',
        public readonly string $accountSettingsPath = '/user/settings/account',
        public readonly ?string $mailAdminOnRegister = null,
        public readonly bool $enablePasswordExpiration = false,
        public readonly bool $generatePasswords = false,
        public readonly bool $allowUnconfirmedEmailLogin = false,
        public readonly bool $allowPasswordRecovery = true,
        public readonly bool $allowAdminPasswordRecovery = true,
        public readonly bool $allowAccountDelete = false,
        public readonly int $emailChangeStrategy = 1,
        public readonly int $rememberLoginLifespan = 1209600,
        public readonly int $tokenConfirmationLifespan = 86400,
        public readonly int $tokenRecoveryLifespan = 21600,
        public readonly array $administrators = [],
        public readonly ?string $administratorPermissionName = null,
        public readonly int $profileVisibility = 0,
        public readonly ?int $maxPasswordAge = null,
        public readonly bool $restrictUserPermissionAssignment = false,
        public readonly bool $disableIpLogging = false,
        public readonly array $minPasswordRequirements = ['lower' => 1, 'digit' => 1, 'upper' => 1],
        public readonly string $viewPath = Voyti::VIEWS_PATH,
        public readonly string $mailPath = Voyti::MAIL_PATH,
        public readonly array $mailParams = [
            'fromEmail' => 'no-reply@example.com',
            'welcomeMailSubject' => 'Welcome to {app}',
            'confirmationMailSubject' => 'Confirm account on {app}',
            'reconfirmationMailSubject' => 'Confirm email change on {app}',
            'recoveryMailSubject' => 'Complete password reset on {app}',
            'twoFactorMailSubject' => 'Code for two factor authentication on {app}',
        ],
        public readonly bool $enableRestApi = false,
        public readonly string $authenticatorClass = 'Yiisoft\\Auth\\Method\\QueryParam',
        public readonly string $adminRestPrefix = 'api/v1',
        public readonly array $adminRestRoutes = [
            'GET users' => 'index',
            'POST users' => 'create',
            'PUT,PATCH users/{id}' => 'update',
            'GET users/{id}' => 'view',
            'DELETE users/{id}' => 'delete',
        ],
    ) {
    }

    public function hasNumberSessionHistory(): bool
    {
        return $this->numberSessionHistory !== false && $this->numberSessionHistory > 0;
    }

    public function hasTimeoutSessionHistory(): bool
    {
        return $this->timeoutSessionHistory !== false && $this->timeoutSessionHistory > 0;
    }
}
