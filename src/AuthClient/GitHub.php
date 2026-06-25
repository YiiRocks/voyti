<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\AuthClient;

final class GitHub extends AbstractAuthClient
{
    public function __construct()
    {
        parent::__construct(
            'https://github.com/login/oauth/authorize',
            'github',
            'user:email',
            'GitHub',
            'https://github.com/login/oauth/access_token',
        );
    }
}
