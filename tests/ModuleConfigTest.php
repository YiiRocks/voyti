<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Enum\EmailChangeConfirmation;
use YiiRocks\Voyti\Enum\ProfileVisibility;
use YiiRocks\Voyti\Enum\RecaptchaVersion;
use YiiRocks\Voyti\ModuleConfig;

final class ModuleConfigTest extends TestCase
{
    public function testConstructorAssignsAllProperties(): void
    {
        $config = new ModuleConfig(
            appName: 'Custom',
            recaptchaVersion: RecaptchaVersion::V3,
            enableGdprCompliance: true,
            gdprExportProperties: ['email'],
            gdprAnonymizePrefix: 'ANON',
            enableTwoFactorAuthentication: true,
            twoFactorAuthenticationForcedPermissions: ['admin'],
            enableRegistration: false,
            enableSocialNetworkRegistration: false,
            socialNetworkClients: ['github' => ['enabled' => true]],
            enableEmailConfirmation: false,
            enableSwitchIdentities: false,
            homeRoute: 'custom/home',
            mailAdminOnRegister: 'admin@example.com',
            enablePasswordExpiration: true,
            enablePasswordComplexity: true,
            passwordHistoryLimit: 5,
            allowPasswordRecovery: false,
            allowAdminPasswordRecovery: true,
            allowAccountDelete: true,
            emailChangeConfirmation: EmailChangeConfirmation::BOTH,
            rememberLoginLifespan: 3600,
            tokenConfirmationLifespan: 7200,
            tokenRecoveryLifespan: 1800,
            administratorPermissionName: 'superadmin',
            profileVisibility: ProfileVisibility::ADMIN,
            maxPasswordAge: 90,
            viewPath: '/custom/view',
            mailPath: '/custom/mail',
            enableRestApi: true,
            adminRestPrefix: 'custom-api',
            apiTokenLifespan: 3600,
            enableAuditLog: false,
        );

        self::assertSame('Custom', $config->appName);
        self::assertSame(RecaptchaVersion::V3, $config->recaptchaVersion);
        self::assertTrue($config->enableGdprCompliance);
        self::assertSame(['email'], $config->gdprExportProperties);
        self::assertSame('ANON', $config->gdprAnonymizePrefix);
        self::assertTrue($config->enableTwoFactorAuthentication);
        self::assertSame(['admin'], $config->twoFactorAuthenticationForcedPermissions);
        self::assertFalse($config->enableRegistration);
        self::assertFalse($config->enableSocialNetworkRegistration);
        self::assertSame(['github' => ['enabled' => true]], $config->socialNetworkClients);
        self::assertFalse($config->enableEmailConfirmation);
        self::assertFalse($config->enableSwitchIdentities);
        self::assertSame('custom/home', $config->homeRoute);
        self::assertSame('admin@example.com', $config->mailAdminOnRegister);
        self::assertTrue($config->enablePasswordExpiration);
        self::assertTrue($config->enablePasswordComplexity);
        self::assertSame(5, $config->passwordHistoryLimit);
        self::assertFalse($config->allowPasswordRecovery);
        self::assertTrue($config->allowAdminPasswordRecovery);
        self::assertTrue($config->allowAccountDelete);
        self::assertSame(EmailChangeConfirmation::BOTH, $config->emailChangeConfirmation);
        self::assertSame(3600, $config->rememberLoginLifespan);
        self::assertSame(7200, $config->tokenConfirmationLifespan);
        self::assertSame(1800, $config->tokenRecoveryLifespan);
        self::assertSame('superadmin', $config->administratorPermissionName);
        self::assertSame(ProfileVisibility::ADMIN, $config->profileVisibility);
        self::assertSame(90, $config->maxPasswordAge);
        self::assertSame('/custom/view', $config->viewPath);
        self::assertSame('/custom/mail', $config->mailPath);
        self::assertTrue($config->enableRestApi);
        self::assertSame('custom-api', $config->adminRestPrefix);
        self::assertSame(3600, $config->apiTokenLifespan);
        self::assertFalse($config->enableAuditLog);
    }

    public function testDefaultViewPathIsRelativeToPackageRoot(): void
    {
        self::assertStringContainsString(
            '/src/../resources/views/bootstrap5',
            str_replace('\\', '/', ModuleConfig::DEFAULT_VIEW_PATH),
        );
        self::assertNotSame('/resources/views/bootstrap5', ModuleConfig::DEFAULT_VIEW_PATH);
    }

}
