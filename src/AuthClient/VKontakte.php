<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\AuthClient;

final class VKontakte extends AbstractAuthClient
{
    public function __construct()
    {
        parent::__construct(
            'https://oauth.vk.com/authorize',
            'vkontakte',
            'email',
            'VKontakte',
            'https://oauth.vk.com/access_token',
        );
    }
}
