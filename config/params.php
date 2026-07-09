<?php

declare(strict_types=1);

use YiiRocks\Voyti\Command;
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
            'voyti:create' => Command\CreateUserCommand::class,
            'voyti:delete' => Command\DeleteUserCommand::class,
            'voyti:confirm' => Command\ConfirmUserCommand::class,
            'voyti:password' => Command\PasswordCommand::class,
            'voyti:api-token:generate' => Command\GenerateApiTokenCommand::class,
            'voyti:api-token:revoke' => Command\RevokeApiTokenCommand::class,
        ],
    ],
];
