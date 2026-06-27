<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\AuthClient;

final class GitHub extends AbstractAuthClient
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config = [])
    {
        parent::__construct(
            'github',
            'GitHub',
            'https://github.com/login/oauth/authorize',
            'https://github.com/login/oauth/access_token',
            'https://api.github.com/user',
            'user:email',
            $config,
        );
    }

    /**
     * @param array<string, mixed> $tokenData
     *
     * @psalm-return array<string, mixed>
     */
    #[\Override]
    protected function loadUserAttributes(array $tokenData, OAuthHttpClientInterface $httpClient): array
    {
        $attributes = parent::loadUserAttributes($tokenData, $httpClient);
        $email = $attributes['email'] ?? null;

        if (is_string($email) && $email !== '') {
            return $attributes;
        }

        $emails = $httpClient->send(
            'GET',
            'https://api.github.com/user/emails',
            $this->userInfoHeaders($tokenData),
        );

        foreach ($emails as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $candidate = $entry['email'] ?? null;
            if (!is_string($candidate) || $candidate === '') {
                continue;
            }

            $primary = (bool) ($entry['primary'] ?? false);
            $verified = (bool) ($entry['verified'] ?? false);
            if ($primary || $verified) {
                $attributes['email'] = $candidate;
                break;
            }
        }

        return $attributes;
    }
}
