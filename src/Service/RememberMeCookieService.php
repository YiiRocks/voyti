<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Service;

use DateInterval;
use DateTimeImmutable;
use JsonException;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;
use YiiRocks\Voyti\Event\Auth\AfterLoginEvent;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Model\UserSessionHistory;
use Yiisoft\Auth\IdentityRepositoryInterface;
use Yiisoft\Cookies\Cookie;
use Yiisoft\Json\Json;
use Yiisoft\Session\SessionInterface;
use Yiisoft\User\CurrentUser;
use Yiisoft\User\Login\Cookie\CookieLogin;
use Yiisoft\User\Login\Cookie\CookieLoginIdentityInterface;

use function count;
use function is_array;
use function is_string;
use function setcookie;
use function time;

/**
 * Cookie payload is `[identityId, cookieLoginKey, expiresAt, sessionId]`. `cookieLoginKey`
 * ({@see User::getCookieLoginKey()}) is shared across all of a user's devices, so the trailing
 * sessionId is what lets {@see loginByCookie()} revoke one device's cookie (via its
 * {@see UserSessionHistory} row) without invalidating the others.
 */
final class RememberMeCookieService
{
    private \Closure $cookieEmitter;
    private \Closure $now;

    public function __construct(
        private readonly int $duration,
        private readonly string $cookieName = 'autoLogin',
        ?\Closure $cookieEmitter = null,
        ?\Closure $now = null,
        private readonly ?EventDispatcherInterface $eventDispatcher = null,
    ) {
        $this->cookieEmitter = $cookieEmitter ?? static fn (string $name, string $value, array $options): bool => setcookie($name, $value, $options);
        $this->now = $now ?? static fn (): int => time();
    }

    public function addCookie(CookieLoginIdentityInterface $identity, ResponseInterface $response, string $sessionId): ResponseInterface
    {
        $duration = $this->duration > 0 ? new DateInterval('PT' . $this->duration . 'S') : null;
        $expires = $duration !== null ? (new DateTimeImmutable())->add($duration) : null;

        $value = Json::encode([
            $identity->getId(),
            $identity->getCookieLoginKey(),
            $expires?->getTimestamp() ?? 0,
            $sessionId,
        ]);

        return (new Cookie($this->cookieName, $value, $expires))->addToResponse($response);
    }

    public function expireCookie(ResponseInterface $response): ResponseInterface
    {
        return (new CookieLogin())->withCookieName($this->cookieName)->expireCookie($response);
    }

    public function getCookieName(): string
    {
        return $this->cookieName;
    }

    /**
     * @param array<string, mixed> $cookies
     */
    public function loginByCookie(
        array $cookies,
        CurrentUser $currentUser,
        IdentityRepositoryInterface $identityRepository,
        SessionInterface $session,
    ): void {
        $now = (int) ($this->now)();
        $cookieName = $this->getCookieName();
        $cookie = $cookies[$cookieName] ?? null;

        if (!is_string($cookie)) {
            return;
        }

        $data = $this->decodeCookie($cookie);
        if ($data === null) {
            return;
        }

        [$id, $key, $expires, $cookieSessionId] = $data;
        $identity = $identityRepository->findIdentity((string) $id);

        $expiresInt = (int) $expires;
        if (
            !$identity instanceof CookieLoginIdentityInterface
            || !$identity->validateCookieLoginKey((string) $key)
            || ($expiresInt !== 0 && $expiresInt < $now)
        ) {
            return;
        }

        if (
            $identity instanceof User
            && UserSessionHistory::findByUserIdAndSessionId($identity->getIdOrZero(), (string) $cookieSessionId) === null
        ) {
            // The session this cookie was issued for was terminated (self-service or admin) - the cookie
            // must not resurrect it, even though the shared cookieLoginKey is still otherwise valid.
            return;
        }

        $previousSessionId = $session->getId();
        $currentUser->login($identity);

        if ($identity instanceof User) {
            // CurrentUser::login() regenerates the session ID (anti-fixation); dispatching
            // AfterLoginEvent here - as the interactive login flow does - keeps
            // SessionHistoryListener in sync with that new ID for auto-logins too.
            $this->eventDispatcher?->dispatch(new AfterLoginEvent($identity, previousSessionId: $previousSessionId));

            $newSessionId = $session->getId();
            if ($newSessionId !== null && $newSessionId !== '') {
                // The just-replaced history row (see UserSessionHistoryDecorator::registerLogin) now lives
                // under $newSessionId, not the sessionId this cookie still references - without re-issuing
                // the cookie here, the next time this device needs to auto-login it would fail the row-existence
                // check above, since the row it points to no longer exists.
                $this->emitCookie((string) $id, (string) $key, $expiresInt, $newSessionId);
            }
        }
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

        $now = (int) ($this->now)();

        $rawCookie = $_COOKIE[$this->cookieName] ?? null;
        if (!is_string($rawCookie)) {
            return;
        }

        $data = $this->decodeCookie($rawCookie);
        if ($data === null) {
            return;
        }

        $lastRefresh = (int) $data[2] - $this->duration;

        if ($now - $lastRefresh < 86400) {
            return;
        }

        $identity = $currentUser->getIdentity();
        if (!$identity instanceof CookieLoginIdentityInterface) {
            return;
        }

        $expiresAt = $now + $this->duration;
        $this->emitCookie((string) $identity->getId(), $identity->getCookieLoginKey(), $expiresAt, (string) $data[3]);
    }

    private function decodeCookie(string $raw): ?array
    {
        try {
            $data = Json::decode($raw);
        } catch (JsonException) {
            return null;
        }

        if (!is_array($data) || count($data) !== 4) {
            return null;
        }

        return $data;
    }

    private function emitCookie(string $id, string $key, int $expiresAt, string $sessionId): void
    {
        $value = Json::encode([$id, $key, $expiresAt, $sessionId]);

        ($this->cookieEmitter)($this->cookieName, $value, [
            'expires' => $expiresAt,
            'path' => '/',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}
