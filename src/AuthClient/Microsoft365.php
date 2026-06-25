<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\AuthClient;

final class Microsoft365 extends AbstractAuthClient
{
    public function __construct()
    {
        parent::__construct(
            'https://login.microsoftonline.com/common/oauth2/v2.0/authorize',
            'microsoft365',
            'openid userProfile email',
            'Microsoft 365',
            'https://login.microsoftonline.com/common/oauth2/v2.0/userToken',
        );
    }
}
