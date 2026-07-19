<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Service;

use DateTimeImmutable;
use JsonException;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;
use YiiRocks\Voyti\Event\Auth\AfterLoginEvent;
use YiiRocks\Voyti\Middleware\RememberMeMiddleware;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Model\UserSessions;
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

/**
 * Cookie payload is `[identityId, cookieLoginKey, expiresAt, sessionId]`. `cookieLoginKey`
 * ({@see User::getCookieLoginKey()}) is shared across all of a user's devices, so the trailing
 * sessionId is what lets {@see loginByCookie()} revoke one device's cookie (via its
 * {@see UserSessions} row) without invalidating the others.
 *
 * All cookie writes go through a PSR-7 {@see ResponseInterface}. {@see loginByCookie()} only
 * authenticates and reports whether a reissue is needed; {@see RememberMeMiddleware}
 * is what writes the cookie back.
 */
final class RememberMeCookieService
{
    public function __construct(
        private readonly int $duration,
        private readonly ClockInterface $clock,
        private readonly string $cookieName = 'autoLogin',
        private readonly ?EventDispatcherInterface $eventDispatcher = null,
    ) {}

    public function addCookie(
        CookieLoginIdentityInterface $identity,
        ResponseInterface $response,
        string $sessionId,
    ): ResponseInterface {
        $expiresAt = $this->duration > 0 ? $this->clock->now()->getTimestamp() + $this->duration : null;
        $expires = $expiresAt !== null ? (new DateTimeImmutable())->setTimestamp($expiresAt) : null;

        $value = Json::encode([
            $identity->getId(),
            $identity->getCookieLoginKey(),
            $expiresAt ?? 0,
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
     * @param array<array-key, mixed> $cookies
     * @param array<array-key, mixed> $serverParams
     *
     * @return bool Whether the cookie logged a {@see User} identity in and its cookie must be
     * reissued on the response - the session (and therefore the sessionId embedded in the cookie)
     * has just rotated, so the caller is responsible for calling {@see addCookie()} with the new
     * session ID afterward.
     */
    public function loginByCookie(
        array $cookies,
        CurrentUser $currentUser,
        IdentityRepositoryInterface $identityRepository,
        SessionInterface $session,
        array $serverParams = [],
    ): bool {
        $now = $this->clock->now()->getTimestamp();
        $cookieName = $this->getCookieName();
        $cookie = $cookies[$cookieName] ?? null;

        if (!is_string($cookie)) {
            return false;
        }

        $data = $this->decodeCookie($cookie);
        if ($data === null) {
            return false;
        }

        [$id, $key, $expires, $cookieSessionId] = $data;
        $identity = $identityRepository->findIdentity((string) $id);

        $expiresInt = (int) $expires;
        if (
            !$identity instanceof CookieLoginIdentityInterface
            || !$identity->validateCookieLoginKey((string) $key)
            || ($expiresInt !== 0 && $expiresInt < $now)
        ) {
            return false;
        }

        if (
            $identity instanceof User
            && UserSessions::findByUserIdAndSessionId($identity->getIdOrZero(), (string) $cookieSessionId) === null
        ) {
            // The session this cookie was issued for was terminated (self-service or admin) - the cookie
            // must not resurrect it, even though the shared cookieLoginKey is still otherwise valid.
            return false;
        }

        $currentUser->login($identity);

        if (!$identity instanceof User) {
            return false;
        }

        // CurrentUser::login() regenerates the session ID (anti-fixation); dispatching
        // AfterLoginEvent here - as the interactive login flow does - keeps
        // SessionListener in sync with that new ID for auto-logins too.
        //
        // Pass $cookieSessionId (the session ID the cookie was issued for) as
        // previousSessionId instead of the current PHP session ID - this is the value
        // stored in UserSessions and lets replaceSession() find/delete the old row.
        $this->eventDispatcher?->dispatch(
            new AfterLoginEvent($identity, previousSessionId: (string) $cookieSessionId, serverParams: $serverParams),
        );

        $newSessionId = $session->getId();
        return $newSessionId !== null && $newSessionId !== '';
    }

    /**
     * Refreshes the remember-me cookie with a new expiration timestamp
     * to implement sliding expiration.
     *
     * The cookie is refreshed at most once every 24 hours to avoid sending
     * a Set-Cookie header on every request.
     *
     * @param array<array-key, mixed> $cookies
     */
    public function refreshCookie(
        CurrentUser $currentUser,
        array $cookies,
        ResponseInterface $response,
    ): ResponseInterface {
        if ($this->duration <= 0) {
            return $response;
        }

        $now = $this->clock->now()->getTimestamp();

        $rawCookie = $cookies[$this->cookieName] ?? null;
        if (!is_string($rawCookie)) {
            return $response;
        }

        $data = $this->decodeCookie($rawCookie);
        if ($data === null) {
            return $response;
        }

        $lastRefresh = (int) $data[2] - $this->duration;

        if ($now - $lastRefresh < 86400) {
            return $response;
        }

        $identity = $currentUser->getIdentity();
        if (!$identity instanceof CookieLoginIdentityInterface) {
            return $response;
        }

        return $this->addCookie($identity, $response, (string) $data[3]);
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
}
