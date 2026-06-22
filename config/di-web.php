<?php

declare(strict_types=1);

use Yiisoft\Session\SessionInterface;
use Yiisoft\User\CurrentUser;

return [
    CurrentUser::class => [
        'withSession()' => [SessionInterface::class],
    ],
];
