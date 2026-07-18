<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\AuthClient;

/**
 * Facebook OAuth client. Facebook requires the access token and requested fields as query parameters
 * rather than an `Authorization` header, so `userInfoHeaders()`/`userInfoQuery()` are overridden.
 */
final readonly class Facebook extends AbstractAuthClient
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config = [])
    {
        parent::__construct(
            'facebook',
            'Facebook',
            'https://www.facebook.com/v19.0/dialog/oauth',
            'https://graph.facebook.com/v19.0/oauth/access_token',
            'https://graph.facebook.com/me',
            'email',
            $config,
        );
    }

    /**
     * @param array<string, mixed> $tokenData
     *
     * @return string[]
     *
     * @psalm-return array<string, string>
     */
    #[\Override]
    protected function userInfoHeaders(array $tokenData): array
    {
        return [
            'Accept' => 'application/json',
            'User-Agent' => 'YiiRocks Voyti',
        ];
    }

    /**
     * @param array<string, mixed> $tokenData
     *
     * @return array<string, string>
     */
    #[\Override]
    protected function userInfoQuery(array $tokenData): array
    {
        return [
            'access_token' => (string) ($tokenData['access_token'] ?? ''),
            'fields' => 'id,name,email',
        ];
    }
}
