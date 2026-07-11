<?php

declare(strict_types=1);

use YiiRocks\Voyti\Console;
use YiiRocks\Voyti\ModuleConfig;
use Yiisoft\Yii\View\Renderer\CsrfViewInjection;

return [
    'yiirocks/voyti' => ModuleConfig::defaults(),

    'yiisoft/yii-view-renderer' => [
        'injections' => [
            CsrfViewInjection::class,
        ],
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
