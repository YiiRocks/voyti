<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Service;

use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use YiiRocks\Voyti\Event\Auth\AfterLoginEvent;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Service\RememberMeCookieService;
use YiiRocks\Voyti\tests\Support\FakeSession;
use Yiisoft\Auth\IdentityRepositoryInterface;
use Yiisoft\User\CurrentUser;
use Yiisoft\User\Login\Cookie\CookieLoginIdentityInterface;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
final class RememberMeCookieServiceTest extends TestCase
{
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

    public function testAddCookieWithPositiveDurationHasExpiry(): void
    {
        $service = new RememberMeCookieService(3600);
        $identity = $this->createMock(CookieLoginIdentityInterface::class);
        $response = new Response();

        $result = $service->addCookie($identity, $response);
        self::assertInstanceOf(Response::class, $result);
        $header = $result->getHeaderLine('Set-Cookie');
        self::assertStringContainsString('Max-Age', $header);
    }

    public function testAddCookieWithZeroDurationHasNoExpiry(): void
    {
        $service = new RememberMeCookieService(0);
        $identity = $this->createMock(CookieLoginIdentityInterface::class);
        $response = new Response();

        $result = $service->addCookie($identity, $response);
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
        $cookie = json_encode(['id123', 'key123', $future]);
        $service->loginByCookie(['autoLogin' => $cookie], $currentUser, $identityRepository);
        $loggedInIdentity = $currentUser->getIdentity();
        self::assertSame($identity, $loggedInIdentity);
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
        $cookie = json_encode(['id123', 'key123', $expired]);
        $service->loginByCookie(['autoLogin' => $cookie], $currentUser, $identityRepository);
        self::assertFalse($session->has('__identity'));
    }

    public function testLoginByCookieWithFloatNowDistinguishesCast(): void
    {
        $nowClosure = static function (): float {
            return 1000.5;
        };
        $service = new RememberMeCookieService(3600, 'autoLogin', null, $nowClosure);
        $currentUser = $this->createCurrentUser();
        $identity = $this->createMock(CookieLoginIdentityInterface::class);
        $identity->method('validateCookieLoginKey')->willReturn(true);
        $identityRepository = $this->createMock(IdentityRepositoryInterface::class);
        $identityRepository->method('findIdentity')->willReturn($identity);

        $cookie = json_encode(['id123', 'key123', 1000]);
        $service->loginByCookie(['autoLogin' => $cookie], $currentUser, $identityRepository);
        self::assertSame($identity, $currentUser->getIdentity());
    }

    public function testLoginByCookieWithInvalidArrayShapeReturns(): void
    {
        $service = new RememberMeCookieService(3600);
        $session = new FakeSession();
        $currentUser = $this->createCurrentUser();
        $identityRepository = $this->createMock(IdentityRepositoryInterface::class);

        $service->loginByCookie(['autoLogin' => json_encode(['id', 'key'])], $currentUser, $identityRepository);
        self::assertFalse($session->has('__identity'));
    }

    public function testLoginByCookieWithInvalidIdentityReturns(): void
    {
        $service = new RememberMeCookieService(3600);
        $session = new FakeSession();
        $currentUser = $this->createCurrentUser();
        $identityRepository = $this->createMock(IdentityRepositoryInterface::class);
        $identityRepository->method('findIdentity')->willReturn(null);

        $cookie = json_encode(['id123', 'key123', 0]);
        $service->loginByCookie(['autoLogin' => $cookie], $currentUser, $identityRepository);
        self::assertFalse($session->has('__identity'));
    }

    public function testLoginByCookieWithInvalidJsonReturns(): void
    {
        $service = new RememberMeCookieService(3600);
        $session = new FakeSession();
        $currentUser = $this->createCurrentUser();
        $identityRepository = $this->createMock(IdentityRepositoryInterface::class);

        $service->loginByCookie(['autoLogin' => 'not-json'], $currentUser, $identityRepository);
        self::assertFalse($session->has('__identity'));
    }

    public function testLoginByCookieWithInvalidKeyAndZeroExpiresLogsInOriginalReturns(): void
    {
        $service = new RememberMeCookieService(3600);
        $currentUser = $this->createCurrentUser();
        $identity = $this->createMock(CookieLoginIdentityInterface::class);
        $identity->method('validateCookieLoginKey')->willReturn(false);
        $identityRepository = $this->createMock(IdentityRepositoryInterface::class);
        $identityRepository->method('findIdentity')->willReturn($identity);

        $cookie = json_encode(['id123', 'key123', 0]);
        $service->loginByCookie(['autoLogin' => $cookie], $currentUser, $identityRepository);
        self::assertNotSame($identity, $currentUser->getIdentity());
    }

    public function testLoginByCookieWithNonNumericExpiresLogsIn(): void
    {
        $service = new RememberMeCookieService(3600);
        $currentUser = $this->createCurrentUser();
        $identity = $this->createMock(CookieLoginIdentityInterface::class);
        $identity->method('validateCookieLoginKey')->willReturn(true);
        $identityRepository = $this->createMock(IdentityRepositoryInterface::class);
        $identityRepository->method('findIdentity')->willReturn($identity);

        $cookie = json_encode(['id123', 'key123', 'abc']);
        $service->loginByCookie(['autoLogin' => $cookie], $currentUser, $identityRepository);
        self::assertSame($identity, $currentUser->getIdentity());
    }

    public function testLoginByCookieWithNonStringCookieReturns(): void
    {
        $service = new RememberMeCookieService(3600);
        $session = new FakeSession();
        $currentUser = $this->createCurrentUser();
        $identityRepository = $this->createMock(IdentityRepositoryInterface::class);

        $service->loginByCookie(['autoLogin' => 123], $currentUser, $identityRepository);
        self::assertFalse($session->has('__identity'));
    }

    public function testLoginByCookieWithNonUserIdentityDoesNotDispatchEvent(): void
    {
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->never())->method('dispatch');

        $service = new RememberMeCookieService(3600, eventDispatcher: $eventDispatcher);
        $currentUser = $this->createCurrentUser();
        $identity = $this->createMock(CookieLoginIdentityInterface::class);
        $identity->method('validateCookieLoginKey')->willReturn(true);
        $identityRepository = $this->createMock(IdentityRepositoryInterface::class);
        $identityRepository->method('findIdentity')->willReturn($identity);

        $future = time() + 3600;
        $cookie = json_encode(['id123', 'key123', $future]);
        $service->loginByCookie(['autoLogin' => $cookie], $currentUser, $identityRepository);

        self::assertSame($identity, $currentUser->getIdentity());
    }

    public function testLoginByCookieWithNullExpiresTreatsAsNeverExpiring(): void
    {
        $service = new RememberMeCookieService(3600);
        $currentUser = $this->createCurrentUser();
        $identity = $this->createMock(CookieLoginIdentityInterface::class);
        $identity->method('validateCookieLoginKey')->willReturn(true);
        $identityRepository = $this->createMock(IdentityRepositoryInterface::class);
        $identityRepository->method('findIdentity')->willReturn($identity);

        $cookie = json_encode(['id123', 'key123', null]);
        $service->loginByCookie(['autoLogin' => $cookie], $currentUser, $identityRepository);
        self::assertSame($identity, $currentUser->getIdentity());
    }

    public function testLoginByCookieWithoutEventDispatcherDoesNotError(): void
    {
        $service = new RememberMeCookieService(3600);
        $currentUser = $this->createCurrentUser();
        $identity = $this->createMock(User::class);
        $identity->method('validateCookieLoginKey')->willReturn(true);
        $identity->method('getId')->willReturn('id123');
        $identityRepository = $this->createMock(IdentityRepositoryInterface::class);
        $identityRepository->method('findIdentity')->willReturn($identity);

        $future = time() + 3600;
        $cookie = json_encode(['id123', 'key123', $future]);
        $service->loginByCookie(['autoLogin' => $cookie], $currentUser, $identityRepository);

        self::assertSame($identity, $currentUser->getIdentity());
    }

    public function testLoginByCookieWithUserIdentityDispatchesAfterLoginEvent(): void
    {
        $identity = $this->createMock(User::class);
        $identity->method('validateCookieLoginKey')->willReturn(true);
        $identity->method('getId')->willReturn('id123');

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(static fn (AfterLoginEvent $event): bool => $event->getUser() === $identity))
            ->willReturnArgument(0);

        $service = new RememberMeCookieService(3600, eventDispatcher: $eventDispatcher);
        $currentUser = $this->createCurrentUser();
        $identityRepository = $this->createMock(IdentityRepositoryInterface::class);
        $identityRepository->method('findIdentity')->willReturn($identity);

        $future = time() + 3600;
        $cookie = json_encode(['id123', 'key123', $future]);
        $service->loginByCookie(['autoLogin' => $cookie], $currentUser, $identityRepository);

        self::assertSame($identity, $currentUser->getIdentity());
    }

    public function testLoginByCookieWithZeroExpiresAndZeroNowReturns(): void
    {
        $nowClosure = $this->fixedNowClosure(0);
        $service = new RememberMeCookieService(3600, 'autoLogin', null, $nowClosure);
        $currentUser = $this->createCurrentUser();
        $identity = $this->createMock(CookieLoginIdentityInterface::class);
        $identity->method('validateCookieLoginKey')->willReturn(true);
        $identityRepository = $this->createMock(IdentityRepositoryInterface::class);
        $identityRepository->method('findIdentity')->willReturn($identity);

        $cookie = json_encode(['id123', 'key123', 0]);
        $service->loginByCookie(['autoLogin' => $cookie], $currentUser, $identityRepository);
        self::assertSame($identity, $currentUser->getIdentity());
    }

    public function testLoginByCookieWithZeroExpiresLogsIn(): void
    {
        $service = new RememberMeCookieService(3600);
        $currentUser = $this->createCurrentUser();
        $identity = $this->createMock(CookieLoginIdentityInterface::class);
        $identity->method('validateCookieLoginKey')->willReturn(true);
        $identityRepository = $this->createMock(IdentityRepositoryInterface::class);
        $identityRepository->method('findIdentity')->willReturn($identity);

        $cookie = json_encode(['id123', 'key123', 0.0]);
        $service->loginByCookie(['autoLogin' => $cookie], $currentUser, $identityRepository);
        self::assertSame($identity, $currentUser->getIdentity());
    }

    public function testNowClosureIsUsedNotRealTime(): void
    {
        $fakeNow = 1000;
        $nowClosure = $this->fixedNowClosure($fakeNow);
        $service = new RememberMeCookieService(3600, 'autoLogin', null, $nowClosure);
        $currentUser = $this->createCurrentUser();
        $identity = $this->createMock(CookieLoginIdentityInterface::class);
        $identity->method('validateCookieLoginKey')->willReturn(true);
        $identityRepository = $this->createMock(IdentityRepositoryInterface::class);
        $identityRepository->method('findIdentity')->willReturn($identity);

        $cookie = json_encode(['id123', 'key123', $fakeNow]);
        $service->loginByCookie(['autoLogin' => $cookie], $currentUser, $identityRepository);
        self::assertSame($identity, $currentUser->getIdentity());
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('refreshCookieBoundaryProvider')]
    public function testRefreshCookieBoundary(int $now, int|string $expires, bool $expectedEmitterCalled): void
    {
        [$emitterState, $emitter] = $this->createEmitterSpy();
        $service = new RememberMeCookieService(3600, 'autoLogin', $emitter, $this->fixedNowClosure($now));

        $currentUser = $this->loggedInIdentity();

        $_COOKIE['autoLogin'] = json_encode(['uid', 'ckey', $expires]);
        $service->refreshCookie($currentUser);
        self::assertSame($expectedEmitterCalled, $emitterState->called);
        unset($_COOKIE['autoLogin']);
    }

    public function testRefreshCookieIdentityNotCookieLoginReturns(): void
    {
        $now = 2000000;
        [$emitterState, $emitter] = $this->createEmitterSpy();
        $service = new RememberMeCookieService(3600, 'autoLogin', $emitter, $this->fixedNowClosure($now));

        $expires = $now - 100000;
        $_COOKIE['autoLogin'] = json_encode(['id', 'key', $expires]);
        $service->refreshCookie($this->createCurrentUser());
        self::assertFalse($emitterState->called);
        unset($_COOKIE['autoLogin']);
    }

    public function testRefreshCookieInvalidArrayReturns(): void
    {
        [$emitterState, $emitter] = $this->createEmitterSpy();
        $service = new RememberMeCookieService(3600, 'autoLogin', $emitter);

        $_COOKIE['autoLogin'] = json_encode(['a', 'b']);
        $service->refreshCookie($this->createCurrentUser());
        self::assertFalse($emitterState->called);
        unset($_COOKIE['autoLogin']);
    }

    public function testRefreshCookieInvalidJsonReturns(): void
    {
        [$emitterState, $emitter] = $this->createEmitterSpy();
        $service = new RememberMeCookieService(3600, 'autoLogin', $emitter);

        $_COOKIE['autoLogin'] = 'not-json';
        $service->refreshCookie($this->createCurrentUser());
        self::assertFalse($emitterState->called);
        unset($_COOKIE['autoLogin']);
    }

    public function testRefreshCookieNoCookieReturns(): void
    {
        [$emitterState, $emitter] = $this->createEmitterSpy();
        $service = new RememberMeCookieService(3600, 'autoLogin', $emitter);

        unset($_COOKIE['autoLogin']);
        $service->refreshCookie($this->createCurrentUser());
        self::assertFalse($emitterState->called);
    }

    public function testRefreshCookieNotEnoughTimePassedReturns(): void
    {
        $now = 1000000;
        [$emitterState, $emitter] = $this->createEmitterSpy();
        $service = new RememberMeCookieService(3600, 'autoLogin', $emitter, $this->fixedNowClosure($now));

        $expires = $now + 3600;
        $_COOKIE['autoLogin'] = json_encode(['id', 'key', $expires]);
        $service->refreshCookie($this->createCurrentUser());
        self::assertFalse($emitterState->called);
        unset($_COOKIE['autoLogin']);
    }

    public function testRefreshCookieSuccess(): void
    {
        $now = 2000000;
        $captured = [];
        $emitter = static function (string $name, string $value, array $options) use (&$captured): bool {
            $captured = ['name' => $name, 'value' => $value, 'options' => $options];
            return true;
        };
        $service = new RememberMeCookieService(3600, 'autoLogin', $emitter, $this->fixedNowClosure($now));

        $session = new FakeSession();
        $currentUser = $this->loggedInIdentity('u/ñid', 'c/ñkey');

        $expires = $now - 90000;
        $_COOKIE['autoLogin'] = json_encode(['id', 'key', $expires]);
        $service->refreshCookie($currentUser);
        self::assertNotEmpty($captured);

        $value = $captured['value'];
        self::assertStringContainsString('u/ñid', $value);
        self::assertStringContainsString('c/ñkey', $value);
        self::assertStringNotContainsString('\u00f1', $value);
        $decoded = json_decode($value, true);
        self::assertSame(['u/ñid', 'c/ñkey', $now + 3600], $decoded);
        self::assertArrayHasKey('expires', $captured['options']);
        self::assertSame($now + 3600, $captured['options']['expires']);
        self::assertTrue($captured['options']['secure']);
        self::assertTrue($captured['options']['httponly']);
        unset($_COOKIE['autoLogin']);
    }

    public function testRefreshCookieUsesExpiresNotKeyForLastRefresh(): void
    {
        $now = 100000;
        [$emitterState, $emitter] = $this->createEmitterSpy();
        $service = new RememberMeCookieService(3600, 'autoLogin', $emitter, $this->fixedNowClosure($now));

        $currentUser = $this->loggedInIdentity();

        $cookie = json_encode(['uid', 1000, 1000000]);
        $_COOKIE['autoLogin'] = $cookie;
        $service->refreshCookie($currentUser);
        self::assertFalse($emitterState->called);
        unset($_COOKIE['autoLogin']);
    }

    public function testRefreshCookieWithFloatNowEmitsIntegerExpiry(): void
    {
        $nowClosure = static function (): float {
            return 2000000.7;
        };
        $captured = [];
        $emitter = static function (string $name, string $value, array $options) use (&$captured): bool {
            $captured = ['name' => $name, 'value' => $value, 'options' => $options];
            return true;
        };
        $service = new RememberMeCookieService(3600, 'autoLogin', $emitter, $nowClosure);

        $currentUser = $this->loggedInIdentity();

        $expires = 1910000;
        $_COOKIE['autoLogin'] = json_encode(['uid', 'ckey', $expires]);
        $service->refreshCookie($currentUser);

        self::assertNotEmpty($captured);
        $decoded = json_decode($captured['value'], true);
        self::assertSame(2003600, $decoded[2]);
        unset($_COOKIE['autoLogin']);
    }

    public function testRefreshCookieWithNonPositiveDurationReturns(): void
    {
        [$emitterState, $emitter] = $this->createEmitterSpy();
        $service = new RememberMeCookieService(0, 'autoLogin', $emitter);

        $_COOKIE['autoLogin'] = 'data';
        $service->refreshCookie($this->createCurrentUser());
        self::assertFalse($emitterState->called);
        unset($_COOKIE['autoLogin']);
    }

    public function testRefreshCookieWithZeroDurationAndValidPathDoesNotEmit(): void
    {
        $now = 100000;
        [$emitterState, $emitter] = $this->createEmitterSpy();
        $service = new RememberMeCookieService(0, 'autoLogin', $emitter, $this->fixedNowClosure($now));

        $currentUser = $this->loggedInIdentity();

        $expires = $now - 90000;
        $_COOKIE['autoLogin'] = json_encode(['uid', 'ckey', $expires]);
        $service->refreshCookie($currentUser);
        self::assertFalse($emitterState->called);
        unset($_COOKIE['autoLogin']);
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

    /**
     * @return array{0: \stdClass, 1: \Closure}
     */
    private function createEmitterSpy(): array
    {
        $state = new \stdClass();
        $state->called = false;
        $emitter = static function () use ($state): bool {
            $state->called = true;
            return true;
        };
        return [$state, $emitter];
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
