<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Service;

use Nyholm\Psr7\Response;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use YiiRocks\Voyti\Event\Auth\AfterLoginEvent;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Model\UserSessions;
use YiiRocks\Voyti\Service\RememberMeCookieService;
use YiiRocks\Voyti\tests\Support\DatabaseSetupTrait;
use YiiRocks\Voyti\tests\Support\EventCaptureDispatcher;
use YiiRocks\Voyti\tests\Support\FakeSession;
use YiiRocks\Voyti\tests\Support\UserFactoryTrait;
use Yiisoft\Auth\IdentityRepositoryInterface;
use Yiisoft\User\CurrentUser;
use Yiisoft\User\Login\Cookie\CookieLoginIdentityInterface;

#[AllowMockObjectsWithoutExpectations]
final class RememberMeCookieServiceTest extends TestCase
{
    use DatabaseSetupTrait;
    use UserFactoryTrait;

    protected function setUp(): void
    {
        $this->setUpDatabase();
    }

    protected function tearDown(): void
    {
        $this->tearDownDatabase();
    }

    /**
     * @return iterable<string, array{int, int|string, bool}>
     */
    public static function refreshCookieBoundaryProvider(): iterable
    {
        yield 'less than boundary emits' => [100000, 17200, true];
        yield 'minus operator on duration emits' => [1000000, 913600, true];
        yield 'minus operator on now/last refresh boundary' => [100000, -20000, true];
        yield 'not enough time passes boundary does not emit' => [100000, 100000, false];
        yield 'non-numeric last refresh does not throw' => [2000000, 'not-a-number', true];
    }

    public function testAddCookieEmbedsSessionId(): void
    {
        $service = new RememberMeCookieService(3600);
        $identity = $this->createMock(CookieLoginIdentityInterface::class);
        $identity->method('getId')->willReturn('uid');
        $identity->method('getCookieLoginKey')->willReturn('ckey');
        $response = new Response();

        $result = $service->addCookie($identity, $response, 'device-session-id');
        $header = $result->getHeaderLine('Set-Cookie');

        preg_match('/autoLogin=([^;]+)/', $header, $matches);
        $decoded = json_decode(urldecode($matches[1]), true);
        self::assertSame(['uid', 'ckey', $decoded[2], 'device-session-id'], $decoded);
    }

    public function testAddCookieWithPositiveDurationHasExpiry(): void
    {
        $service = new RememberMeCookieService(3600);
        $identity = $this->createMock(CookieLoginIdentityInterface::class);
        $response = new Response();

        $result = $service->addCookie($identity, $response, 'sess-1');
        self::assertInstanceOf(Response::class, $result);
        $header = $result->getHeaderLine('Set-Cookie');
        self::assertStringContainsString('Max-Age', $header);
    }

    public function testAddCookieWithZeroDurationHasNoExpiry(): void
    {
        $service = new RememberMeCookieService(0);
        $identity = $this->createMock(CookieLoginIdentityInterface::class);
        $response = new Response();

        $result = $service->addCookie($identity, $response, 'sess-1');
        self::assertInstanceOf(Response::class, $result);
        $header = $result->getHeaderLine('Set-Cookie');
        self::assertStringNotContainsString('Max-Age', $header);
        self::assertStringNotContainsString('Expires', $header);
    }

    public function testExpireCookie(): void
    {
        $service = new RememberMeCookieService(3600);
        $response = new Response();

        $result = $service->expireCookie($response);
        self::assertInstanceOf(Response::class, $result);
    }

    public function testGetCookieName(): void
    {
        $service = new RememberMeCookieService(3600, 'autoLogin');
        self::assertSame('autoLogin', $service->getCookieName());
    }

    public function testLoginByCookieDoesNotReissueCookieWhenSessionIdIsEmptyString(): void
    {
        $service = new RememberMeCookieService(3600, 'autoLogin', eventDispatcher: new EventCaptureDispatcher());

        $user = $this->createUser(
            username: 'cookieuser' . random_int(1, 1000000),
            email: 'cookieuser' . random_int(1, 1000000) . '@example.com',
        );
        $userId = (int) $user->getId();
        $this->createUserSession($userId, 'cookie-session-id');

        $identity = $this->createMock(User::class);
        $identity->method('validateCookieLoginKey')->willReturn(true);
        $identity->method('getId')->willReturn((string) $userId);
        $identity->method('getIdOrZero')->willReturn($userId);
        $identityRepository = $this->createMock(IdentityRepositoryInterface::class);
        $identityRepository->method('findIdentity')->willReturn($identity);

        // Opened but never assigned an ID and not attached to $currentUser, so it stays
        // active with an empty ID both before and after login() - distinct from the
        // "session never opened" (null) case below.
        $session = new FakeSession();
        $session->open();
        $currentUser = $this->createCurrentUser();

        $future = time() + 3600;
        $cookie = json_encode(['id123', 'key123', $future, 'cookie-session-id']);
        $result = $service->loginByCookie(['autoLogin' => $cookie], $currentUser, $identityRepository, $session);

        self::assertSame('', $session->getId());
        self::assertFalse($result);
    }

    public function testLoginByCookieDoesNotReissueCookieWhenSessionNeverOpened(): void
    {
        $service = new RememberMeCookieService(3600, 'autoLogin', eventDispatcher: new EventCaptureDispatcher());

        $user = $this->createUser(
            username: 'cookieuser' . random_int(1, 1000000),
            email: 'cookieuser' . random_int(1, 1000000) . '@example.com',
        );
        $userId = (int) $user->getId();
        $this->createUserSession($userId, 'cookie-session-id');

        $identity = $this->createMock(User::class);
        $identity->method('validateCookieLoginKey')->willReturn(true);
        $identity->method('getId')->willReturn((string) $userId);
        $identity->method('getIdOrZero')->willReturn($userId);
        $identityRepository = $this->createMock(IdentityRepositoryInterface::class);
        $identityRepository->method('findIdentity')->willReturn($identity);

        $session = new FakeSession();
        $currentUser = $this->createCurrentUser();

        $future = time() + 3600;
        $cookie = json_encode(['id123', 'key123', $future, 'cookie-session-id']);
        $result = $service->loginByCookie(['autoLogin' => $cookie], $currentUser, $identityRepository, $session);

        self::assertFalse($result);
    }

    public function testLoginByCookieReturnsFalseForNonUserIdentityEvenWithRegeneratedSession(): void
    {
        $service = new RememberMeCookieService(3600, 'autoLogin', eventDispatcher: new EventCaptureDispatcher());

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

    public function testLoginByCookieReturnsTrueWithRegeneratedSessionId(): void
    {
        $service = new RememberMeCookieService(3600, 'autoLogin', eventDispatcher: new EventCaptureDispatcher());

        $user = $this->createUser(
            username: 'cookieuser' . random_int(1, 1000000),
            email: 'cookieuser' . random_int(1, 1000000) . '@example.com',
        );
        $userId = (int) $user->getId();
        $this->createUserSession($userId, 'cookie-session-id');

        $identity = $this->createMock(User::class);
        $identity->method('validateCookieLoginKey')->willReturn(true);
        $identity->method('getId')->willReturn((string) $userId);
        $identity->method('getIdOrZero')->willReturn($userId);
        $identity->method('getCookieLoginKey')->willReturn('key123');
        $identityRepository = $this->createMock(IdentityRepositoryInterface::class);
        $identityRepository->method('findIdentity')->willReturn($identity);

        $session = new FakeSession();
        $session->setId('cookie-session-id');
        $session->open();
        $currentUser = $this->createCurrentUser()->withSession($session);

        $future = time() + 3600;
        $cookie = json_encode(['id123', 'key123', $future, 'cookie-session-id']);
        $result = $service->loginByCookie(['autoLogin' => $cookie], $currentUser, $identityRepository, $session);

        self::assertTrue($result);
        self::assertNotSame('cookie-session-id', $session->getId());
    }

    public function testLoginByCookieSuccess(): void
    {
        $service = new RememberMeCookieService(3600);
        $session = new FakeSession();
        $currentUser = $this->createCurrentUser();
        $identity = $this->createMock(CookieLoginIdentityInterface::class);
        $identity->method('validateCookieLoginKey')->willReturn(true);
        $identityRepository = $this->createMock(IdentityRepositoryInterface::class);
        $identityRepository->method('findIdentity')->willReturn($identity);

        $future = time() + 3600;
        $cookie = json_encode(['id123', 'key123', $future, 'sess-id']);
        $result = $service->loginByCookie(['autoLogin' => $cookie], $currentUser, $identityRepository, $session);
        $loggedInIdentity = $currentUser->getIdentity();
        self::assertSame($identity, $loggedInIdentity);
        self::assertFalse($result);
    }

    public function testLoginByCookieWithExpiredCookieReturns(): void
    {
        $service = new RememberMeCookieService(3600);
        $session = new FakeSession();
        $currentUser = $this->createCurrentUser();
        $identity = $this->createMock(CookieLoginIdentityInterface::class);
        $identity->method('validateCookieLoginKey')->willReturn(true);
        $identityRepository = $this->createMock(IdentityRepositoryInterface::class);
        $identityRepository->method('findIdentity')->willReturn($identity);

        $expired = time() - 100;
        $cookie = json_encode(['id123', 'key123', $expired, 'sess-id']);
        $result = $service->loginByCookie(['autoLogin' => $cookie], $currentUser, $identityRepository, $session);
        self::assertFalse($session->has('__identity'));
        self::assertFalse($result);
    }

    public function testLoginByCookieWithFloatNowDistinguishesCast(): void
    {
        $nowClosure = static function (): float {
            return 1000.5;
        };
        $service = new RememberMeCookieService(3600, 'autoLogin', $nowClosure);
        $session = new FakeSession();
        $currentUser = $this->createCurrentUser();
        $identity = $this->createMock(CookieLoginIdentityInterface::class);
        $identity->method('validateCookieLoginKey')->willReturn(true);
        $identityRepository = $this->createMock(IdentityRepositoryInterface::class);
        $identityRepository->method('findIdentity')->willReturn($identity);

        $cookie = json_encode(['id123', 'key123', 1000, 'sess-id']);
        $service->loginByCookie(['autoLogin' => $cookie], $currentUser, $identityRepository, $session);
        self::assertSame($identity, $currentUser->getIdentity());
    }

    public function testLoginByCookieWithInvalidArrayShapeReturns(): void
    {
        $service = new RememberMeCookieService(3600);
        $session = new FakeSession();
        $currentUser = $this->createCurrentUser();
        $identityRepository = $this->createMock(IdentityRepositoryInterface::class);

        $result = $service->loginByCookie(['autoLogin' => json_encode(['id', 'key'])], $currentUser, $identityRepository, $session);
        self::assertFalse($session->has('__identity'));
        self::assertFalse($result);
    }

    public function testLoginByCookieWithInvalidIdentityReturns(): void
    {
        $service = new RememberMeCookieService(3600);
        $session = new FakeSession();
        $currentUser = $this->createCurrentUser();
        $identityRepository = $this->createMock(IdentityRepositoryInterface::class);
        $identityRepository->method('findIdentity')->willReturn(null);

        $cookie = json_encode(['id123', 'key123', 0, 'sess-id']);
        $result = $service->loginByCookie(['autoLogin' => $cookie], $currentUser, $identityRepository, $session);
        self::assertFalse($session->has('__identity'));
        self::assertFalse($result);
    }

    public function testLoginByCookieWithInvalidJsonReturns(): void
    {
        $service = new RememberMeCookieService(3600);
        $session = new FakeSession();
        $currentUser = $this->createCurrentUser();
        $identityRepository = $this->createMock(IdentityRepositoryInterface::class);

        $result = $service->loginByCookie(['autoLogin' => 'not-json'], $currentUser, $identityRepository, $session);
        self::assertFalse($session->has('__identity'));
        self::assertFalse($result);
    }

    public function testLoginByCookieWithInvalidKeyAndZeroExpiresLogsInOriginalReturns(): void
    {
        $service = new RememberMeCookieService(3600);
        $session = new FakeSession();
        $currentUser = $this->createCurrentUser();
        $identity = $this->createMock(CookieLoginIdentityInterface::class);
        $identity->method('validateCookieLoginKey')->willReturn(false);
        $identityRepository = $this->createMock(IdentityRepositoryInterface::class);
        $identityRepository->method('findIdentity')->willReturn($identity);

        $cookie = json_encode(['id123', 'key123', 0, 'sess-id']);
        $result = $service->loginByCookie(['autoLogin' => $cookie], $currentUser, $identityRepository, $session);
        self::assertNotSame($identity, $currentUser->getIdentity());
        self::assertFalse($result);
    }

    public function testLoginByCookieWithNonNumericExpiresLogsIn(): void
    {
        $service = new RememberMeCookieService(3600);
        $session = new FakeSession();
        $currentUser = $this->createCurrentUser();
        $identity = $this->createMock(CookieLoginIdentityInterface::class);
        $identity->method('validateCookieLoginKey')->willReturn(true);
        $identityRepository = $this->createMock(IdentityRepositoryInterface::class);
        $identityRepository->method('findIdentity')->willReturn($identity);

        $cookie = json_encode(['id123', 'key123', 'abc', 'sess-id']);
        $service->loginByCookie(['autoLogin' => $cookie], $currentUser, $identityRepository, $session);
        self::assertSame($identity, $currentUser->getIdentity());
    }

    public function testLoginByCookieWithNonStringCookieReturns(): void
    {
        $service = new RememberMeCookieService(3600);
        $session = new FakeSession();
        $currentUser = $this->createCurrentUser();
        $identityRepository = $this->createMock(IdentityRepositoryInterface::class);

        $result = $service->loginByCookie(['autoLogin' => 123], $currentUser, $identityRepository, $session);
        self::assertFalse($session->has('__identity'));
        self::assertFalse($result);
    }

    public function testLoginByCookieWithNonUserIdentityDoesNotDispatchEvent(): void
    {
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->never())->method('dispatch');

        $service = new RememberMeCookieService(3600, eventDispatcher: $eventDispatcher);
        $session = new FakeSession();
        $currentUser = $this->createCurrentUser();
        $identity = $this->createMock(CookieLoginIdentityInterface::class);
        $identity->method('validateCookieLoginKey')->willReturn(true);
        $identityRepository = $this->createMock(IdentityRepositoryInterface::class);
        $identityRepository->method('findIdentity')->willReturn($identity);

        $future = time() + 3600;
        $cookie = json_encode(['id123', 'key123', $future, 'sess-id']);
        $result = $service->loginByCookie(['autoLogin' => $cookie], $currentUser, $identityRepository, $session);

        self::assertSame($identity, $currentUser->getIdentity());
        self::assertFalse($result);
    }

    public function testLoginByCookieWithNullExpiresTreatsAsNeverExpiring(): void
    {
        $service = new RememberMeCookieService(3600);
        $session = new FakeSession();
        $currentUser = $this->createCurrentUser();
        $identity = $this->createMock(CookieLoginIdentityInterface::class);
        $identity->method('validateCookieLoginKey')->willReturn(true);
        $identityRepository = $this->createMock(IdentityRepositoryInterface::class);
        $identityRepository->method('findIdentity')->willReturn($identity);

        $cookie = json_encode(['id123', 'key123', null, 'sess-id']);
        $service->loginByCookie(['autoLogin' => $cookie], $currentUser, $identityRepository, $session);
        self::assertSame($identity, $currentUser->getIdentity());
    }

    public function testLoginByCookieWithoutEventDispatcherDoesNotError(): void
    {
        $service = new RememberMeCookieService(3600);

        $user = $this->createUser(
            username: 'cookieuser' . random_int(1, 1000000),
            email: 'cookieuser' . random_int(1, 1000000) . '@example.com',
        );
        $userId = (int) $user->getId();
        $this->createUserSession($userId, 'cookie-session-id');

        $session = new FakeSession();
        $currentUser = $this->createCurrentUser();
        $identity = $this->createMock(User::class);
        $identity->method('validateCookieLoginKey')->willReturn(true);
        $identity->method('getId')->willReturn((string) $userId);
        $identity->method('getIdOrZero')->willReturn($userId);
        $identityRepository = $this->createMock(IdentityRepositoryInterface::class);
        $identityRepository->method('findIdentity')->willReturn($identity);

        $future = time() + 3600;
        $cookie = json_encode(['id123', 'key123', $future, 'cookie-session-id']);
        $result = $service->loginByCookie(['autoLogin' => $cookie], $currentUser, $identityRepository, $session);

        self::assertSame($identity, $currentUser->getIdentity());
        self::assertFalse($result);
    }

    public function testLoginByCookieWithRevokedSessionRowReturns(): void
    {
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->never())->method('dispatch');

        $service = new RememberMeCookieService(3600, eventDispatcher: $eventDispatcher);

        $user = $this->createUser(
            username: 'cookieuser' . random_int(1, 1000000),
            email: 'cookieuser' . random_int(1, 1000000) . '@example.com',
        );
        $userId = (int) $user->getId();
        // Deliberately no matching UserSessions row for 'terminated-session-id' -
        // simulates the device's session having been terminated from elsewhere.

        $session = new FakeSession();
        $currentUser = $this->createCurrentUser();
        $identity = $this->createMock(User::class);
        $identity->method('validateCookieLoginKey')->willReturn(true);
        $identity->method('getId')->willReturn((string) $userId);
        $identity->method('getIdOrZero')->willReturn($userId);
        $identityRepository = $this->createMock(IdentityRepositoryInterface::class);
        $identityRepository->method('findIdentity')->willReturn($identity);

        $future = time() + 3600;
        $cookie = json_encode(['id123', 'key123', $future, 'terminated-session-id']);
        $result = $service->loginByCookie(['autoLogin' => $cookie], $currentUser, $identityRepository, $session);

        self::assertNotSame($identity, $currentUser->getIdentity());
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

        $identity = $this->createMock(User::class);
        $identity->method('validateCookieLoginKey')->willReturn(true);
        $identity->method('getId')->willReturn((string) $userId);
        $identity->method('getIdOrZero')->willReturn($userId);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(
                static fn(AfterLoginEvent $event): bool => $event->getUser() === $identity
                    && $event->getPreviousSessionId() === 'cookie-session-id',
            ))
            ->willReturnArgument(0);

        $service = new RememberMeCookieService(3600, 'autoLogin', eventDispatcher: $eventDispatcher);
        $session = new FakeSession();
        $session->setId('php-session-id');
        $session->open();
        $currentUser = $this->createCurrentUser();
        $identityRepository = $this->createMock(IdentityRepositoryInterface::class);
        $identityRepository->method('findIdentity')->willReturn($identity);

        $future = time() + 3600;
        $cookie = json_encode(['id123', 'key123', $future, 'cookie-session-id']);
        $result = $service->loginByCookie(['autoLogin' => $cookie], $currentUser, $identityRepository, $session);

        self::assertSame($identity, $currentUser->getIdentity());
        self::assertTrue($result);
    }

    public function testLoginByCookieWithZeroExpiresAndZeroNowReturns(): void
    {
        $nowClosure = $this->fixedNowClosure(0);
        $service = new RememberMeCookieService(3600, 'autoLogin', $nowClosure);
        $session = new FakeSession();
        $currentUser = $this->createCurrentUser();
        $identity = $this->createMock(CookieLoginIdentityInterface::class);
        $identity->method('validateCookieLoginKey')->willReturn(true);
        $identityRepository = $this->createMock(IdentityRepositoryInterface::class);
        $identityRepository->method('findIdentity')->willReturn($identity);

        $cookie = json_encode(['id123', 'key123', 0, 'sess-id']);
        $service->loginByCookie(['autoLogin' => $cookie], $currentUser, $identityRepository, $session);
        self::assertSame($identity, $currentUser->getIdentity());
    }

    public function testLoginByCookieWithZeroExpiresLogsIn(): void
    {
        $service = new RememberMeCookieService(3600);
        $session = new FakeSession();
        $currentUser = $this->createCurrentUser();
        $identity = $this->createMock(CookieLoginIdentityInterface::class);
        $identity->method('validateCookieLoginKey')->willReturn(true);
        $identityRepository = $this->createMock(IdentityRepositoryInterface::class);
        $identityRepository->method('findIdentity')->willReturn($identity);

        $cookie = json_encode(['id123', 'key123', 0.0, 'sess-id']);
        $service->loginByCookie(['autoLogin' => $cookie], $currentUser, $identityRepository, $session);
        self::assertSame($identity, $currentUser->getIdentity());
    }

    public function testNowClosureIsUsedNotRealTime(): void
    {
        $fakeNow = 1000;
        $nowClosure = $this->fixedNowClosure($fakeNow);
        $service = new RememberMeCookieService(3600, 'autoLogin', $nowClosure);
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

    #[DataProvider('refreshCookieBoundaryProvider')]
    public function testRefreshCookieBoundary(int $now, int|string $expires, bool $expectedReissued): void
    {
        $service = new RememberMeCookieService(3600, 'autoLogin', $this->fixedNowClosure($now));
        $currentUser = $this->loggedInIdentity();
        $response = new Response();

        $cookies = ['autoLogin' => json_encode(['uid', 'ckey', $expires, 'sess-id'])];
        $result = $service->refreshCookie($currentUser, $cookies, $response);
        self::assertSame($expectedReissued, $result !== $response);
    }

    public function testRefreshCookieIdentityNotCookieLoginReturns(): void
    {
        $now = 2000000;
        $service = new RememberMeCookieService(3600, 'autoLogin', $this->fixedNowClosure($now));
        $response = new Response();

        $expires = $now - 100000;
        $cookies = ['autoLogin' => json_encode(['id', 'key', $expires, 'sess-id'])];
        $result = $service->refreshCookie($this->createCurrentUser(), $cookies, $response);
        self::assertSame($response, $result);
    }

    public function testRefreshCookieInvalidArrayReturns(): void
    {
        $service = new RememberMeCookieService(3600, 'autoLogin');
        $response = new Response();

        $cookies = ['autoLogin' => json_encode(['a', 'b'])];
        $result = $service->refreshCookie($this->createCurrentUser(), $cookies, $response);
        self::assertSame($response, $result);
    }

    public function testRefreshCookieInvalidJsonReturns(): void
    {
        $service = new RememberMeCookieService(3600, 'autoLogin');
        $response = new Response();

        $cookies = ['autoLogin' => 'not-json'];
        $result = $service->refreshCookie($this->createCurrentUser(), $cookies, $response);
        self::assertSame($response, $result);
    }

    public function testRefreshCookieNoCookieReturns(): void
    {
        $service = new RememberMeCookieService(3600, 'autoLogin');
        $response = new Response();

        $result = $service->refreshCookie($this->createCurrentUser(), [], $response);
        self::assertSame($response, $result);
    }

    public function testRefreshCookieNotEnoughTimePassedReturns(): void
    {
        $now = 1000000;
        $service = new RememberMeCookieService(3600, 'autoLogin', $this->fixedNowClosure($now));
        $response = new Response();

        $expires = $now + 3600;
        $cookies = ['autoLogin' => json_encode(['id', 'key', $expires, 'sess-id'])];
        $result = $service->refreshCookie($this->createCurrentUser(), $cookies, $response);
        self::assertSame($response, $result);
    }

    public function testRefreshCookiePreservesSessionId(): void
    {
        $now = 2000000;
        $service = new RememberMeCookieService(3600, 'autoLogin', $this->fixedNowClosure($now));
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
        $service = new RememberMeCookieService(3600, 'autoLogin', $this->fixedNowClosure($now));
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

    public function testRefreshCookieUsesExpiresNotKeyForLastRefresh(): void
    {
        $now = 100000;
        $service = new RememberMeCookieService(3600, 'autoLogin', $this->fixedNowClosure($now));
        $currentUser = $this->loggedInIdentity();
        $response = new Response();

        $cookies = ['autoLogin' => json_encode(['uid', 1000, 1000000, 'sess-id'])];
        $result = $service->refreshCookie($currentUser, $cookies, $response);
        self::assertSame($response, $result);
    }

    public function testRefreshCookieWithFloatNowEmitsIntegerExpiry(): void
    {
        $nowClosure = static function (): float {
            return 2000000.7;
        };
        $service = new RememberMeCookieService(3600, 'autoLogin', $nowClosure);
        $currentUser = $this->loggedInIdentity();
        $response = new Response();

        $expires = 1910000;
        $cookies = ['autoLogin' => json_encode(['uid', 'ckey', $expires, 'sess-id'])];
        $result = $service->refreshCookie($currentUser, $cookies, $response);

        self::assertNotSame($response, $result);
        $header = $result->getHeaderLine('Set-Cookie');
        preg_match('/autoLogin=([^;]+)/', $header, $matches);
        $decoded = json_decode(urldecode($matches[1]), true);
        self::assertSame(2003600, $decoded[2]);
    }

    public function testRefreshCookieWithNonPositiveDurationReturns(): void
    {
        $service = new RememberMeCookieService(0, 'autoLogin');
        $response = new Response();

        $result = $service->refreshCookie($this->createCurrentUser(), ['autoLogin' => 'data'], $response);
        self::assertSame($response, $result);
    }

    public function testRefreshCookieWithZeroDurationAndValidPathDoesNotEmit(): void
    {
        $now = 100000;
        $service = new RememberMeCookieService(0, 'autoLogin', $this->fixedNowClosure($now));
        $currentUser = $this->loggedInIdentity();
        $response = new Response();

        $expires = $now - 90000;
        $cookies = ['autoLogin' => json_encode(['uid', 'ckey', $expires, 'sess-id'])];
        $result = $service->refreshCookie($currentUser, $cookies, $response);
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

    private function createUserSession(int $userId, string $sessionId): UserSessions
    {
        $sh = new UserSessions();
        $sh->setUserId($userId);
        $sh->setSessionId($sessionId);
        $sh->setIp('127.0.0.1');
        $sh->setCreatedAt(time());
        $sh->setUpdatedAt(time());
        $sh->save();

        return $sh;
    }

    private function fixedNowClosure(int $now): \Closure
    {
        return static function () use ($now): int {
            return $now;
        };
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
