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

    public function testAddCookie(): void
    {
        $identity = $this->createMock(CookieLoginIdentityInterface::class);

        // Positive duration: has Max-Age expiry
        $service = new RememberMeCookieService(3600, new SystemClock());
        $response = new Response();
        $result = $service->addCookie($identity, $response, 'sess-1');
        self::assertInstanceOf(Response::class, $result);
        $header = $result->getHeaderLine('Set-Cookie');
        self::assertStringContainsString('Max-Age', $header);

        // Zero duration: no Max-Age or Expires, encodes zero in payload
        $service = new RememberMeCookieService(0, new SystemClock());
        $response = new Response();
        $result = $service->addCookie($identity, $response, 'sess-1');
        self::assertInstanceOf(Response::class, $result);
        $header = $result->getHeaderLine('Set-Cookie');
        self::assertStringNotContainsString('Max-Age', $header);
        self::assertStringNotContainsString('Expires', $header);
        preg_match('/autoLogin=([^;]+)/', $header, $matches);
        $decoded = json_decode(urldecode($matches[1]), true);
        self::assertSame(0, $decoded[2]);
    }

    public function testAddCookieAppliesConfiguredDomain(): void
    {
        $identity = $this->createMock(CookieLoginIdentityInterface::class);

        // No domain configured (default): cookie is host-only, matching historical behavior.
        $service = new RememberMeCookieService(3600, new SystemClock());
        $response = $service->addCookie($identity, new Response(), 'sess-1');
        self::assertStringNotContainsString('Domain=', $response->getHeaderLine('Set-Cookie'));

        // Domain configured: cookie carries the Domain attribute, enabling subdomain sharing.
        $service = new RememberMeCookieService(3600, new SystemClock(), cookieDomain: '.example.com');
        $response = $service->addCookie($identity, new Response(), 'sess-1');
        self::assertStringContainsString('Domain=.example.com', $response->getHeaderLine('Set-Cookie'));
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

    public function testLoginByCookieEdgeCases(): void
    {
        // Empty session ID: does not reissue cookie
        $service = new RememberMeCookieService(3600, new SystemClock(), 'autoLogin', eventDispatcher: new EventCaptureDispatcher());
        $user1 = $this->createUser(username: 'loginedge' . random_int(1, 1000000), email: 'loginedge' . random_int(1, 1000000) . '@example.com');
        $this->createUserSession((int) $user1->getId(), 'cookie-session-id');
        $session = new FakeSession();
        $session->open();
        $currentUser = $this->createCurrentUser();
        $identityRepository = $this->createMock(IdentityRepositoryInterface::class);
        $identityRepository->method('findIdentity')->willReturn($user1);
        $future = time() + 3600;
        $cookie = json_encode(['id123', 'key', $future, 'cookie-session-id']);
        $result = $service->loginByCookie(['autoLogin' => $cookie], $currentUser, $identityRepository, $session);
        self::assertSame('', $session->getId());
        self::assertFalse($result);

        // Non-User identity: returns false with regenerated session
        $identity = $this->createMock(CookieLoginIdentityInterface::class);
        $identity->method('validateCookieLoginKey')->willReturn(true);
        $identity->method('getId')->willReturn('id123');
        $identityRepository = $this->createMock(IdentityRepositoryInterface::class);
        $identityRepository->method('findIdentity')->willReturn($identity);
        $session2 = new FakeSession();
        $session2->setId('cookie-session-id');
        $session2->open();
        $currentUser2 = $this->createCurrentUser()->withSession($session2);
        $result = $service->loginByCookie(['autoLogin' => $cookie], $currentUser2, $identityRepository, $session2);
        self::assertFalse($result);
        self::assertSame($identity, $currentUser2->getIdentity());

        // Invalid array shape: does not call findIdentity
        $service3 = new RememberMeCookieService(3600, new SystemClock());
        $identityRepository3 = $this->createMock(IdentityRepositoryInterface::class);
        $identityRepository3->expects($this->never())->method('findIdentity');
        $currentUser3 = $this->createCurrentUser();
        $session3 = new FakeSession();
        $result = $service3->loginByCookie(['autoLogin' => json_encode(['id', 'key'])], $currentUser3, $identityRepository3, $session3);
        self::assertFalse($session3->has('__identity'));
        self::assertFalse($result);

        // Missing session row: does not log in
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->never())->method('dispatch');
        $service4 = new RememberMeCookieService(3600, new SystemClock(), eventDispatcher: $eventDispatcher);
        $user4 = $this->createUser(username: 'misssess' . random_int(1, 1000000), email: 'misssess' . random_int(1, 1000000) . '@example.com');
        $identityRepository4 = $this->createMock(IdentityRepositoryInterface::class);
        $identityRepository4->method('findIdentity')->willReturn($user4);
        $currentUser4 = $this->createCurrentUser();
        $session4 = new FakeSession();
        $cookie4 = json_encode(['id123', 'key', $future, 'terminated-session-id']);
        $result = $service4->loginByCookie(['autoLogin' => $cookie4], $currentUser4, $identityRepository4, $session4);
        self::assertNotSame($user4, $currentUser4->getIdentity());
        self::assertFalse($result);

        // Non-string cookie: returns false
        $service5 = new RememberMeCookieService(3600, new SystemClock());
        $identityRepository5 = $this->createMock(IdentityRepositoryInterface::class);
        $currentUser5 = $this->createCurrentUser();
        $session5 = new FakeSession();
        $result = $service5->loginByCookie(['autoLogin' => 123], $currentUser5, $identityRepository5, $session5);
        self::assertFalse($session5->has('__identity'));
        self::assertFalse($result);

        // Without event dispatcher: no error on successful login
        $service6 = new RememberMeCookieService(3600, new SystemClock());
        $user6 = $this->createUser(username: 'nodispatch' . random_int(1, 1000000), email: 'nodispatch' . random_int(1, 1000000) . '@example.com');
        $this->createUserSession((int) $user6->getId(), 'cookie-session-id');
        $identityRepository6 = $this->createMock(IdentityRepositoryInterface::class);
        $identityRepository6->method('findIdentity')->willReturn($user6);
        $currentUser6 = $this->createCurrentUser();
        $session6 = new FakeSession();
        $result = $service6->loginByCookie(['autoLogin' => $cookie], $currentUser6, $identityRepository6, $session6);
        self::assertSame($user6, $currentUser6->getIdentity());
        self::assertFalse($result);

        // Revoked session row: does not log in
        $eventDispatcher2 = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher2->expects($this->never())->method('dispatch');
        $service7 = new RememberMeCookieService(3600, new SystemClock(), eventDispatcher: $eventDispatcher2);
        $user7 = $this->createUser(username: 'revokedsess' . random_int(1, 1000000), email: 'revokedsess' . random_int(1, 1000000) . '@example.com');
        $revokedSession = $this->createUserSession((int) $user7->getId(), 'revoked-session-id');
        $revokedSession->setRevokedAt(time());
        $revokedSession->save();
        $identityRepository7 = $this->createMock(IdentityRepositoryInterface::class);
        $identityRepository7->method('findIdentity')->willReturn($user7);
        $currentUser7 = $this->createCurrentUser();
        $session7 = new FakeSession();
        $cookie7 = json_encode(['id123', 'key', $future, 'revoked-session-id']);
        $result = $service7->loginByCookie(['autoLogin' => $cookie7], $currentUser7, $identityRepository7, $session7);
        self::assertNotSame($user7, $currentUser7->getIdentity());
        self::assertFalse($result);

        // With user identity: logs in and dispatches AfterLoginEvent
        $user8 = $this->createUser(username: 'dispatchuser' . random_int(1, 1000000), email: 'dispatchuser' . random_int(1, 1000000) . '@example.com');
        $this->createUserSession((int) $user8->getId(), 'cookie-session-id');
        $eventDispatcher3 = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher3->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(
                static fn(AfterLoginEvent $event): bool => $event->getUser() === $user8
                    && $event->getPreviousSessionId() === 'cookie-session-id',
            ))
            ->willReturnArgument(0);
        $service8 = new RememberMeCookieService(3600, new SystemClock(), 'autoLogin', eventDispatcher: $eventDispatcher3);
        $session8 = new FakeSession();
        $session8->setId('php-session-id');
        $session8->open();
        $currentUser8 = $this->createCurrentUser();
        $identityRepository8 = $this->createMock(IdentityRepositoryInterface::class);
        $identityRepository8->method('findIdentity')->willReturn($user8);
        $result = $service8->loginByCookie(['autoLogin' => $cookie], $currentUser8, $identityRepository8, $session8);
        self::assertSame($user8, $currentUser8->getIdentity());
        self::assertTrue($result);
    }

    public function testRefreshCookie(): void
    {
        $now = 2000000;
        $service = new RememberMeCookieService(3600, $this->fixedClock($now), 'autoLogin');
        $response = new Response();
        $expires = $now - 90000;

        // Preserves session ID from original cookie
        $currentUser = $this->loggedInIdentity();
        $cookies = ['autoLogin' => json_encode(['id', 'key', $expires, 'original-device-session-id'])];
        $result = $service->refreshCookie($currentUser, $cookies, $response);
        self::assertNotSame($response, $result);
        $header = $result->getHeaderLine('Set-Cookie');
        preg_match('/autoLogin=([^;]+)/', $header, $matches);
        $decoded = json_decode(urldecode($matches[1]), true);
        self::assertSame('original-device-session-id', $decoded[3]);

        // Sets secure flags and updates ID/key with unicode support
        $currentUser2 = $this->loggedInIdentity('u/ñid', 'c/ñkey');
        $response2 = new Response();
        $cookies2 = ['autoLogin' => json_encode(['id', 'key', $expires, 'sess-id'])];
        $result2 = $service->refreshCookie($currentUser2, $cookies2, $response2);
        self::assertNotSame($response2, $result2);
        $header2 = $result2->getHeaderLine('Set-Cookie');
        self::assertStringContainsString('Secure', $header2);
        self::assertStringContainsString('HttpOnly', $header2);
        self::assertStringContainsString('SameSite=Lax', $header2);
        preg_match('/autoLogin=([^;]+)/', $header2, $matches2);
        $value = urldecode($matches2[1]);
        self::assertStringContainsString('u/ñid', $value);
        self::assertStringContainsString('c/ñkey', $value);
        $decoded2 = json_decode($value, true);
        self::assertSame(['u/ñid', 'c/ñkey', $now + 3600, 'sess-id'], $decoded2);
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
