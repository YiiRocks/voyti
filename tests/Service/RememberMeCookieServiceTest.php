<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Service;

use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use YiiRocks\Voyti\Service\RememberMeCookieService;
use Yiisoft\Auth\IdentityInterface;
use Yiisoft\Auth\IdentityRepositoryInterface;
use Yiisoft\User\CurrentUser;
use Yiisoft\User\Login\Cookie\CookieLoginIdentityInterface;

final class RememberMeCookieServiceTest extends TestCase
{
    private const int FIXED_NOW = 1_700_000_000;

    public function testAddCookieAddsConfiguredCookieToResponse(): void
    {
        $service = new RememberMeCookieService(3600, 'rememberMe');
        $response = (new Psr17Factory())->createResponse();

        $response = $service->addCookie($this->identity(), $response);
        $header = $response->getHeaderLine('Set-Cookie');

        self::assertStringContainsString('"user-1"', urldecode($header));
        self::assertStringContainsString('"secret-key"', urldecode($header));
        self::assertStringContainsString('Expires=', $header);
    }

    public function testAddCookieWithZeroDurationCreatesSessionCookiePayload(): void
    {
        $service = new RememberMeCookieService(0, 'rememberMe');
        $response = (new Psr17Factory())->createResponse();

        $response = $service->addCookie($this->identity(), $response);
        $header = urldecode($response->getHeaderLine('Set-Cookie'));

        self::assertStringContainsString('rememberMe=["user-1","secret-key",0]', $header);
    }

    public function testExpireCookieAddsExpiredCookieHeader(): void
    {
        $service = new RememberMeCookieService(3600, 'rememberMe');
        $response = (new Psr17Factory())->createResponse();

        $response = $service->expireCookie($response);
        $header = $response->getHeaderLine('Set-Cookie');

        self::assertStringContainsString('Expires=', $header);
    }

    public function testGetCookieNameReturnsConfiguredName(): void
    {
        $service = new RememberMeCookieService(3600, 'rememberMe');

        self::assertSame('rememberMe', $service->getCookieName());
    }

    public function testLoginByCookieAcceptsCookieAtSupportedDepthLimit(): void
    {
        $service = new RememberMeCookieService(
            3600,
            'rememberMe',
            null,
            static fn (): int => 0,
        );
        $identity = $this->identity();
        $currentUser = $this->currentUser($identity);

        $service->loginByCookie(
            ['rememberMe' => $this->deepCookieJson(510)],
            $currentUser,
            $this->identityRepository($identity),
        );

        self::assertSame('user-1', $currentUser->getId());
    }

    public function testLoginByCookieAcceptsExpirationAtCurrentSecondBoundary(): void
    {
        $service = new RememberMeCookieService(
            3600,
            'rememberMe',
            null,
            static fn (): int => self::FIXED_NOW,
        );
        $identity = $this->identity();
        $currentUser = $this->currentUser($identity);

        $service->loginByCookie(
            ['rememberMe' => json_encode(['user-1', 'secret-key', self::FIXED_NOW], JSON_THROW_ON_ERROR)],
            $currentUser,
            $this->identityRepository($identity),
        );

        self::assertSame('user-1', $currentUser->getId());
    }

    public function testLoginByCookieAcceptsStringZeroExpiration(): void
    {
        $service = new RememberMeCookieService(3600, 'rememberMe');
        $identity = $this->identity();
        $currentUser = $this->currentUser($identity);
        $repository = $this->identityRepository($identity);

        $service->loginByCookie(
            ['rememberMe' => json_encode(['user-1', 'secret-key', '0'], JSON_THROW_ON_ERROR)],
            $currentUser,
            $repository,
        );

        self::assertSame('user-1', $currentUser->getId());
    }

    public function testLoginByCookieCastsFloatNowToIntBeforeExpiryComparison(): void
    {
        $service = new RememberMeCookieService(
            3600,
            'rememberMe',
            null,
            static fn (): float => 1_700_000_000.5,
        );
        $identity = $this->identity();
        $currentUser = $this->currentUser($identity);

        $service->loginByCookie(
            ['rememberMe' => json_encode(['user-1', 'secret-key', 1_700_000_000], JSON_THROW_ON_ERROR)],
            $currentUser,
            $this->identityRepository($identity),
        );

        self::assertSame('user-1', $currentUser->getId());
    }

    public function testLoginByCookieDoesNothingWhenCookieIsExpired(): void
    {
        $service = new RememberMeCookieService(3600, 'rememberMe');
        $identity = $this->identity();
        $currentUser = $this->currentUser($identity);

        $service->loginByCookie(
            ['rememberMe' => json_encode(['user-1', 'secret-key', time() - 1], JSON_THROW_ON_ERROR)],
            $currentUser,
            $this->identityRepository($identity),
        );

        self::assertNull($currentUser->getId());
    }

    public function testLoginByCookieDoesNothingWhenCookieNameDoesNotMatch(): void
    {
        $service = new RememberMeCookieService(3600, 'rememberMe');
        $identity = $this->identity();
        $currentUser = $this->currentUser($identity);

        $service->loginByCookie(
            ['other' => json_encode(['user-1', 'secret-key', time() + 3600], JSON_THROW_ON_ERROR)],
            $currentUser,
            $this->identityRepository($identity),
        );

        self::assertNull($currentUser->getId());
        self::assertTrue($currentUser->isGuest());
    }

    public function testLoginByCookieDoesNothingWhenIdentityDoesNotImplementCookieInterface(): void
    {
        $service = new RememberMeCookieService(3600, 'rememberMe');
        $currentUser = $this->currentUser($this->identity());
        $repository = new class implements IdentityRepositoryInterface {
            #[\Override]
            public function findIdentity(string $id): ?IdentityInterface
            {
                return new class($id) implements IdentityInterface {
                    public function __construct(private readonly string $id)
                    {
                    }

                    #[\Override]
                    public function getId(): ?string
                    {
                        return $this->id;
                    }
                };
            }
        };

        $service->loginByCookie(
            ['rememberMe' => json_encode(['user-1', 'secret-key', time() + 3600], JSON_THROW_ON_ERROR)],
            $currentUser,
            $repository,
        );

        self::assertNull($currentUser->getId());
    }

    public function testLoginByCookieDoesNothingWhenKeyIsInvalid(): void
    {
        $service = new RememberMeCookieService(3600, 'rememberMe');
        $identity = $this->identity();
        $currentUser = $this->currentUser($identity);

        $service->loginByCookie(
            ['rememberMe' => json_encode(['user-1', 'wrong-key', time() + 3600], JSON_THROW_ON_ERROR)],
            $currentUser,
            $this->identityRepository($identity),
        );

        self::assertNull($currentUser->getId());
    }

    public function testLoginByCookieDoesNothingWithInvalidJson(): void
    {
        $service = new RememberMeCookieService(3600, 'rememberMe');
        $identity = $this->identity();
        $currentUser = $this->currentUser($identity);

        $service->loginByCookie(
            ['rememberMe' => '{invalid'],
            $currentUser,
            $this->identityRepository($identity),
        );

        self::assertNull($currentUser->getId());
    }

    public function testLoginByCookieDoesNothingWithWrongCookieShape(): void
    {
        $service = new RememberMeCookieService(3600, 'rememberMe');
        $identity = $this->identity();
        $currentUser = $this->currentUser($identity);

        $service->loginByCookie(
            ['rememberMe' => json_encode(['user-1', 'secret-key'], JSON_THROW_ON_ERROR)],
            $currentUser,
            $this->identityRepository($identity),
        );

        self::assertNull($currentUser->getId());
    }

    public function testLoginByCookieLogsUserInWhenCookieNeverExpires(): void
    {
        $service = new RememberMeCookieService(3600, 'rememberMe');
        $identity = $this->identity();
        $currentUser = $this->currentUser($identity);
        $repository = $this->identityRepository($identity);

        $service->loginByCookie(
            ['rememberMe' => json_encode(['user-1', 'secret-key', 0], JSON_THROW_ON_ERROR)],
            $currentUser,
            $repository,
        );

        self::assertSame('user-1', $currentUser->getId());
    }

    public function testLoginByCookieLogsUserInWithValidPersistentCookie(): void
    {
        $service = new RememberMeCookieService(3600, 'rememberMe');
        $identity = $this->identity();
        $currentUser = $this->currentUser($identity);
        $repository = $this->identityRepository($identity);
        $expires = time() + 3600;

        $service->loginByCookie(
            ['rememberMe' => json_encode(['user-1', 'secret-key', $expires], JSON_THROW_ON_ERROR)],
            $currentUser,
            $repository,
        );

        self::assertSame('user-1', $currentUser->getId());
        self::assertFalse($currentUser->isGuest());
    }

    public function testLoginByCookieRejectsCookieBeyondDepthLimit(): void
    {
        $service = new RememberMeCookieService(
            3600,
            'rememberMe',
            null,
            static fn (): int => 0,
        );
        $identity = $this->identity();
        $currentUser = $this->currentUser($identity);

        $service->loginByCookie(
            ['rememberMe' => $this->deepCookieJson(511)],
            $currentUser,
            $this->identityRepository($identity),
        );

        self::assertNull($currentUser->getId());
        self::assertTrue($currentUser->isGuest());
    }

    public function testLoginByCookieRejectsWeirdExpiryStringThatCastsToPastInteger(): void
    {
        $service = new RememberMeCookieService(
            3600,
            'rememberMe',
            null,
            static fn (): int => self::FIXED_NOW,
        );
        $identity = $this->identity();
        $currentUser = $this->currentUser($identity);

        $service->loginByCookie(
            ['rememberMe' => json_encode(['user-1', 'secret-key', '1e9abc'], JSON_THROW_ON_ERROR)],
            $currentUser,
            $this->identityRepository($identity),
        );

        self::assertNull($currentUser->getId());
        self::assertTrue($currentUser->isGuest());
    }

    public function testRefreshCookieAcceptsCookieAtSupportedDepthLimit(): void
    {
        $called = false;
        $service = new RememberMeCookieService(
            3600,
            'rememberMe',
            static function (string $name, string $value, array $options) use (&$called): bool {
                $called = true;
                return true;
            },
            static fn (): int => self::FIXED_NOW,
        );
        $currentUser = $this->currentUser($this->identity());
        $currentUser->overrideIdentity($this->identity());
        $_COOKIE['rememberMe'] = $this->deepCookieJson(510);

        try {
            $service->refreshCookie($currentUser);
        } finally {
            unset($_COOKIE['rememberMe']);
        }

        self::assertTrue($called);
    }

    public function testRefreshCookieCastsFloatNowToIntForExpiresTimestamp(): void
    {
        $captured = [];
        $service = new RememberMeCookieService(
            3600,
            'rememberMe',
            static function (string $name, string $value, array $options) use (&$captured): bool {
                $captured = [$name, $value, $options];
                return true;
            },
            static fn (): float => 1_700_086_400.9,
        );
        $currentUser = $this->currentUser($this->identity());
        $currentUser->overrideIdentity($this->identity());
        $_COOKIE['rememberMe'] = json_encode(['user-1', 'secret-key', 1_700_003_600], JSON_THROW_ON_ERROR);

        try {
            $service->refreshCookie($currentUser);
        } finally {
            unset($_COOKIE['rememberMe']);
        }

        self::assertSame(1_700_090_000, $captured[2]['expires'] ?? null);
    }

    public function testRefreshCookieDoesNothingForInvalidCookieShape(): void
    {
        $called = false;
        $service = new RememberMeCookieService(
            3600,
            'rememberMe',
            static function (string $name, string $value, array $options) use (&$called): bool {
                $called = true;
                return true;
            },
        );
        $currentUser = $this->currentUser($this->identity());
        $currentUser->overrideIdentity($this->identity());
        $_COOKIE['rememberMe'] = json_encode(['user-1', 'secret-key'], JSON_THROW_ON_ERROR);

        try {
            $service->refreshCookie($currentUser);
        } finally {
            unset($_COOKIE['rememberMe']);
        }

        self::assertFalse($called);
    }

    public function testRefreshCookieDoesNothingForNonCookieIdentity(): void
    {
        $called = false;
        $service = new RememberMeCookieService(
            3600,
            'rememberMe',
            static function (string $name, string $value, array $options) use (&$called): bool {
                $called = true;
                return true;
            },
        );
        $currentUser = new CurrentUser(
            $this->identityRepository($this->identity()),
            $this->eventDispatcher(),
        );
        $currentUser->overrideIdentity(new class implements IdentityInterface {
            #[\Override]
            public function getId(): ?string
            {
                return 'user-1';
            }
        });
        $_COOKIE['rememberMe'] = json_encode(['user-1', 'secret-key', time() - 86401 + 3600], JSON_THROW_ON_ERROR);

        try {
            $service->refreshCookie($currentUser);
        } finally {
            unset($_COOKIE['rememberMe']);
        }

        self::assertFalse($called);
    }

    public function testRefreshCookieDoesNothingForRecentlyRefreshedCookie(): void
    {
        $called = false;
        $service = new RememberMeCookieService(
            3600,
            'rememberMe',
            static function (string $name, string $value, array $options) use (&$called): bool {
                $called = true;
                return true;
            },
        );
        $currentUser = $this->currentUser($this->identity());
        $currentUser->overrideIdentity($this->identity());
        $_COOKIE['rememberMe'] = json_encode(['user-1', 'secret-key', time() + 3600], JSON_THROW_ON_ERROR);

        try {
            $service->refreshCookie($currentUser);
        } finally {
            unset($_COOKIE['rememberMe']);
        }

        self::assertFalse($called);
    }

    public function testRefreshCookieDoesNothingWhenDurationIsZero(): void
    {
        $called = false;
        $service = new RememberMeCookieService(
            0,
            'rememberMe',
            static function (string $name, string $value, array $options) use (&$called): bool {
                $called = true;
                return true;
            },
        );
        $currentUser = $this->currentUser($this->identity());
        $currentUser->overrideIdentity($this->identity());
        $_COOKIE['rememberMe'] = json_encode(['user-1', 'secret-key', time() - 86401], JSON_THROW_ON_ERROR);

        try {
            $service->refreshCookie($currentUser);
        } finally {
            unset($_COOKIE['rememberMe']);
        }

        self::assertFalse($called);
    }

    public function testRefreshCookieKeepsSlashAndUnicodeUnescapedInPayload(): void
    {
        $captured = [];
        $service = new RememberMeCookieService(
            3600,
            'rememberMe',
            static function (string $name, string $value, array $options) use (&$captured): bool {
                $captured = [$name, $value, $options];
                return true;
            },
            static fn (): int => self::FIXED_NOW,
        );
        $identity = $this->identity('user/ä', 'secret/ß');
        $currentUser = $this->currentUser($identity);
        $currentUser->overrideIdentity($identity);
        $_COOKIE['rememberMe'] = json_encode(
            ['user/ä', 'secret/ß', self::FIXED_NOW - 86401 + 3600],
            JSON_THROW_ON_ERROR,
        );

        try {
            $service->refreshCookie($currentUser);
        } finally {
            unset($_COOKIE['rememberMe']);
        }

        self::assertSame('["user/ä","secret/ß",1700003600]', $captured[1] ?? null);
    }

    public function testRefreshCookieRefreshesAtDayBoundaryUsingIntegerExpiration(): void
    {
        $captured = [];
        $service = new RememberMeCookieService(
            3600,
            'rememberMe',
            static function (string $name, string $value, array $options) use (&$captured): bool {
                $captured = [$name, $value, $options];
                return true;
            },
            static fn (): int => self::FIXED_NOW,
        );
        $currentUser = $this->currentUser($this->identity());
        $currentUser->overrideIdentity($this->identity());
        $_COOKIE['rememberMe'] = json_encode(
            ['user-1', 'secret-key', self::FIXED_NOW - 86400 + 3600 + 0.9],
            JSON_THROW_ON_ERROR,
        );

        try {
            $service->refreshCookie($currentUser);
        } finally {
            unset($_COOKIE['rememberMe']);
        }

        self::assertSame(self::FIXED_NOW + 3600, $captured[2]['expires'] ?? null);
    }

    public function testRefreshCookieRefreshesOldCookieForCookieIdentity(): void
    {
        $captured = [];
        $service = new RememberMeCookieService(
            3600,
            'rememberMe',
            static function (string $name, string $value, array $options) use (&$captured): bool {
                $captured = [$name, $value, $options];
                return true;
            },
        );
        $currentUser = $this->currentUser($this->identity());
        $currentUser->overrideIdentity($this->identity());
        $_COOKIE['rememberMe'] = json_encode(['user-1', 'secret-key', time() - 86401 + 3600], JSON_THROW_ON_ERROR);

        try {
            $service->refreshCookie($currentUser);
        } finally {
            unset($_COOKIE['rememberMe']);
        }

        self::assertSame('rememberMe', $captured[0] ?? null);
        $payload = json_decode($captured[1], true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('user-1', $payload[0] ?? null);
        self::assertSame('secret-key', $payload[1] ?? null);
        self::assertCount(3, $payload);
        self::assertSame('/', $captured[2]['path'] ?? null);
        self::assertTrue($captured[2]['secure'] ?? false);
        self::assertTrue($captured[2]['httponly'] ?? false);
        self::assertSame('Lax', $captured[2]['samesite'] ?? null);
        self::assertGreaterThan(time(), $captured[2]['expires'] ?? 0);
    }

    public function testRefreshCookieSilentlyIgnoresTooDeepCookieAtCurrentDepthLimit(): void
    {
        $called = false;
        $service = new RememberMeCookieService(
            3600,
            'rememberMe',
            static function (string $name, string $value, array $options) use (&$called): bool {
                $called = true;
                return true;
            },
            static fn (): int => self::FIXED_NOW,
        );
        $currentUser = $this->currentUser($this->identity());
        $currentUser->overrideIdentity($this->identity());
        $_COOKIE['rememberMe'] = $this->deepCookieJson(511);

        try {
            $service->refreshCookie($currentUser);
        } finally {
            unset($_COOKIE['rememberMe']);
        }

        self::assertFalse($called);
    }

    private function currentUser(CookieLoginIdentityInterface $identity): CurrentUser
    {
        return new CurrentUser(
            $this->identityRepository($identity),
            $this->eventDispatcher(),
        );
    }

    private function deepCookieJson(int $levels): string
    {
        $expires = 0;

        for ($i = 0; $i < $levels; $i++) {
            $expires = [$expires];
        }

        return json_encode(['user-1', 'secret-key', $expires], JSON_THROW_ON_ERROR);
    }

    private function eventDispatcher(): EventDispatcherInterface
    {
        return new class implements EventDispatcherInterface {
            #[\Override]
            public function dispatch(object $event): object
            {
                return $event;
            }
        };
    }

    private function identity(string $id = 'user-1', string $key = 'secret-key'): CookieLoginIdentityInterface
    {
        return new class($id, $key) implements CookieLoginIdentityInterface {
            public function __construct(
                private readonly string $id,
                private readonly string $key,
            ) {
            }

            #[\Override]
            public function getId(): ?string
            {
                return $this->id;
            }

            #[\Override]
            public function getCookieLoginKey(): string
            {
                return $this->key;
            }

            #[\Override]
            public function validateCookieLoginKey(string $key): bool
            {
                return $key === $this->key;
            }
        };
    }

    private function identityRepository(IdentityInterface $identity): IdentityRepositoryInterface
    {
        return new class($identity) implements IdentityRepositoryInterface {
            public function __construct(private readonly IdentityInterface $identity)
            {
            }

            #[\Override]
            public function findIdentity(string $id): ?IdentityInterface
            {
                return $id === $this->identity->getId() ? $this->identity : null;
            }
        };
    }
}
