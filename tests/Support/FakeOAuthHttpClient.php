<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Support;

use YiiRocks\Voyti\AuthClient\OAuthHttpClientInterface;

final class FakeOAuthHttpClient implements OAuthHttpClientInterface
{
    /** @var array<string, array<string, mixed>> */
    private array $responses = [];

    /**
     * @param array<string, mixed> $response
     */
    public function queue(string $method, string $url, array $response): void
    {
        $this->responses[strtoupper($method) . ' ' . $url] = $response;
    }

    #[\Override]
    public function send(
        string $method,
        string $url,
        array $headers = [],
        array $query = [],
        array $body = [],
    ): array {
        $requestUrl = $url;
        if ($query !== []) {
            $requestUrl .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
        }

        $key = strtoupper($method) . ' ' . $requestUrl;

        return $this->responses[$key] ?? [];
    }
}
