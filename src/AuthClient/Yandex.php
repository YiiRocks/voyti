<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\AuthClient;

/**
 * Yandex OAuth client. Yandex uses its own attribute keys (`default_email`, `real_name`, etc.) and
 * requires a `format=json` query parameter, so `normalizeUserAttributes()` and `userInfoQuery()` are
 * overridden accordingly.
 */
final readonly class Yandex extends AbstractAuthClient
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config = [])
    {
        parent::__construct(
            'yandex',
            'Yandex',
            'https://oauth.yandex.com/authorize',
            'https://oauth.yandex.com/token',
            'https://login.yandex.ru/info',
            'login:email login:info',
            $config,
        );
    }

    /**
     * @param array<string, mixed> $attributes
     * @param array<string, mixed> $tokenData
     *
     * @return (null|string)[]
     *
     * @psalm-return array{id: string, email: null|string, username: null|string, name: null|string}
     */
    #[\Override]
    protected function normalizeUserAttributes(array $attributes, array $tokenData): array
    {
        return [
            'id' => $this->firstString($attributes, ['id']) ?? '',
            'email' => $this->firstString($attributes, ['default_email', 'email']),
            'username' => $this->firstString($attributes, ['login', 'display_name']),
            'name' => $this->firstString($attributes, ['real_name', 'display_name']),
        ];
    }

    /**
     * @param array<string, mixed> $tokenData
     *
     * @return string[]
     *
     * @psalm-return array{format: 'json'}
     */
    #[\Override]
    protected function userInfoQuery(array $tokenData): array
    {
        return [
            'format' => 'json',
        ];
    }
}
