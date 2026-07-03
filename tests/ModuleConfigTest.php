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
            enableGdprCompliance: true,
            gdprExportProperties: ['email'],
            gdprAnonymizePrefix: 'ANON',
            enableTwoFactorAuthentication: true,
            twoFactorAuthenticationForcedPermissions: ['admin'],
            enableRegistration: false,
            enableSocialNetworkRegistration: false,
            enableEmailConfirmation: false,
            enableSwitchIdentities: false,
            switchIdentitySessionKey: 'custom_key',
            loginRoute: 'custom/login',
            accountSettingsRoute: 'custom/settings-account',
            mailAdminOnRegister: 'admin@example.com',
            enablePasswordExpiration: true,
            generatePasswords: true,
            allowPasswordRecovery: false,
            allowAdminPasswordRecovery: false,
            allowAccountDelete: true,
            emailChangeStrategy: 2,
            rememberLoginLifespan: 3600,
            tokenConfirmationLifespan: 1800,
            tokenRecoveryLifespan: 900,
            administratorPermissionName: 'admin',
            profileVisibility: 1,
            maxPasswordAge: 90,
            disableIpLogging: true,
            viewPath: '/custom/views',
            mailPath: '/custom/mail',
            enableRestApi: true,
            adminRestPrefix: 'admin/api/v2',
        );

        $this->assertSame('v2', $config->recaptchaVersion);
        $this->assertTrue($config->enableSessionHistory);
        $this->assertSame(100, $config->numberSessionHistory);
        $this->assertTrue($config->enableGdprCompliance);
        $this->assertSame(['email'], $config->gdprExportProperties);
        $this->assertSame('ANON', $config->gdprAnonymizePrefix);
        $this->assertTrue($config->enableTwoFactorAuthentication);
        $this->assertSame(['admin'], $config->twoFactorAuthenticationForcedPermissions);
        $this->assertFalse($config->enableRegistration);
        $this->assertFalse($config->enableSocialNetworkRegistration);
        $this->assertFalse($config->enableEmailConfirmation);
        $this->assertFalse($config->enableSwitchIdentities);
        $this->assertSame('custom_key', $config->switchIdentitySessionKey);
        $this->assertSame('custom/login', $config->loginRoute);
        $this->assertSame('custom/settings-account', $config->accountSettingsRoute);
        $this->assertSame('admin@example.com', $config->mailAdminOnRegister);
        $this->assertTrue($config->enablePasswordExpiration);
        $this->assertTrue($config->generatePasswords);
        $this->assertFalse($config->allowPasswordRecovery);
        $this->assertFalse($config->allowAdminPasswordRecovery);
        $this->assertTrue($config->allowAccountDelete);
        $this->assertSame(2, $config->emailChangeStrategy);
        $this->assertSame(3600, $config->rememberLoginLifespan);
        $this->assertSame(1800, $config->tokenConfirmationLifespan);
        $this->assertSame(900, $config->tokenRecoveryLifespan);
        $this->assertSame('admin', $config->administratorPermissionName);
        $this->assertSame(1, $config->profileVisibility);
        $this->assertSame(90, $config->maxPasswordAge);
        $this->assertTrue($config->disableIpLogging);
        $this->assertSame('/custom/views', $config->viewPath);
        $this->assertSame('/custom/mail', $config->mailPath);
        $this->assertTrue($config->enableRestApi);
        $this->assertSame('admin/api/v2', $config->adminRestPrefix);
    }
    public function testDefaultValues(): void
    {
        $config = new ModuleConfig();

        $this->assertNull($config->recaptchaVersion);
        $this->assertTrue($config->enableSessionHistory);
        $this->assertSame(50, $config->numberSessionHistory);
        $this->assertFalse($config->enableGdprCompliance);
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
        $this->assertFalse($config->enableTwoFactorAuthentication);
        $this->assertSame([], $config->twoFactorAuthenticationForcedPermissions);
        $this->assertTrue($config->enableRegistration);
        $this->assertTrue($config->enableSocialNetworkRegistration);
        $this->assertTrue($config->enableEmailConfirmation);
        $this->assertTrue($config->enableSwitchIdentities);
        $this->assertSame('voyti_original_user', $config->switchIdentitySessionKey);
        $this->assertSame('voyti/login', $config->loginRoute);
        $this->assertSame('voyti/settings-account', $config->accountSettingsRoute);
        $this->assertNull($config->mailAdminOnRegister);
        $this->assertFalse($config->enablePasswordExpiration);
        $this->assertFalse($config->generatePasswords);
        $this->assertTrue($config->allowPasswordRecovery);
        $this->assertTrue($config->allowAdminPasswordRecovery);
        $this->assertFalse($config->allowAccountDelete);
        $this->assertSame(1, $config->emailChangeStrategy);
        $this->assertSame(2592000, $config->rememberLoginLifespan);
        $this->assertSame(86400, $config->tokenConfirmationLifespan);
        $this->assertSame(21600, $config->tokenRecoveryLifespan);
        $this->assertSame('admin', $config->administratorPermissionName);
        $this->assertSame(2, $config->profileVisibility);
        $this->assertNull($config->maxPasswordAge);
        $this->assertFalse($config->disableIpLogging);
        $this->assertSame(
            str_replace('\\', '/', dirname(__DIR__) . '/src/resources/views/bootstrap5'),
            str_replace('\\', '/', $config->viewPath),
        );
        $this->assertSame(
            str_replace('\\', '/', dirname(__DIR__) . '/src/resources/mail'),
            str_replace('\\', '/', $config->mailPath),
        );
        $this->assertFalse($config->enableRestApi);
        $this->assertSame('api/v1', $config->adminRestPrefix);
    }

    public function testFromArray(): void
    {
        $config = ModuleConfig::fromArray([
            'enableRestApi' => true,
            'adminRestPrefix' => 'admin/api',
            'socialNetworkClients' => [
                'github' => [
                    'clientId' => 'client-id',
                    'clientSecret' => 'client-secret',
                ],
            ],
        ]);

        $this->assertTrue($config->enableRestApi);
        $this->assertSame('admin/api', $config->adminRestPrefix);
        $this->assertSame([
            'github' => [
                'clientId' => 'client-id',
                'clientSecret' => 'client-secret',
            ],
        ], $config->socialNetworkClients);
        $this->assertTrue($config->enableRegistration);
    }

    public function testFromArrayIgnoresLegacyUnknownOptions(): void
    {
        $config = ModuleConfig::fromArray([
            'autoRegisterRoutes' => false,
            'enableRestApi' => true,
        ]);

        $this->assertTrue($config->enableRestApi);
        $this->assertTrue($config->enableRegistration);
    }

    public function testHasNumberSessionHistory(): void
    {
        $config = new ModuleConfig();
        $this->assertTrue($config->hasNumberSessionHistory());

        $config = new ModuleConfig(numberSessionHistory: false);
        $this->assertFalse($config->hasNumberSessionHistory());

        $config = new ModuleConfig(numberSessionHistory: 0);
        $this->assertFalse($config->hasNumberSessionHistory());

        $config = new ModuleConfig(numberSessionHistory: 5);
        $this->assertTrue($config->hasNumberSessionHistory());
    }

    public function testPackageParamsExposeDefaults(): void
    {
        $params = require dirname(__DIR__) . '/config/params.php';

        $this->assertSame(ModuleConfig::defaults(), $params['yiirocks/voyti']);
        $this->assertArrayNotHasKey(ModuleConfig::class, $params);
    }
}
