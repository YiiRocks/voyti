<?php

declare(strict_types=1);

use YiiRocks\Voyti\ModuleConfig;

return [
    'yiisoft/aliases' => [
        'aliases' => [
            '@voyti' => dirname(__DIR__) . '/src',
            '@voytiViews' => '@voyti/resources/views/bootstrap5',
            '@voytiMail' => '@voyti/resources/mail',
        ],
    ],

    'yiisoft/view' => [
        'theme' => [
            'pathMap' => [
                '@voytiViews' => ['@voyti/resources/views/bootstrap5'],
                '@voytiMail' => ['@voyti/resources/mail'],
            ],
        ],
    ],

    YiiRocks\Voyti\ModuleConfig::class => new ModuleConfig(),

    'yiisoft/yii-console' => [
        'commands' => [
            'voyti:create' => \YiiRocks\Voyti\Command\CreateUserCommand::class,
            'voyti:delete' => \YiiRocks\Voyti\Command\DeleteUserCommand::class,
            'voyti:confirm' => \YiiRocks\Voyti\Command\ConfirmUserCommand::class,
            'voyti:password' => \YiiRocks\Voyti\Command\PasswordCommand::class,
        ],
    ],
];
