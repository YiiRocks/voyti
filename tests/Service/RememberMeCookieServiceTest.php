<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Service;

use DateTimeImmutable;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use YiiRocks\Voyti\Clock\SystemClock;
use YiiRocks\Voyti\Event\Auth\AfterLoginEvent;
use YiiRocks\Voyti\Service\RememberMeCookieService;
use YiiRocks\Voyti\tests\Support\DatabaseTestCase;
use YiiRocks\Voyti\tests\Support\EventCaptureDispatcher;
use YiiRocks\Voyti\tests\Support\FakeSession;
use YiiRocks\Voyti\tests\Support\FixedClock;
use YiiRocks\Voyti\tests\Support\UserFactoryTrait;
use YiiRocks\Voyti\tests\Support\UserSessionFactoryTrait;
use Yiisoft\Auth\IdentityRepositoryInterface;
use Yiisoft\User\CurrentUser;
use Yiisoft\User\Login\Cookie\CookieLoginIdentityInterface;

#[AllowMockObjectsWithoutExpectations]
final class RememberMeCookieServiceTest extends DatabaseTestCase
{
    use UserFactoryTrait;
    use UserSessionFactoryTrait;

    public static function loginByCookieProvider(): iterable
    {
        yield 'valid key and unexpired cookie' => [null, time() + 3600, true, true];
        yield 'expired cookie' => [null, time() - 100, true, false];
        yield 'invalid key' => [null, 0, false, false];
        yield 'non-numeric expires' => [null, 'abc', true, true];
        yield 'null expires treated as never expiring' => [null, null, true, true];
        yield 'zero expires with zero clock' => [0, 0, true, true];
        yield 'zero expires as float' => [null, 0.0, true, true];
    }

    public static function refreshCookieBoundaryProvider(): iterable
    {
        yield 'less than boundary emits' => [100000, 17200, true];
        yield 'minus operator on duration emits' => [1000000, 913600, true];
        yield 'minus operator on now/last refresh boundary' => [100000, -20000, true];
        yield 'not enough time passes boundary does not emit' => [100000, 100000, false];
        yield 'non-numeric last refresh does not throw' => [2000000, 'not-a-number', true];
    }

    public static function refreshCookieNoEmitProvider(): iterable
    {
        yield 'identity not cookie login' => [3600, false, json_encode(['id', 'key', 1900000, 'sess-id']), 2000000];
        yield 'invalid array shape' => [3600, false, json_encode(['a', 'b']), null];
        yield 'invalid json' => [3600, false, 'not-json', null];
        yield 'no cookie' => [3600, false, null, null];
        yield 'not enough time passed' => [3600, true, json_encode(['id', 'key', 1003600, 'sess-id']), 1000000];
        yield 'uses expires not key for last refresh' => [3600, true, json_encode(['uid', 1000, 1000000, 'sess-id']), 100000];
        yield 'non-positive duration' => [0, false, 'data', null];
        yield 'zero duration with valid path' => [0, true, json_encode(['uid', 'ckey', 10000, 'sess-id']), 100000];
    }

    public function testAddCookieWithPositiveDurationHasExpiry(): void
    {
        $service = new RememberMeCookieService(3600, new SystemClock());
        $identity = $this->createMock(CookieLoginIdentityInterface::class);
        $response = new Response();

        $result = $service->addCookie($identity, $response, 'sess-1');
        self::assertInstanceOf(Response::class, $result);
        $header = $result->getHeaderLine('Set-Cookie');
        self::assertStringContainsString('Max-Age', $header);
    }

    public function testAddCookieWithZeroDurationHasNoExpiry(): void
    {
        $service = new RememberMeCookieService(0, new SystemClock());
        $identity = $this->createMock(CookieLoginIdentityInterface::class);
        $response = new Response();

        $result = $service->addCookie($identity, $response, 'sess-1');
        self::assertInstanceOf(Response::class, $result);
        $header = $result->getHeaderLine('Set-Cookie');
        self::assertStringNotContainsString('Max-Age', $header);
        self::assertStringNotContainsString('Expires', $header);
        // A zero duration encodes a zero expiry into the payload (no future timestamp).
        preg_match('/autoLogin=([^;]+)/', $header, $matches);
        $decoded = json_decode(urldecode($matches[1]), true);
        self::assertSame(0, $decoded[2]);
    }

    public function testClockIsUsedNotRealTime(): void
    {
        $fakeNow = 1000;
        $clock = $this->fixedClock($fakeNow);
        $service = new RememberMeCookieService(3600, $clock, 'autoLogin');
        $session = new FakeSession();
        $currentUser = $this->createCurrentUser();
        $identity = $this->createMock(CookieLoginIdentityInterface::class);
        $identity->method('validateCookieLoginKey')->willReturn(true);
        $identityRepository = $this->createMock(IdentityRepositoryInterface::class);
        $identityRepository->method('findIdentity')->willReturn($identity);

        $cookie = json_encode(['id123', 'key123', $fakeNow, 'sess-id']);
        $service->loginByCookie(['autoLogin' => $cookie], $currentUser, $identityRepository, $session);
        self::assertSame($identity, $currentUser->getIdentity());
    }

    public function testGetCookieName(): void
    {
        $service = new RememberMeCookieService(3600, new SystemClock(), 'autoLogin');
        self::assertSame('autoLogin', $service->getCookieName());
    }

    #[DataProvider('loginByCookieProvider')]
    public function testLoginByCookie(?int $now, int|string|float|null $expires, bool $validKey, bool $expectedLoggedIn): void
    {
        $service = new RememberMeCookieService(3600, $now === null ? new SystemClock() : $this->fixedClock($now));
        $session = new FakeSession();
        $currentUser = $this->createCurrentUser();
        $identity = $this->createMock(CookieLoginIdentityInterface::class);
        $identity->method('validateCookieLoginKey')->willReturn($validKey);
        $identityRepository = $this->createMock(IdentityRepositoryInterface::class);
        $identityRepository->method('findIdentity')->willReturn($identity);

        $cookie = json_encode(['id123', 'key123', $expires, 'sess-id']);
        $result = $service->loginByCookie(['autoLogin' => $cookie], $currentUser, $identityRepository, $session);

        self::assertFalse($result);
        self::assertSame($expectedLoggedIn, $currentUser->getIdentity() === $identity);
    }

    public function testLoginByCookieDoesNotReissueCookieWhenSessionIdIsEmptyString(): void
    {
        $service = new RememberMeCookieService(
            3600,
            new SystemClock(),
            'autoLogin',
            eventDispatcher: new EventCaptureDispatcher(),
        );

        $user = $this->createUser(
            username: 'cookieuser' . random_int(1, 1000000),
            email: 'cookieuser' . random_int(1, 1000000) . '@example.com',
        );
        $userId = (int) $user->getId();
        $this->createUserSession($userId, 'cookie-session-id');

        $identityRepository = $this->createMock(IdentityRepositoryInterface::class);
        $identityRepository->method('findIdentity')->willReturn($user);

        // Opened but never assigned an ID and not attached to $currentUser, so it stays
        // active with an empty ID both before and after login() - distinct from the
        // "session never opened" (null) case below.
        $session = new FakeSession();
        $session->open();
        $currentUser = $this->createCurrentUser();

        $future = time() + 3600;
        $cookie = json_encode(['id123', 'key', $future, 'cookie-session-id']);
        $result = $service->loginByCookie(['autoLogin' => $cookie], $currentUser, $identityRepository, $session);

        self::assertSame('', $session->getId());
        self::assertFalse($result);
    }

    public function testLoginByCookieReturnsFalseForNonUserIdentityEvenWithRegeneratedSession(): void
    {
        $service = new RememberMeCookieService(
            3600,
            new SystemClock(),
            'autoLogin',
            eventDispatcher: new EventCaptureDispatcher(),
        );

        $identity = $this->createMock(CookieLoginIdentityInterface::class);
        $identity->method('validateCookieLoginKey')->willReturn(true);
        $identity->method('getId')->willReturn('id123');
        $identityRepository = $this->createMock(IdentityRepositoryInterface::class);
        $identityRepository->method('findIdentity')->willReturn($identity);

        $session = new FakeSession();
        $session->setId('cookie-session-id');
        $session->open();
        $currentUser = $this->createCurrentUser()->withSession($session);

        $future = time() + 3600;
        $cookie = json_encode(['id123', 'key123', $future, 'cookie-session-id']);
        $result = $service->loginByCookie(['autoLogin' => $cookie], $currentUser, $identityRepository, $session);

        self::assertFalse($result);
        self::assertSame($identity, $currentUser->getIdentity());
    }

    public function testLoginByCookieWithInvalidArrayShapeReturns(): void
    {
        $service = new RememberMeCookieService(3600, new SystemClock());
        $session = new FakeSession();
        $currentUser = $this->createCurrentUser();
        $identityRepository = $this->createMock(IdentityRepositoryInterface::class);
        $identityRepository->expects($this->never())->method('findIdentity');

        $result = $service->loginByCookie(['autoLogin' => json_encode(['id', 'key'])], $currentUser, $identityRepository, $session);
        self::assertFalse($session->has('__identity'));
        self::assertFalse($result);
    }

    public function testLoginByCookieWithMissingSessionRowReturns(): void
    {
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->never())->method('dispatch');

        $service = new RememberMeCookieService(3600, new SystemClock(), eventDispatcher: $eventDispatcher);

        $user = $this->createUser(
            username: 'cookieuser' . random_int(1, 1000000),
            email: 'cookieuser' . random_int(1, 1000000) . '@example.com',
        );
        $userId = (int) $user->getId();
        // Deliberately no matching UserSessions row for 'terminated-session-id' -
        // simulates the device's session having been terminated from elsewhere.

        $session = new FakeSession();
        $currentUser = $this->createCurrentUser();
        $identityRepository = $this->createMock(IdentityRepositoryInterface::class);
        $identityRepository->method('findIdentity')->willReturn($user);

        $future = time() + 3600;
        $cookie = json_encode(['id123', 'key', $future, 'terminated-session-id']);
        $result = $service->loginByCookie(['autoLogin' => $cookie], $currentUser, $identityRepository, $session);

        self::assertNotSame($user, $currentUser->getIdentity());
        self::assertFalse($result);
    }

    public function testLoginByCookieWithNonStringCookieReturns(): void
    {
        $service = new RememberMeCookieService(3600, new SystemClock());
        $session = new FakeSession();
        $currentUser = $this->createCurrentUser();
        $identityRepository = $this->createMock(IdentityRepositoryInterface::class);

        $result = $service->loginByCookie(['autoLogin' => 123], $currentUser, $identityRepository, $session);
        self::assertFalse($session->has('__identity'));
        self::assertFalse($result);
    }

    public function testLoginByCookieWithoutEventDispatcherDoesNotError(): void
    {
        $service = new RememberMeCookieService(3600, new SystemClock());

        $user = $this->createUser(
            username: 'cookieuser' . random_int(1, 1000000),
            email: 'cookieuser' . random_int(1, 1000000) . '@example.com',
        );
        $userId = (int) $user->getId();
        $this->createUserSession($userId, 'cookie-session-id');

        $session = new FakeSession();
        $currentUser = $this->createCurrentUser();
        $identityRepository = $this->createMock(IdentityRepositoryInterface::class);
        $identityRepository->method('findIdentity')->willReturn($user);

        $future = time() + 3600;
        $cookie = json_encode(['id123', 'key', $future, 'cookie-session-id']);
        $result = $service->loginByCookie(['autoLogin' => $cookie], $currentUser, $identityRepository, $session);

        self::assertSame($user, $currentUser->getIdentity());
        self::assertFalse($result);
    }

    public function testLoginByCookieWithRevokedSessionRowReturns(): void
    {
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->never())->method('dispatch');

        $service = new RememberMeCookieService(3600, new SystemClock(), eventDispatcher: $eventDispatcher);

        $user = $this->createUser(
            username: 'cookieuser' . random_int(1, 1000000),
            email: 'cookieuser' . random_int(1, 1000000) . '@example.com',
        );
        $userId = (int) $user->getId();
        $revokedSession = $this->createUserSession($userId, 'revoked-session-id');
        $revokedSession->setRevokedAt(time());
        $revokedSession->save();

        $session = new FakeSession();
        $currentUser = $this->createCurrentUser();
        $identityRepository = $this->createMock(IdentityRepositoryInterface::class);
        $identityRepository->method('findIdentity')->willReturn($user);

        $future = time() + 3600;
        $cookie = json_encode(['id123', 'key', $future, 'revoked-session-id']);
        $result = $service->loginByCookie(['autoLogin' => $cookie], $currentUser, $identityRepository, $session);

        self::assertNotSame($user, $currentUser->getIdentity());
        self::assertFalse($result);
    }

    public function testLoginByCookieWithUserIdentityDispatchesAfterLoginEventWithCookieSessionId(): void
    {
        $user = $this->createUser(
            username: 'cookieuser' . random_int(1, 1000000),
            email: 'cookieuser' . random_int(1, 1000000) . '@example.com',
        );
        $userId = (int) $user->getId();
        $this->createUserSession($userId, 'cookie-session-id');

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(
                static fn(AfterLoginEvent $event): bool => $event->getUser() === $user
                    && $event->getPreviousSessionId() === 'cookie-session-id',
            ))
            ->willReturnArgument(0);

        $service = new RememberMeCookieService(3600, new SystemClock(), 'autoLogin', eventDispatcher: $eventDispatcher);
        $session = new FakeSession();
        $session->setId('php-session-id');
        $session->open();
        $currentUser = $this->createCurrentUser();
        $identityRepository = $this->createMock(IdentityRepositoryInterface::class);
        $identityRepository->method('findIdentity')->willReturn($user);

        $future = time() + 3600;
        $cookie = json_encode(['id123', 'key', $future, 'cookie-session-id']);
        $result = $service->loginByCookie(['autoLogin' => $cookie], $currentUser, $identityRepository, $session);

        self::assertSame($user, $currentUser->getIdentity());
        self::assertTrue($result);
    }

    #[DataProvider('refreshCookieBoundaryProvider')]
    public function testRefreshCookieBoundary(int $now, int|string $expires, bool $expectedReissued): void
    {
        $service = new RememberMeCookieService(3600, $this->fixedClock($now), 'autoLogin');
        $currentUser = $this->loggedInIdentity();
        $response = new Response();

        $cookies = ['autoLogin' => json_encode(['uid', 'ckey', $expires, 'sess-id'])];
        $result = $service->refreshCookie($currentUser, $cookies, $response);
        self::assertSame($expectedReissued, $result !== $response);
    }

    #[DataProvider('refreshCookieNoEmitProvider')]
    public function testRefreshCookieDoesNotEmit(int $duration, bool $loggedIn, ?string $cookieValue, ?int $now): void
    {
        $service = new RememberMeCookieService($duration, $now === null ? new SystemClock() : $this->fixedClock($now), 'autoLogin');
        $response = new Response();

        $cookies = $cookieValue === null ? [] : ['autoLogin' => $cookieValue];
        $result = $service->refreshCookie($loggedIn ? $this->loggedInIdentity() : $this->createCurrentUser(), $cookies, $response);

        self::assertSame($response, $result);
    }

    public function testRefreshCookiePreservesSessionId(): void
    {
        $now = 2000000;
        $service = new RememberMeCookieService(3600, $this->fixedClock($now), 'autoLogin');
        $currentUser = $this->loggedInIdentity();
        $response = new Response();

        $expires = $now - 90000;
        $cookies = ['autoLogin' => json_encode(['id', 'key', $expires, 'original-device-session-id'])];
        $result = $service->refreshCookie($currentUser, $cookies, $response);
        self::assertNotSame($response, $result);

        $header = $result->getHeaderLine('Set-Cookie');
        preg_match('/autoLogin=([^;]+)/', $header, $matches);
        $decoded = json_decode(urldecode($matches[1]), true);
        self::assertSame('original-device-session-id', $decoded[3]);
    }

    public function testRefreshCookieSuccess(): void
    {
        $now = 2000000;
        $service = new RememberMeCookieService(3600, $this->fixedClock($now), 'autoLogin');
        $currentUser = $this->loggedInIdentity('u/ñid', 'c/ñkey');
        $response = new Response();

        $expires = $now - 90000;
        $cookies = ['autoLogin' => json_encode(['id', 'key', $expires, 'sess-id'])];
        $result = $service->refreshCookie($currentUser, $cookies, $response);
        self::assertNotSame($response, $result);

        $header = $result->getHeaderLine('Set-Cookie');
        self::assertStringContainsString('Secure', $header);
        self::assertStringContainsString('HttpOnly', $header);
        self::assertStringContainsString('SameSite=Lax', $header);

        preg_match('/autoLogin=([^;]+)/', $header, $matches);
        $value = urldecode($matches[1]);
        self::assertStringContainsString('u/ñid', $value);
        self::assertStringContainsString('c/ñkey', $value);
        $decoded = json_decode($value, true);
        self::assertSame(['u/ñid', 'c/ñkey', $now + 3600, 'sess-id'], $decoded);
    }

    private function createCurrentUser(): CurrentUser
    {
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->method('dispatch')->willReturnArgument(0);
        return new CurrentUser(
            $this->createMock(IdentityRepositoryInterface::class),
            $eventDispatcher,
        );
    }

    private function fixedClock(int $now): ClockInterface
    {
        return new FixedClock((new DateTimeImmutable())->setTimestamp($now));
    }

    private function loggedInIdentity(string $id = 'uid', string $key = 'ckey'): CurrentUser
    {
        $identity = $this->createMock(CookieLoginIdentityInterface::class);
        $identity->method('getId')->willReturn($id);
        $identity->method('getCookieLoginKey')->willReturn($key);
        $currentUser = $this->createCurrentUser();
        $currentUser->login($identity);
        return $currentUser;
    }
}
