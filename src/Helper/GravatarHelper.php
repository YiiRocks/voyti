<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Helper;

final class GravatarHelper
{
    private const BASE_URL = 'https://www.gravatar.com/avatar/';

    public function buildId(string $email): string
    {
        return md5(strtolower(trim($email)));
    }

    public function getUrl(string $gravatarId, int $size = 200): string
    {
        return self::BASE_URL . $gravatarId . '?s=' . $size . '&d=identicon';
    }
}
