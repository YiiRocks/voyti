<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Helper;

use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\Session\SessionInterface;

trait InputDataTrait
{

    /**
     * @param array<array-key, mixed> $body
     *
     * @return array<array-key, mixed>
     */
    protected function formData(array $body, string $formName): array
    {
        /** @var mixed $data */
        $data = $body[$formName] ?? $body;

        return is_array($data) ? $data : [];
    }
    /**
     * @return array<array-key, mixed>
     */
    protected function parsedBody(ServerRequestInterface $request): array
    {
        $body = $request->getParsedBody();

        return is_array($body) ? $body : [];
    }

    /**
     * @return array<array-key, mixed>
     */
    protected function queryParams(ServerRequestInterface $request): array
    {
        return $request->getQueryParams();
    }

    /**
     * @param array<array-key, mixed> $data
     */
    protected function stringValue(array $data, string $key, string $default = ''): string
    {
        /** @var mixed $value */
        $value = $data[$key] ?? $default;

        return is_string($value) ? $value : $default;
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @return null|string
     */
    private function nullableStringValue(array $data, string $key): string|null
    {
        /** @var mixed $value */
        $value = $data[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function requestAttributeString(ServerRequestInterface $request, string $name, string $default = ''): string
    {
        /** @var mixed $value */
        $value = $request->getAttribute($name, $default);

        return is_string($value) ? $value : $default;
    }

    private function sessionArray(SessionInterface $session, string $key): array
    {
        /** @var mixed $value */
        $value = $session->get($key);

        return is_array($value) ? $value : [];
    }
}
