<?php

declare(strict_types=1);

use YiiRocks\Voyti\Console;
use YiiRocks\Voyti\Enum\EmailChangeConfirmation;
use YiiRocks\Voyti\Enum\ProfileVisibility;
use YiiRocks\Voyti\Enum\RecaptchaVersion;
use YiiRocks\Voyti\VoytiConfig;

return [
    'yiirocks/voyti' => [
        'appName' => 'Voyti',
        'recaptchaVersion' => RecaptchaVersion::V3,
        'accountMenuItems' => [],
        'enableRegistration' => true,
        'enableEmailConfirmation' => true,
        'enablePasswordComplexity' => false,
        'enableRecommendations' => true,
        'enableSwitchIdentities' => true,
        'homeRoute' => 'home',
        'mailAdminOnRegister' => null,
        'passwordHistoryLimit' => 10,
        'allowPasswordRecovery' => true,
        'allowAdminPasswordRecovery' => false,
        'allowAccountDelete' => false,
        'privacyMenuItems' => [],
        'emailChangeConfirmation' => EmailChangeConfirmation::NEW,
        'rememberLoginLifespan' => 2592000,
        'tokenConfirmationLifespan' => 86400,
        'tokenRecoveryLifespan' => 21600,
        'administratorPermissionName' => 'voyti-admin',
        'profileVisibility' => ProfileVisibility::USERS,
        'maxPasswordAge' => 0,
        'viewPath' => null,
        'mailPath' => VoytiConfig::DEFAULT_MAIL_PATH,
        'enableAuditLog' => true,
        'rememberMeCookieDomain' => null,
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
