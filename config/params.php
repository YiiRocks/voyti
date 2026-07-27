<?php

declare(strict_types=1);

use YiiRocks\Voyti\Console;
use YiiRocks\Voyti\Enum\EmailChangeConfirmation;
use YiiRocks\Voyti\Enum\ProfileVisibility;
use YiiRocks\Voyti\Enum\RecaptchaVersion;
use YiiRocks\Voyti\ModuleConfig;

return [
    'yiirocks/voyti' => [
        'appName' => 'Voyti',
        'recaptchaVersion' => RecaptchaVersion::V3,
        'enableGdprCompliance' => false,
        'gdprExportProperties' => [
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
        'gdprAnonymizePrefix' => 'GDPR',
        'enableTwoFactorAuthentication' => false,
        'twoFactorAuthenticationForcedPermissions' => [],
        'enableRegistration' => true,
        'enableSocialNetworkRegistration' => true,
        'enableEmailConfirmation' => true,
        'enableSwitchIdentities' => true,
        'homeRoute' => 'home',
        'mailAdminOnRegister' => null,
        'enablePasswordComplexity' => false,
        'passwordHistoryLimit' => 10,
        'allowPasswordRecovery' => true,
        'allowAdminPasswordRecovery' => false,
        'allowAccountDelete' => false,
        'emailChangeConfirmation' => EmailChangeConfirmation::NEW,
        'rememberLoginLifespan' => 2592000,
        'tokenConfirmationLifespan' => 86400,
        'tokenRecoveryLifespan' => 21600,
        'administratorPermissionName' => 'voyti-admin',
        'profileVisibility' => ProfileVisibility::USERS,
        'maxPasswordAge' => 0,
        'viewPath' => ModuleConfig::DEFAULT_VIEW_PATH,
        'mailPath' => ModuleConfig::DEFAULT_MAIL_PATH,
        'apiTokenLifespan' => 0,
        'enableAuditLog' => true,
    ],

    'yiisoft/yii-console' => [
        'commands' => [
            'voyti:create' => Console\CreateUserCommand::class,
            'voyti:delete' => Console\DeleteUserCommand::class,
            'voyti:confirm' => Console\ConfirmUserCommand::class,
            'voyti:password' => Console\PasswordCommand::class,
            'voyti:api-token:generate' => Console\GenerateApiTokenCommand::class,
            'voyti:api-token:revoke' => Console\RevokeApiTokenCommand::class,
        ],
    ],
];
