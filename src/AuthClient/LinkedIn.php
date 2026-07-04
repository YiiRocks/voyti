<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\AuthClient;

final readonly class LinkedIn extends AbstractAuthClient
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config = [])
    {
        parent::__construct(
            'linkedin',
            'LinkedIn',
            'https://www.linkedin.com/oauth/v2/authorization',
            'https://www.linkedin.com/oauth/v2/accessToken',
            'https://api.linkedin.com/v2/userinfo',
            'openid profile email',
            $config,
        );
    }
}
