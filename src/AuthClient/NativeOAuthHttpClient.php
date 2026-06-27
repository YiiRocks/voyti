<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\AuthClient;

use RuntimeException;

final class NativeOAuthHttpClient implements OAuthHttpClientInterface
{
    #[\Override]
    public function send(
        string $method,
        string $url,
        array $headers = [],
        array $query = [],
        array $body = [],
    ): array {
        $method = strtoupper($method);
        $requestUrl = $this->appendQuery($url, $query);
        $headerLines = [];
        foreach ($headers as $name => $value) {
            $headerLines[] = $name . ': ' . $value;
        }

        $context = [
            'method' => $method,
            'header' => implode("\r\n", $headerLines),
            'ignore_errors' => true,
            'timeout' => 15,
        ];

        if ($body !== []) {
            $context['header'] = trim($context['header'] . "\r\nContent-Type: application/x-www-form-urlencoded");
            $context['content'] = http_build_query($body);
        }

        $resource = @file_get_contents(
            $requestUrl,
            false,
            stream_context_create(['http' => $context]),
        );

        if ($resource === false) {
            throw new RuntimeException("Unable to contact OAuth provider at '{$requestUrl}'.");
        }

        /** @var list<string> $http_response_header */
        $responseHeaders = $http_response_header ?? [];
        $statusCode = $this->statusCode($responseHeaders);
        /** @var array<string, mixed> $data */
        $data = $this->decode($resource);

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
     * @param array $data
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

    /**
     * @param list<string> $headers
     */
    private function statusCode(array $headers): int
    {
        $statusLine = $headers[0] ?? '';
        if (preg_match('/\s(\d{3})\s/', $statusLine, $matches) === 1) {
            return (int) $matches[1];
        }

        return 200;
    }
}
