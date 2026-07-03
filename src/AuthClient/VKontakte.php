<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\AuthClient;

final class VKontakte extends AbstractAuthClient
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config = [])
    {
        parent::__construct(
            'vkontakte',
            'VKontakte',
            'https://oauth.vk.com/authorize',
            'https://oauth.vk.com/access_token',
            'https://api.vk.com/method/users.get',
            'email',
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
        $response = $attributes['response'] ?? [];
        /** @var array $account */
        $account = is_array($response) && isset($response[0]) && is_array($response[0]) ? $response[0] : [];
        $firstName = $this->firstString($account, ['first_name']);
        $lastName = $this->firstString($account, ['last_name']);

        return [
            'id' => $this->firstString($account, ['id']) ?? $this->firstString($tokenData, ['user_id']) ?? '',
            'email' => $this->firstString($tokenData, ['email']),
            'username' => $this->firstString($account, ['screen_name']),
            'name' => trim(implode(' ', array_filter([$firstName, $lastName]))) ?: null,
        ];
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
     * @return string[]
     *
     * @psalm-return array{access_token: string, fields: 'screen_name', v: '5.199'}
     */
    #[\Override]
    protected function userInfoQuery(array $tokenData): array
    {
        return [
            'access_token' => (string) ($tokenData['access_token'] ?? ''),
            'fields' => 'screen_name',
            'v' => '5.199',
        ];
    }
}
