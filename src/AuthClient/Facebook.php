<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\AuthClient;

final class Facebook extends AbstractAuthClient
{
    public function __construct()
    {
        parent::__construct(
            'https://www.facebook.com/dialog/oauth',
            'facebook',
            'email',
            'Facebook',
            'https://graph.facebook.com/v2.0/oauth/access_token',
        );
    }
}
