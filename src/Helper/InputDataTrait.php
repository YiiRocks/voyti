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
        $data = $body[$formName] ?? $body;

        return is_array($data) ? $data : [];
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @return null|string
     *
     * @infection-ignore-all ProtectedVisibility: declared protected for consistency with the rest of this
     * shared trait's API surface, even though the only current caller (AbstractAuthItemController) accesses
     * it from within the same class and would work identically if it were private.
     */
    protected function nullableStringValue(array $data, string $key): string|null
    {
        $value = $data[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
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

    protected function requestAttributeString(ServerRequestInterface $request, string $name, string $default = ''): string
    {
        $value = $request->getAttribute($name, $default);

        return is_string($value) ? $value : $default;
    }

    /**
     * @return array<array-key, mixed>
     */
    protected function sessionArray(SessionInterface $session, string $key): array
    {
        $value = $session->get($key);

        return is_array($value) ? $value : [];
    }

    /**
     * @param array<array-key, mixed> $data
     */
    protected function stringValue(array $data, string $key, string $default = ''): string
    {
        $value = $data[$key] ?? $default;

        return is_string($value) ? $value : $default;
    }
}
