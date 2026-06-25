<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\AuthClient;

final class Yandex extends AbstractAuthClient
{
    public function __construct()
    {
        parent::__construct(
            'https://oauth.yandex.com/authorize',
            'yandex',
            '',
            'Yandex',
            'https://oauth.yandex.com/userToken',
        );
    }
}
