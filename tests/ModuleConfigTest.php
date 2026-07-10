<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Helper\ProfileVisibility;
use YiiRocks\Voyti\Helper\RecaptchaVersion;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Strategy\EmailChangeConfirmation;

final class ModuleConfigTest extends TestCase
{

    public function testConstructorCustomValues(): void
    {
        $config = new ModuleConfig(
            appName: 'Custom',
            recaptchaVersion: RecaptchaVersion::V3,
            enableSessionHistory: false,
            numberSessionHistory: false,
            enableGdprCompliance: true,
            enableTwoFactorAuthentication: true,
            enableRegistration: false,
            enableSocialNetworkRegistration: false,
            enableEmailConfirmation: false,
            enableSwitchIdentities: false,
            switchIdentitySessionKey: null,
            loginRoute: 'custom/login',
            accountSettingsRoute: 'custom/settings',
            homeRoute: 'custom/home',
            mailAdminOnRegister: 'admin@example.com',
            enablePasswordExpiration: true,
            enablePasswordComplexity: true,
            generatePasswords: true,
            allowPasswordRecovery: false,
            allowAdminPasswordRecovery: false,
            allowAccountDelete: true,
            emailChangeConfirmation: EmailChangeConfirmation::BOTH,
            rememberLoginLifespan: 3600,
            tokenConfirmationLifespan: 7200,
            tokenRecoveryLifespan: 1800,
            administratorPermissionName: 'superadmin',
            profileVisibility: ProfileVisibility::ADMIN,
            maxPasswordAge: 90,
            disableIpLogging: true,
            enableRestApi: true,
            adminRestPrefix: 'custom-api',
            apiTokenLifespan: 3600,
        );
        self::assertSame('Custom', $config->appName);
        self::assertSame(RecaptchaVersion::V3, $config->recaptchaVersion);
        self::assertFalse($config->enableSessionHistory);
        self::assertFalse($config->numberSessionHistory);
        self::assertTrue($config->enableGdprCompliance);
        self::assertTrue($config->enableTwoFactorAuthentication);
        self::assertFalse($config->enableRegistration);
        self::assertFalse($config->enableSocialNetworkRegistration);
        self::assertFalse($config->enableEmailConfirmation);
        self::assertFalse($config->enableSwitchIdentities);
        self::assertNull($config->switchIdentitySessionKey);
        self::assertSame('custom/login', $config->loginRoute);
        self::assertSame('custom/settings', $config->accountSettingsRoute);
        self::assertSame('custom/home', $config->homeRoute);
        self::assertSame('admin@example.com', $config->mailAdminOnRegister);
        self::assertTrue($config->enablePasswordExpiration);
        self::assertTrue($config->enablePasswordComplexity);
        self::assertTrue($config->generatePasswords);
        self::assertFalse($config->allowPasswordRecovery);
        self::assertFalse($config->allowAdminPasswordRecovery);
        self::assertTrue($config->allowAccountDelete);
        self::assertSame(EmailChangeConfirmation::BOTH, $config->emailChangeConfirmation);
        self::assertSame(3600, $config->rememberLoginLifespan);
        self::assertSame(7200, $config->tokenConfirmationLifespan);
        self::assertSame(1800, $config->tokenRecoveryLifespan);
        self::assertSame('superadmin', $config->administratorPermissionName);
        self::assertSame(ProfileVisibility::ADMIN, $config->profileVisibility);
        self::assertSame(90, $config->maxPasswordAge);
        self::assertTrue($config->disableIpLogging);
        self::assertTrue($config->enableRestApi);
        self::assertSame('custom-api', $config->adminRestPrefix);
        self::assertSame(3600, $config->apiTokenLifespan);
    }

    public function testConstructorDefaults(): void
    {
        $config = new ModuleConfig();
        self::assertSame('Voyti', $config->appName);
        self::assertNull($config->recaptchaVersion);
        self::assertTrue($config->enableSessionHistory);
        self::assertSame(50, $config->numberSessionHistory);
        self::assertIsArray($config->gdprExportProperties);
        self::assertCount(10, $config->gdprExportProperties);
        self::assertSame('GDPR', $config->gdprAnonymizePrefix);
        self::assertFalse($config->enableTwoFactorAuthentication);
        self::assertSame([], $config->twoFactorAuthenticationForcedPermissions);
        self::assertSame([], $config->socialNetworkClients);
        self::assertSame('voyti_original_user', $config->switchIdentitySessionKey);
        self::assertSame('voyti/login', $config->loginRoute);
        self::assertSame('voyti/settings-account', $config->accountSettingsRoute);
        self::assertSame('home', $config->homeRoute);
        self::assertFalse($config->enablePasswordExpiration);
        self::assertFalse($config->enablePasswordComplexity);
        self::assertFalse($config->generatePasswords);
        self::assertSame(EmailChangeConfirmation::NEW, $config->emailChangeConfirmation);
        self::assertSame(2592000, $config->rememberLoginLifespan);
        self::assertSame(86400, $config->tokenConfirmationLifespan);
        self::assertSame(21600, $config->tokenRecoveryLifespan);
        self::assertSame('admin', $config->administratorPermissionName);
        self::assertSame(ProfileVisibility::USERS, $config->profileVisibility);
        self::assertNull($config->maxPasswordAge);
        self::assertFalse($config->disableIpLogging);
        self::assertFalse($config->enableRestApi);
        self::assertSame('api', $config->adminRestPrefix);
        self::assertNull($config->apiTokenLifespan);
    }
    public function testDefaultsReturnsAllPropertyKeys(): void
    {
        $defaults = ModuleConfig::defaults();
        $expectedKeys = [
            'appName',
            'recaptchaVersion',
            'enableSessionHistory',
            'numberSessionHistory',
            'enableGdprCompliance',
            'gdprExportProperties',
            'gdprAnonymizePrefix',
            'enableTwoFactorAuthentication',
            'twoFactorAuthenticationForcedPermissions',
            'enableRegistration',
            'enableSocialNetworkRegistration',
            'socialNetworkClients',
            'enableEmailConfirmation',
            'enableSwitchIdentities',
            'switchIdentitySessionKey',
            'loginRoute',
            'accountSettingsRoute',
            'homeRoute',
            'mailAdminOnRegister',
            'enablePasswordExpiration',
            'enablePasswordComplexity',
            'generatePasswords',
            'allowPasswordRecovery',
            'allowAdminPasswordRecovery',
            'allowAccountDelete',
            'emailChangeConfirmation',
            'rememberLoginLifespan',
            'tokenConfirmationLifespan',
            'tokenRecoveryLifespan',
            'administratorPermissionName',
            'profileVisibility',
            'maxPasswordAge',
            'disableIpLogging',
            'viewPath',
            'mailPath',
            'enableRestApi',
            'adminRestPrefix',
            'apiTokenLifespan',
        ];
        foreach ($expectedKeys as $key) {
            self::assertArrayHasKey($key, $defaults);
        }
        self::assertCount(count($expectedKeys), $defaults);
    }

    public function testFromArrayIgnoresInvalidKeys(): void
    {
        $config = ModuleConfig::fromArray([
            'nonexistentKey' => 'value',
            'anotherInvalid' => true,
        ]);
        self::assertSame('Voyti', $config->appName);
        self::assertTrue($config->enableRegistration);
    }

    public function testFromArrayMergesCustomValues(): void
    {
        $config = ModuleConfig::fromArray([
            'appName' => 'MyApp',
            'enableRegistration' => false,
            'enableGdprCompliance' => true,
            'numberSessionHistory' => 10,
        ]);
        self::assertSame('MyApp', $config->appName);
        self::assertFalse($config->enableRegistration);
        self::assertTrue($config->enableGdprCompliance);
        self::assertSame(10, $config->numberSessionHistory);
        self::assertNull($config->recaptchaVersion);
    }

    public function testFromArrayWithEmptyReturnsDefaults(): void
    {
        $config = ModuleConfig::fromArray([]);
        self::assertSame('Voyti', $config->appName);
        self::assertNull($config->recaptchaVersion);
        self::assertTrue($config->enableSessionHistory);
        self::assertSame(50, $config->numberSessionHistory);
        self::assertFalse($config->enableGdprCompliance);
        self::assertFalse($config->enableTwoFactorAuthentication);
        self::assertTrue($config->enableRegistration);
        self::assertTrue($config->enableSocialNetworkRegistration);
        self::assertTrue($config->enableEmailConfirmation);
        self::assertTrue($config->enableSwitchIdentities);
        self::assertSame('voyti_original_user', $config->switchIdentitySessionKey);
        self::assertSame('voyti/login', $config->loginRoute);
        self::assertSame('voyti/settings-account', $config->accountSettingsRoute);
        self::assertSame('home', $config->homeRoute);
        self::assertNull($config->mailAdminOnRegister);
        self::assertFalse($config->enablePasswordExpiration);
        self::assertFalse($config->enablePasswordComplexity);
        self::assertFalse($config->generatePasswords);
        self::assertTrue($config->allowPasswordRecovery);
        self::assertTrue($config->allowAdminPasswordRecovery);
        self::assertFalse($config->allowAccountDelete);
        self::assertSame(EmailChangeConfirmation::NEW, $config->emailChangeConfirmation);
        self::assertSame(2592000, $config->rememberLoginLifespan);
        self::assertSame(86400, $config->tokenConfirmationLifespan);
        self::assertSame(21600, $config->tokenRecoveryLifespan);
        self::assertSame('admin', $config->administratorPermissionName);
        self::assertSame(ProfileVisibility::USERS, $config->profileVisibility);
        self::assertNull($config->maxPasswordAge);
        self::assertFalse($config->disableIpLogging);
        self::assertFalse($config->enableRestApi);
        self::assertSame('api', $config->adminRestPrefix);
        self::assertNull($config->apiTokenLifespan);
    }

    public function testGettersReturnExpectedValues(): void
    {
        $config = new ModuleConfig(
            gdprExportProperties: ['email'],
            twoFactorAuthenticationForcedPermissions: ['admin'],
            socialNetworkClients: ['github' => ['enabled' => true]],
            mailPath: '/custom/mail',
        );
        self::assertSame(['email'], $config->gdprExportProperties);
        self::assertSame(['admin'], $config->twoFactorAuthenticationForcedPermissions);
        self::assertSame(['github' => ['enabled' => true]], $config->socialNetworkClients);
        self::assertSame('/custom/mail', $config->mailPath);
        self::assertStringContainsString('resources/views/bootstrap5', $config->viewPath);
    }

    public function testHasNumberSessionHistoryWithFalse(): void
    {
        $config = new ModuleConfig(numberSessionHistory: false);
        self::assertFalse($config->hasNumberSessionHistory());
    }

    public function testHasNumberSessionHistoryWithNegative(): void
    {
        $config = new ModuleConfig(numberSessionHistory: -1);
        self::assertFalse($config->hasNumberSessionHistory());
    }

    public function testHasNumberSessionHistoryWithPositive(): void
    {
        $config = new ModuleConfig(numberSessionHistory: 5);
        self::assertTrue($config->hasNumberSessionHistory());
    }

    public function testHasNumberSessionHistoryWithZero(): void
    {
        $config = new ModuleConfig(numberSessionHistory: 0);
        self::assertFalse($config->hasNumberSessionHistory());
    }

    public function testMailPathConcatOrder(): void
    {
        $config = new ModuleConfig();
        self::assertStringContainsString('/src/../resources/mail', str_replace('\\', '/', $config->mailPath));
    }

    public function testMailPathNotBareConstant(): void
    {
        $config = new ModuleConfig();
        $bare = '/resources/mail';
        self::assertNotSame($bare, $config->mailPath);
    }

    public function testViewPathConcatOrder(): void
    {
        $config = new ModuleConfig();
        self::assertStringContainsString('/src/../resources/views/bootstrap5', str_replace('\\', '/', $config->viewPath));
    }

    public function testViewPathNotBareConstant(): void
    {
        $config = new ModuleConfig();
        $bare = '/resources/views/bootstrap5';
        self::assertNotSame($bare, $config->viewPath);
    }
}
