<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\AuthClient;

final class VKontakte
{
    public function getName(): string
    {
        return 'vkontakte';
    }

    public function getTitle(): string
    {
        return 'VKontakte';
    }

    public function getAuthUrl(): string
    {
        return 'https://oauth.vk.com/authorize';
    }

    public function getTokenUrl(): string
    {
        return 'https://oauth.vk.com/access_token';
    }

    public function getScope(): string
    {
        return 'email';
    }
}
