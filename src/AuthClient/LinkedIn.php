<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\AuthClient;

final class LinkedIn extends AbstractAuthClient
{
    public function __construct()
    {
        parent::__construct(
            'https://www.linkedin.com/uas/oauth2/authorization',
            'linkedin',
            'r_emailaddress r_liteprofile',
            'LinkedIn',
            'https://www.linkedin.com/uas/oauth2/accessToken',
        );
    }
}
