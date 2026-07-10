<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\AuthClient;

use YiiRocks\Voyti\Http\ClientInterface;
use Yiisoft\Http\Method;

final readonly class GitHub extends AbstractAuthClient
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
    protected function loadUserAttributes(array $tokenData, ClientInterface $httpClient): array
    {
        $attributes = parent::loadUserAttributes($tokenData, $httpClient);

        if (is_string($attributes['email'] ?? null) && $attributes['email'] !== '') {
            return $attributes;
        }

        $emails = $httpClient->send(
            Method::GET,
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

            /** @var bool $primary */
            $primary = $entry['primary'] ?? false;
            /** @var bool $verified */
            $verified = $entry['verified'] ?? false;
            if ($primary || $verified) {
                $attributes['email'] = $candidate;
                break;
            }
        }

        return $attributes;
    }
}
