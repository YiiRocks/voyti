<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Http;

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface as PsrClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use RuntimeException;

final class Psr18Client implements ClientInterface
{
    public function __construct(
        private readonly PsrClientInterface $httpClient,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
    ) {
    }

    #[\Override]
    public function send(
        string $method,
        string $url,
        array $headers = [],
        array $query = [],
        array $body = [],
    ): array {
        $requestUrl = $this->appendQuery($url, $query);
        $request = $this->requestFactory->createRequest(strtoupper($method), $requestUrl);

        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        if ($body !== []) {
            $request = $request
                ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
                ->withBody($this->streamFactory->createStream(http_build_query($body)));
        }

        try {
            $response = $this->httpClient->sendRequest($request);
        } catch (ClientExceptionInterface $exception) {
            throw new RuntimeException("Unable to contact OAuth provider at '{$requestUrl}'.", previous: $exception);
        }

        /** @var array<string, mixed> $data */
        $data = $this->decode((string) $response->getBody());
        $statusCode = $response->getStatusCode();

        if ($statusCode >= 400) {
            $message = $this->errorMessage($data);
            throw new RuntimeException($message !== '' ? $message : "OAuth provider request failed with status {$statusCode}.");
        }

        return $data;
    }

    private function appendQuery(string $url, array $query): string
    {
        if ($query === []) {
            return $url;
        }

        return $url . (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
    }

    /**
     * @return array<array-key, mixed>
     */
    private function decode(string $body): array
    {
        $decoded = json_decode($body, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        parse_str($body, $parsed);

        return $parsed;
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private function errorMessage(array $data): string
    {
        foreach (['error_description', 'message', 'error', 'error_summary'] as $key) {
            $value = $data[$key] ?? null;
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        $error = $data['error'] ?? null;
        if (is_array($error)) {
            $message = $error['message'] ?? null;
            if (is_string($message) && $message !== '') {
                return $message;
            }
        }

        return '';
    }
}
