<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\ModuleConfig;

final class ModuleConfigTest extends TestCase
{

    public function testCustomValues(): void
    {
        $config = new ModuleConfig(
            recaptchaVersion: 'v2',
            enableSessionHistory: true,
            numberSessionHistory: 100,
            timeoutSessionHistory: 3600,
            enableGdprCompliance: true,
            gdprPrivacyPolicyUrl: 'https://example.com/privacy',
            gdprExportProperties: ['email'],
            gdprAnonymizePrefix: 'ANON',
            gdprRequireConsentToAll: true,
            gdprConsentMessage: 'Do you consent?',
            gdprConsentExcludedUrls: ['/admin'],
            enableTwoFactorAuthentication: true,
            twoFactorAuthenticationForcedPermissions: ['admin'],
            twoFactorAuthenticationValidators: ['email'],
            twoFactorAuthenticationCycles: 2,
            enableAutoLogin: false,
            enableRegistration: false,
            enableSocialNetworkRegistration: false,
            sendWelcomeMailAfterSocialNetworkRegistration: false,
            enableEmailConfirmation: false,
            enableFlashMessages: false,
            enableSwitchIdentities: false,
            switchIdentitySessionKey: 'custom_key',
            mailAdminOnRegister: 'admin@example.com',
            enablePasswordExpiration: true,
            generatePasswords: true,
            allowUnconfirmedEmailLogin: true,
            allowPasswordRecovery: false,
            allowAdminPasswordRecovery: false,
            allowAccountDelete: true,
            emailChangeStrategy: 2,
            rememberLoginLifespan: 3600,
            tokenConfirmationLifespan: 1800,
            tokenRecoveryLifespan: 900,
            administrators: ['admin'],
            administratorPermissionName: 'admin',
            profileVisibility: 1,
            blowfishCost: 12,
            maxPasswordAge: 90,
            restrictUserPermissionAssignment: true,
            disableIpLogging: true,
            minPasswordRequirements: ['lower' => 1],
            mailParams: ['fromEmail' => 'admin@example.com'],
            enableRestApi: true,
            authenticatorClass: 'Yiisoft\\Auth\\Method\\Session',
            adminRestPrefix: 'admin/api/v2',
            adminRestRoutes: ['GET users' => 'index'],
        );

        $this->assertSame('v2', $config->recaptchaVersion);
        $this->assertTrue($config->enableSessionHistory);
        $this->assertSame(100, $config->numberSessionHistory);
        $this->assertSame(3600, $config->timeoutSessionHistory);
        $this->assertTrue($config->enableGdprCompliance);
        $this->assertSame('https://example.com/privacy', $config->gdprPrivacyPolicyUrl);
        $this->assertSame(['email'], $config->gdprExportProperties);
        $this->assertSame('ANON', $config->gdprAnonymizePrefix);
        $this->assertTrue($config->gdprRequireConsentToAll);
        $this->assertSame('Do you consent?', $config->gdprConsentMessage);
        $this->assertSame(['/admin'], $config->gdprConsentExcludedUrls);
        $this->assertTrue($config->enableTwoFactorAuthentication);
        $this->assertSame(['admin'], $config->twoFactorAuthenticationForcedPermissions);
        $this->assertSame(['email'], $config->twoFactorAuthenticationValidators);
        $this->assertSame(2, $config->twoFactorAuthenticationCycles);
        $this->assertFalse($config->enableAutoLogin);
        $this->assertFalse($config->enableRegistration);
        $this->assertFalse($config->enableSocialNetworkRegistration);
        $this->assertFalse($config->sendWelcomeMailAfterSocialNetworkRegistration);
        $this->assertFalse($config->enableEmailConfirmation);
        $this->assertFalse($config->enableFlashMessages);
        $this->assertFalse($config->enableSwitchIdentities);
        $this->assertSame('custom_key', $config->switchIdentitySessionKey);
        $this->assertSame('admin@example.com', $config->mailAdminOnRegister);
        $this->assertTrue($config->enablePasswordExpiration);
        $this->assertTrue($config->generatePasswords);
        $this->assertTrue($config->allowUnconfirmedEmailLogin);
        $this->assertFalse($config->allowPasswordRecovery);
        $this->assertFalse($config->allowAdminPasswordRecovery);
        $this->assertTrue($config->allowAccountDelete);
        $this->assertSame(2, $config->emailChangeStrategy);
        $this->assertSame(3600, $config->rememberLoginLifespan);
        $this->assertSame(1800, $config->tokenConfirmationLifespan);
        $this->assertSame(900, $config->tokenRecoveryLifespan);
        $this->assertSame(['admin'], $config->administrators);
        $this->assertSame('admin', $config->administratorPermissionName);
        $this->assertSame(1, $config->profileVisibility);
        $this->assertSame(12, $config->blowfishCost);
        $this->assertSame(90, $config->maxPasswordAge);
        $this->assertTrue($config->restrictUserPermissionAssignment);
        $this->assertTrue($config->disableIpLogging);
        $this->assertSame(['lower' => 1], $config->minPasswordRequirements);
        $this->assertSame(['fromEmail' => 'admin@example.com'], $config->mailParams);
        $this->assertTrue($config->enableRestApi);
        $this->assertSame('Yiisoft\\Auth\\Method\\Session', $config->authenticatorClass);
        $this->assertSame('admin/api/v2', $config->adminRestPrefix);
        $this->assertSame(['GET users' => 'index'], $config->adminRestRoutes);
    }
    public function testDefaultValues(): void
    {
        $config = new ModuleConfig();

        $this->assertNull($config->recaptchaVersion);
        $this->assertFalse($config->enableSessionHistory);
        $this->assertFalse($config->numberSessionHistory);
        $this->assertFalse($config->timeoutSessionHistory);
        $this->assertFalse($config->enableGdprCompliance);
        $this->assertNull($config->gdprPrivacyPolicyUrl);
        $this->assertSame([
            'email',
            'username',
            'userProfile.public_email',
            'userProfile.name',
            'userProfile.gravatar_email',
            'userProfile.location',
            'userProfile.website',
            'userProfile.bio',
        ], $config->gdprExportProperties);
        $this->assertSame('GDPR', $config->gdprAnonymizePrefix);
        $this->assertFalse($config->gdprRequireConsentToAll);
        $this->assertNull($config->gdprConsentMessage);
        $this->assertSame(['user/settings/*'], $config->gdprConsentExcludedUrls);
        $this->assertFalse($config->enableTwoFactorAuthentication);
        $this->assertSame([], $config->twoFactorAuthenticationForcedPermissions);
        $this->assertSame([], $config->twoFactorAuthenticationValidators);
        $this->assertSame(1, $config->twoFactorAuthenticationCycles);
        $this->assertTrue($config->enableAutoLogin);
        $this->assertTrue($config->enableRegistration);
        $this->assertTrue($config->enableSocialNetworkRegistration);
        $this->assertTrue($config->sendWelcomeMailAfterSocialNetworkRegistration);
        $this->assertTrue($config->enableEmailConfirmation);
        $this->assertTrue($config->enableFlashMessages);
        $this->assertTrue($config->enableSwitchIdentities);
        $this->assertSame('voyti_original_user', $config->switchIdentitySessionKey);
        $this->assertNull($config->mailAdminOnRegister);
        $this->assertFalse($config->enablePasswordExpiration);
        $this->assertFalse($config->generatePasswords);
        $this->assertFalse($config->allowUnconfirmedEmailLogin);
        $this->assertTrue($config->allowPasswordRecovery);
        $this->assertTrue($config->allowAdminPasswordRecovery);
        $this->assertFalse($config->allowAccountDelete);
        $this->assertSame(1, $config->emailChangeStrategy);
        $this->assertSame(1209600, $config->rememberLoginLifespan);
        $this->assertSame(86400, $config->tokenConfirmationLifespan);
        $this->assertSame(21600, $config->tokenRecoveryLifespan);
        $this->assertSame([], $config->administrators);
        $this->assertNull($config->administratorPermissionName);
        $this->assertSame(0, $config->profileVisibility);
        $this->assertSame(10, $config->blowfishCost);
        $this->assertNull($config->maxPasswordAge);
        $this->assertFalse($config->restrictUserPermissionAssignment);
        $this->assertFalse($config->disableIpLogging);
        $this->assertSame(['lower' => 1, 'digit' => 1, 'upper' => 1], $config->minPasswordRequirements);
        $this->assertSame([
            'fromEmail' => 'no-reply@example.com',
            'welcomeMailSubject' => 'Welcome to {app}',
            'confirmationMailSubject' => 'Confirm account on {app}',
            'reconfirmationMailSubject' => 'Confirm email change on {app}',
            'recoveryMailSubject' => 'Complete password reset on {app}',
            'twoFactorMailSubject' => 'Code for two factor authentication on {app}',
        ], $config->mailParams);
        $this->assertFalse($config->enableRestApi);
        $this->assertSame('Yiisoft\\Auth\\Method\\QueryParam', $config->authenticatorClass);
        $this->assertSame('api/v1', $config->adminRestPrefix);
        $this->assertSame([
            'GET users' => 'index',
            'POST users' => 'create',
            'PUT,PATCH users/{id}' => 'update',
            'GET users/{id}' => 'view',
            'DELETE users/{id}' => 'delete',
        ], $config->adminRestRoutes);
    }

    public function testHasNumberSessionHistory(): void
    {
        $config = new ModuleConfig();
        $this->assertFalse($config->hasNumberSessionHistory());

        $config = new ModuleConfig(numberSessionHistory: false);
        $this->assertFalse($config->hasNumberSessionHistory());

        $config = new ModuleConfig(numberSessionHistory: 0);
        $this->assertFalse($config->hasNumberSessionHistory());

        $config = new ModuleConfig(numberSessionHistory: 5);
        $this->assertTrue($config->hasNumberSessionHistory());
    }

    public function testHasTimeoutSessionHistory(): void
    {
        $config = new ModuleConfig();
        $this->assertFalse($config->hasTimeoutSessionHistory());

        $config = new ModuleConfig(timeoutSessionHistory: false);
        $this->assertFalse($config->hasTimeoutSessionHistory());

        $config = new ModuleConfig(timeoutSessionHistory: 0);
        $this->assertFalse($config->hasTimeoutSessionHistory());

        $config = new ModuleConfig(timeoutSessionHistory: 3600);
        $this->assertTrue($config->hasTimeoutSessionHistory());
    }
}
