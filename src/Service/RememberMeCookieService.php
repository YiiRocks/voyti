<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Service;

use DateInterval;
use JsonException;
use Psr\Http\Message\ResponseInterface;
use Yiisoft\Auth\IdentityRepositoryInterface;
use Yiisoft\User\CurrentUser;
use Yiisoft\User\Login\Cookie\CookieLogin;
use Yiisoft\User\Login\Cookie\CookieLoginIdentityInterface;

use function count;
use function is_array;
use function is_string;
use function json_decode;
use function json_encode;
use function setcookie;
use function time;

final class RememberMeCookieService
{
    public function __construct(
        private readonly int $duration,
        private readonly string $cookieName = 'autoLogin',
    ) {
    }

    public function addCookie(CookieLoginIdentityInterface $identity, ResponseInterface $response): ResponseInterface
    {
        return $this
            ->cookieLogin()
            ->addCookie(
                $identity,
                $response,
                $this->duration > 0 ? new DateInterval('PT' . $this->duration . 'S') : null,
            );
    }

    /**
     * Refreshes the remember-me cookie with a new expiration timestamp
     * to implement sliding expiration.
     *
     * The cookie is refreshed at most once every 24 hours to avoid sending
     * a Set-Cookie header on every request.
     */
    public function refreshCookie(CurrentUser $currentUser): void
    {
        if ($this->duration <= 0) {
            return;
        }

        $rawCookie = $_COOKIE[$this->cookieName] ?? null;
        if (!is_string($rawCookie)) {
            return;
        }

        try {
            $data = json_decode($rawCookie, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return;
        }

        if (!is_array($data) || count($data) !== 3) {
            return;
        }

        $lastRefresh = (int) $data[2] - $this->duration;

        if (time() - $lastRefresh < 86400) {
            return;
        }

        $identity = $currentUser->getIdentity();
        if (!$identity instanceof CookieLoginIdentityInterface) {
            return;
        }

        $expiresAt = time() + $this->duration;
        $value = json_encode(
            [$identity->getId(), $identity->getCookieLoginKey(), $expiresAt],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );

        setcookie($this->cookieName, $value, [
            'expires' => $expiresAt,
            'path' => '/',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    public function expireCookie(ResponseInterface $response): ResponseInterface
    {
        return $this->cookieLogin()->expireCookie($response);
    }

    public function getCookieName(): string
    {
        return $this->cookieLogin()->getCookieName();
    }

    private function cookieLogin(): CookieLogin
    {
        return (new CookieLogin())->withCookieName($this->cookieName);
    }

    /**
     * @param array<string, mixed> $cookies
     */
    public function loginByCookie(
        array $cookies,
        CurrentUser $currentUser,
        IdentityRepositoryInterface $identityRepository,
    ): void {
        $cookieName = $this->getCookieName();
        $cookie = $cookies[$cookieName] ?? null;

        if (!is_string($cookie)) {
            return;
        }

        try {
            $data = json_decode($cookie, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return;
        }

        if (!is_array($data) || count($data) !== 3) {
            return;
        }

        [$id, $key, $expires] = $data;
        $identity = $identityRepository->findIdentity((string) $id);

        if (
            !$identity instanceof CookieLoginIdentityInterface
            || !$identity->validateCookieLoginKey((string) $key)
            || ((int) $expires !== 0 && (int) $expires < time())
        ) {
            return;
        }
        $currentUser->login($identity);
    }
}
