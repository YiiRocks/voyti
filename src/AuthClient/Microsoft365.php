<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\AuthClient;

final readonly class Microsoft365 extends AbstractAuthClient
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config = [])
    {
        parent::__construct(
            'microsoft365',
            'Microsoft 365',
            'https://login.microsoftonline.com/common/oauth2/v2.0/authorize',
            'https://login.microsoftonline.com/common/oauth2/v2.0/token',
            'https://graph.microsoft.com/oidc/userinfo',
            'openid profile email User.Read',
            $config,
        );
    }
}
