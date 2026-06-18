<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\AuthClient;

final class Yandex
{

    public function getAuthUrl(): string
    {
        return 'https://oauth.yandex.com/authorize';
    }
    public function getName(): string
    {
        return 'yandex';
    }

    public function getScope(): string
    {
        return '';
    }

    public function getTitle(): string
    {
        return 'Yandex';
    }

    public function getTokenUrl(): string
    {
        return 'https://oauth.yandex.com/token';
    }
}
