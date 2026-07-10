<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Service;

use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use YiiRocks\Voyti\Service\RememberMeCookieService;
use YiiRocks\Voyti\tests\Support\FakeSession;
use Yiisoft\Auth\IdentityRepositoryInterface;
use Yiisoft\User\CurrentUser;
use Yiisoft\User\Login\Cookie\CookieLoginIdentityInterface;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
final class RememberMeCookieServiceTest extends TestCase
{
    public function testAddCookieWithPositiveDuration(): void
    {
        $service = new RememberMeCookieService(3600);
        $identity = $this->createMock(CookieLoginIdentityInterface::class);
        $response = new Response();

        $result = $service->addCookie($identity, $response);
        self::assertInstanceOf(Response::class, $result);
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

    public function testAddCookieWithZeroDuration(): void
    {
        $service = new RememberMeCookieService(0);
        $identity = $this->createMock(CookieLoginIdentityInterface::class);
        $response = new Response();

        $result = $service->addCookie($identity, $response);
        self::assertInstanceOf(Response::class, $result);
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

    public function testLoginByCookieWithZeroExpiresAndZeroNowReturns(): void
    {
        $nowClosure = static function (): int {
            return 0;
        };
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
        $nowClosure = static function () use ($fakeNow): int {
            return $fakeNow;
        };
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

    public function testRefreshCookieIdentityNotCookieLoginReturns(): void
    {
        $now = 2000000;
        $nowClosure = static function () use ($now): int {
            return $now;
        };
        $emitterCalled = false;
        $emitter = static function () use (&$emitterCalled): bool {
            $emitterCalled = true;
            return true;
        };
        $service = new RememberMeCookieService(3600, 'autoLogin', $emitter, $nowClosure);

        $expires = $now - 100000;
        $_COOKIE['autoLogin'] = json_encode(['id', 'key', $expires]);
        $service->refreshCookie($this->createCurrentUser());
        self::assertFalse($emitterCalled);
        unset($_COOKIE['autoLogin']);
    }

    public function testRefreshCookieInvalidArrayReturns(): void
    {
        $emitterCalled = false;
        $emitter = static function () use (&$emitterCalled): bool {
            $emitterCalled = true;
            return true;
        };
        $service = new RememberMeCookieService(3600, 'autoLogin', $emitter);

        $_COOKIE['autoLogin'] = json_encode(['a', 'b']);
        $service->refreshCookie($this->createCurrentUser());
        self::assertFalse($emitterCalled);
        unset($_COOKIE['autoLogin']);
    }

    public function testRefreshCookieInvalidJsonReturns(): void
    {
        $emitterCalled = false;
        $emitter = static function () use (&$emitterCalled): bool {
            $emitterCalled = true;
            return true;
        };
        $service = new RememberMeCookieService(3600, 'autoLogin', $emitter);

        $_COOKIE['autoLogin'] = 'not-json';
        $service->refreshCookie($this->createCurrentUser());
        self::assertFalse($emitterCalled);
        unset($_COOKIE['autoLogin']);
    }

    public function testRefreshCookieLessThanBoundaryEmits(): void
    {
        $now = 100000;
        $nowClosure = static function () use ($now): int {
            return $now;
        };
        $emitterCalled = false;
        $emitter = static function () use (&$emitterCalled): bool {
            $emitterCalled = true;
            return true;
        };
        $service = new RememberMeCookieService(3600, 'autoLogin', $emitter, $nowClosure);

        $identity = $this->createMock(CookieLoginIdentityInterface::class);
        $identity->method('getId')->willReturn('uid');
        $identity->method('getCookieLoginKey')->willReturn('ckey');
        $currentUser = $this->createCurrentUser();
        $currentUser->login($identity);

        $expires = 17200;
        $_COOKIE['autoLogin'] = json_encode(['uid', 'ckey', $expires]);
        $service->refreshCookie($currentUser);
        self::assertTrue($emitterCalled);
        unset($_COOKIE['autoLogin']);
    }

    public function testRefreshCookieMinusOperatorOnDurationEmits(): void
    {
        $now = 1000000;
        $nowClosure = static function () use ($now): int {
            return $now;
        };
        $emitterCalled = false;
        $emitter = static function () use (&$emitterCalled): bool {
            $emitterCalled = true;
            return true;
        };
        $service = new RememberMeCookieService(3600, 'autoLogin', $emitter, $nowClosure);

        $identity = $this->createMock(CookieLoginIdentityInterface::class);
        $identity->method('getId')->willReturn('uid');
        $identity->method('getCookieLoginKey')->willReturn('ckey');
        $currentUser = $this->createCurrentUser();
        $currentUser->login($identity);

        $expires = 913600;
        $_COOKIE['autoLogin'] = json_encode(['uid', 'ckey', $expires]);
        $service->refreshCookie($currentUser);
        self::assertTrue($emitterCalled);
        unset($_COOKIE['autoLogin']);
    }

    public function testRefreshCookieMinusOperatorOnNowLastRefreshBoundary(): void
    {
        $now = 100000;
        $nowClosure = static function () use ($now): int {
            return $now;
        };
        $emitterCalled = false;
        $emitter = static function () use (&$emitterCalled): bool {
            $emitterCalled = true;
            return true;
        };
        $service = new RememberMeCookieService(3600, 'autoLogin', $emitter, $nowClosure);

        $identity = $this->createMock(CookieLoginIdentityInterface::class);
        $identity->method('getId')->willReturn('uid');
        $identity->method('getCookieLoginKey')->willReturn('ckey');
        $currentUser = $this->createCurrentUser();
        $currentUser->login($identity);

        $expires = -20000;
        $_COOKIE['autoLogin'] = json_encode(['uid', 'ckey', $expires]);
        $service->refreshCookie($currentUser);
        self::assertTrue($emitterCalled);
        unset($_COOKIE['autoLogin']);
    }

    public function testRefreshCookieNoCookieReturns(): void
    {
        $emitterCalled = false;
        $emitter = static function () use (&$emitterCalled): bool {
            $emitterCalled = true;
            return true;
        };
        $service = new RememberMeCookieService(3600, 'autoLogin', $emitter);

        unset($_COOKIE['autoLogin']);
        $service->refreshCookie($this->createCurrentUser());
        self::assertFalse($emitterCalled);
    }

    public function testRefreshCookieNotEnoughTimePassedReturns(): void
    {
        $now = 1000000;
        $nowClosure = static function () use ($now): int {
            return $now;
        };
        $emitterCalled = false;
        $emitter = static function () use (&$emitterCalled): bool {
            $emitterCalled = true;
            return true;
        };
        $service = new RememberMeCookieService(3600, 'autoLogin', $emitter, $nowClosure);

        $expires = $now + 3600;
        $_COOKIE['autoLogin'] = json_encode(['id', 'key', $expires]);
        $service->refreshCookie($this->createCurrentUser());
        self::assertFalse($emitterCalled);
        unset($_COOKIE['autoLogin']);
    }

    public function testRefreshCookieNotEnoughTimePassesBoundaryDoesNotEmit(): void
    {
        $now = 100000;
        $nowClosure = static function () use ($now): int {
            return $now;
        };
        $emitterCalled = false;
        $emitter = static function () use (&$emitterCalled): bool {
            $emitterCalled = true;
            return true;
        };
        $service = new RememberMeCookieService(3600, 'autoLogin', $emitter, $nowClosure);

        $identity = $this->createMock(CookieLoginIdentityInterface::class);
        $identity->method('getId')->willReturn('uid');
        $identity->method('getCookieLoginKey')->willReturn('ckey');
        $currentUser = $this->createCurrentUser();
        $currentUser->login($identity);

        $expires = 100000;
        $_COOKIE['autoLogin'] = json_encode(['uid', 'ckey', $expires]);
        $service->refreshCookie($currentUser);
        self::assertFalse($emitterCalled);
        unset($_COOKIE['autoLogin']);
    }

    public function testRefreshCookieSuccess(): void
    {
        $now = 2000000;
        $nowClosure = static function () use ($now): int {
            return $now;
        };
        $captured = [];
        $emitter = static function (string $name, string $value, array $options) use (&$captured): bool {
            $captured = ['name' => $name, 'value' => $value, 'options' => $options];
            return true;
        };
        $service = new RememberMeCookieService(3600, 'autoLogin', $emitter, $nowClosure);

        $identity = $this->createMock(CookieLoginIdentityInterface::class);
        $identity->method('getId')->willReturn('u/ñid');
        $identity->method('getCookieLoginKey')->willReturn('c/ñkey');

        $session = new FakeSession();
        $currentUser = $this->createCurrentUser();
        $currentUser->login($identity);

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
        $nowClosure = static function () use ($now): int {
            return $now;
        };
        $emitterCalled = false;
        $emitter = static function () use (&$emitterCalled): bool {
            $emitterCalled = true;
            return true;
        };
        $service = new RememberMeCookieService(3600, 'autoLogin', $emitter, $nowClosure);

        $identity = $this->createMock(CookieLoginIdentityInterface::class);
        $identity->method('getId')->willReturn('uid');
        $identity->method('getCookieLoginKey')->willReturn('ckey');
        $currentUser = $this->createCurrentUser();
        $currentUser->login($identity);

        $cookie = json_encode(['uid', 1000, 1000000]);
        $_COOKIE['autoLogin'] = $cookie;
        $service->refreshCookie($currentUser);
        self::assertFalse($emitterCalled);
        unset($_COOKIE['autoLogin']);
    }

    public function testRefreshCookieWithNonPositiveDurationReturns(): void
    {
        $emitterCalled = false;
        $emitter = static function () use (&$emitterCalled): bool {
            $emitterCalled = true;
            return true;
        };
        $service = new RememberMeCookieService(0, 'autoLogin', $emitter);

        $_COOKIE['autoLogin'] = 'data';
        $service->refreshCookie($this->createCurrentUser());
        self::assertFalse($emitterCalled);
        unset($_COOKIE['autoLogin']);
    }

    public function testRefreshCookieWithZeroDurationAndValidPathDoesNotEmit(): void
    {
        $now = 100000;
        $nowClosure = static function () use ($now): int {
            return $now;
        };
        $emitterCalled = false;
        $emitter = static function () use (&$emitterCalled): bool {
            $emitterCalled = true;
            return true;
        };
        $service = new RememberMeCookieService(0, 'autoLogin', $emitter, $nowClosure);

        $identity = $this->createMock(CookieLoginIdentityInterface::class);
        $identity->method('getId')->willReturn('uid');
        $identity->method('getCookieLoginKey')->willReturn('ckey');
        $currentUser = $this->createCurrentUser();
        $currentUser->login($identity);

        $expires = $now - 90000;
        $_COOKIE['autoLogin'] = json_encode(['uid', 'ckey', $expires]);
        $service->refreshCookie($currentUser);
        self::assertFalse($emitterCalled);
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
}
