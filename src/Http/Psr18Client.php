<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Http;

use Override;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface as PsrClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use RuntimeException;
use Yiisoft\Http\Header;

/**
 * PSR-18 backed implementation of {@see ClientInterface}: builds the request via PSR-17
 * factories, sends it, and decodes the response body as JSON or, failing that,
 * `application/x-www-form-urlencoded` data - some OAuth providers reply in that format.
 */
final readonly class Psr18Client implements ClientInterface
{
    public function __construct(
        private PsrClientInterface $httpClient,
        private RequestFactoryInterface $requestFactory,
        private StreamFactoryInterface $streamFactory,
    ) {}

    #[Override]
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
                ->withHeader(Header::CONTENT_TYPE, 'application/x-www-form-urlencoded')
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
            throw new RuntimeException(
                $message !== '' ? $message : "OAuth provider request failed with status {$statusCode}.",
            );
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
        /** @var mixed $decoded */
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
            /** @var mixed $rawValue */
            $rawValue = $data[$key] ?? null;
            if (is_string($rawValue) && $rawValue !== '') {
                return $rawValue;
            }
        }

        /** @var mixed $error */
        $error = $data['error'] ?? null;
        if (is_array($error)) {
            /** @var mixed $rawMessage */
            $rawMessage = $error['message'] ?? null;
            if (is_string($rawMessage) && $rawMessage !== '') {
                return $rawMessage;
            }
        }

        return '';
    }
}
