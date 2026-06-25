<?php

declare(strict_types=1);

use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Command;

return [
    YiiRocks\Voyti\ModuleConfig::class => new ModuleConfig(),

    'yiisoft/yii-console' => [
        'commands' => [
            'voyti:create' => Command\CreateUserCommand::class,
            'voyti:delete' => Command\DeleteUserCommand::class,
            'voyti:confirm' => Command\ConfirmUserCommand::class,
            'voyti:password' => Command\PasswordCommand::class,
        ],
    ],
];
