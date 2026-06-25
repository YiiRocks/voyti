<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\AuthClient;

final class LinkedIn
{

    public function getAuthUrl(): string
    {
        return 'https://www.linkedin.com/uas/oauth2/authorization';
    }
    public function getName(): string
    {
        return 'linkedin';
    }

    public function getScope(): string
    {
        return 'r_emailaddress r_liteprofile';
    }

    public function getTitle(): string
    {
        return 'LinkedIn';
    }

    public function getTokenUrl(): string
    {
        return 'https://www.linkedin.com/uas/oauth2/accessToken';
    }
}
