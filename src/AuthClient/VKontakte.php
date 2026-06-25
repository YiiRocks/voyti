<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\AuthClient;

final class VKontakte
{

    public function getAuthUrl(): string
    {
        return 'https://oauth.vk.com/authorize';
    }
    public function getName(): string
    {
        return 'vkontakte';
    }

    public function getScope(): string
    {
        return 'email';
    }

    public function getTitle(): string
    {
        return 'VKontakte';
    }

    public function getTokenUrl(): string
    {
        return 'https://oauth.vk.com/access_token';
    }
}
