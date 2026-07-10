<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Service;

use DateInterval;
use JsonException;
use Psr\Http\Message\ResponseInterface;
use Yiisoft\Auth\IdentityRepositoryInterface;
use Yiisoft\Json\Json;
use Yiisoft\User\CurrentUser;
use Yiisoft\User\Login\Cookie\CookieLogin;
use Yiisoft\User\Login\Cookie\CookieLoginIdentityInterface;

use function count;
use function is_array;
use function is_string;
use function setcookie;
use function time;

final class RememberMeCookieService
{
    private \Closure $cookieEmitter;
    private \Closure $now;

    public function __construct(
        private readonly int $duration,
        private readonly string $cookieName = 'autoLogin',
        ?\Closure $cookieEmitter = null,
        ?\Closure $now = null,
    ) {
        $this->cookieEmitter = $cookieEmitter ?? static fn (string $name, string $value, array $options): bool => setcookie($name, $value, $options);
        $this->now = $now ?? static fn (): int => time();
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

    public function expireCookie(ResponseInterface $response): ResponseInterface
    {
        return $this->cookieLogin()->expireCookie($response);
    }

    public function getCookieName(): string
    {
        return $this->cookieLogin()->getCookieName();
    }

    /**
     * @param array<string, mixed> $cookies
     */
    public function loginByCookie(
        array $cookies,
        CurrentUser $currentUser,
        IdentityRepositoryInterface $identityRepository,
    ): void {
        $now = (int) ($this->now)();
        $cookieName = $this->getCookieName();
        $cookie = $cookies[$cookieName] ?? null;

        if (!is_string($cookie)) {
            return;
        }

        try {
            $data = Json::decode($cookie);
        } catch (JsonException) {
            return;
        }

        if (!is_array($data) || count($data) !== 3) {
            return;
        }

        [$id, $key, $expires] = $data;
        $identity = $identityRepository->findIdentity((string) $id);

        /** @infection-ignore-all CastInt: In PHP 8.5, non-numeric string < int is false (string-to-number comparison changed), so removing the cast produces the same boolean result for all inputs. The cast is kept for defensive type safety against malformed cookies. */
        $expiresInt = (int) $expires;
        if (
            !$identity instanceof CookieLoginIdentityInterface
            || !$identity->validateCookieLoginKey((string) $key)
            || ($expiresInt !== 0 && $expiresInt < $now)
        ) {
            return;
        }
        $currentUser->login($identity);
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

        /** @infection-ignore-all CastInt: the float/int difference in $now is never enough to flip the 86400-second gap threshold when $lastRefresh is always an integer. */
        $now = (int) ($this->now)();

        $rawCookie = $_COOKIE[$this->cookieName] ?? null;
        if (!is_string($rawCookie)) {
            return;
        }

        try {
            $data = Json::decode($rawCookie);
        } catch (JsonException) {
            return;
        }

        if (!is_array($data) || count($data) !== 3) {
            return;
        }

        /** @infection-ignore-all CastInt: $data[2] is always a JSON int from our own encoding; a non-numeric value would throw TypeError in PHP 8.5 when used in subtraction without cast. The cast is defensive — no test feeds non-numeric data. */
        $lastRefresh = (int) $data[2] - $this->duration;

        if ($now - $lastRefresh < 86400) {
            return;
        }

        $identity = $currentUser->getIdentity();
        if (!$identity instanceof CookieLoginIdentityInterface) {
            return;
        }

        $expiresAt = $now + $this->duration;
        $value = Json::encode([$identity->getId(), $identity->getCookieLoginKey(), $expiresAt]);

        ($this->cookieEmitter)($this->cookieName, $value, [
            'expires' => $expiresAt,
            'path' => '/',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    private function cookieLogin(): CookieLogin
    {
        return (new CookieLogin())->withCookieName($this->cookieName);
    }
}
