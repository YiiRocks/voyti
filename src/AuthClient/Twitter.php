<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\AuthClient;

final readonly class Twitter extends AbstractAuthClient
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config = [])
    {
        parent::__construct(
            'x',
            'X (formerly Twitter)',
            'https://twitter.com/i/oauth2/authorize',
            'https://api.twitter.com/2/oauth2/token',
            'https://api.twitter.com/2/users/me',
            'tweet.read users.read offline.access',
            $config,
        );
    }

    /**
     * @param array<string, mixed> $attributes
     * @param array<string, mixed> $tokenData
     *
     * @return (null|string)[]
     *
     * @psalm-return array{id: string, email: null, username: null|string, name: null|string}
     */
    #[\Override]
    protected function normalizeUserAttributes(array $attributes, array $tokenData): array
    {
        $data = $attributes['data'] ?? [];

        return [
            'id' => $this->firstString(is_array($data) ? $data : [], ['id']) ?? '',
            'email' => null,
            'username' => $this->firstString(is_array($data) ? $data : [], ['username']),
            'name' => $this->firstString(is_array($data) ? $data : [], ['name']),
        ];
    }

    /**
     * @param array<string, mixed> $tokenData
     *
     * @return string[]
     *
     * @psalm-return array{'user.fields': 'id,name,username,profile_image_url'}
     */
    #[\Override]
    protected function userInfoQuery(array $tokenData): array
    {
        return [
            'user.fields' => 'id,name,username,profile_image_url',
        ];
    }
}
