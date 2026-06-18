<?php

declare(strict_types=1);

return [
    'console-commands' => [
        'voyti:create' => \YiiRocks\Voyti\Command\CreateUserCommand::class,
        'voyti:delete' => \YiiRocks\Voyti\Command\DeleteUserCommand::class,
        'voyti:confirm' => \YiiRocks\Voyti\Command\ConfirmUserCommand::class,
        'voyti:password' => \YiiRocks\Voyti\Command\PasswordCommand::class,
    ],
];
