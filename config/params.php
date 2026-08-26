<?php

declare(strict_types=1);

use YiiRocks\Voyti\Console;
use YiiRocks\Voyti\Enum\EmailChangeConfirmation;
use YiiRocks\Voyti\Enum\ProfileVisibility;
use YiiRocks\Voyti\Enum\RecaptchaVersion;
use YiiRocks\Voyti\VoytiConfig;

return [
    'yiirocks/voyti' => [
        // General
        'appName' => 'Voyti',
        'homeRoute' => 'home',
        // Authentication & Registration
        'enableRegistration' => true,
        'enableEmailConfirmation' => true,
        'allowPasswordRecovery' => true,
        'allowAdminPasswordRecovery' => false,
        'allowAccountDelete' => false,
        'emailChangeConfirmation' => EmailChangeConfirmation::NEW,
        'rememberLoginLifespan' => 2592000,
        'tokenConfirmationLifespan' => 86400,
        'tokenRecoveryLifespan' => 21600,
        'enableSwitchIdentities' => true,
        'mailAdminOnRegister' => null,
        'recaptchaVersion' => RecaptchaVersion::V3,
        // Session & Security
        'maxPasswordAge' => 0,
        'enablePasswordComplexity' => false,
        'passwordHistoryLimit' => 10,
        'administratorPermissionName' => 'voyti-admin',
        'profileVisibility' => ProfileVisibility::USERS,
        'enableAuditLog' => true,
        'rememberMeCookieDomain' => null,
        // Views & Mail
        'viewPath' => null,
        'mailPath' => VoytiConfig::DEFAULT_MAIL_PATH,
        // Admin Dashboard
        'enableRecommendations' => true,
        // Contributed by other packages - not meant to be set by host apps directly
        'accountMenuItems' => [],
        'privacyMenuItems' => [],
        'viewsPackagePaths' => [],
    ],

    'yiisoft/yii-console' => [
        'commands' => [
            'voyti:create' => Console\CreateUserCommand::class,
            'voyti:delete' => Console\DeleteUserCommand::class,
            'voyti:confirm' => Console\ConfirmUserCommand::class,
            'voyti:password' => Console\PasswordCommand::class,
        ],
    ],
];
