<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\AuthClient;

interface OAuthHttpClientInterface
{
    /**
     * @param array<string, string> $headers
     * @param array<string, string> $query
     * @param array<string, string> $body
     *
     * @return array<string, mixed>
     */
    public function send(
        string $method,
        string $url,
        array $headers = [],
        array $query = [],
        array $body = [],
    ): array;
}
