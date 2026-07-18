<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Service\Password;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Service\Password\ExpireService;
use YiiRocks\Voyti\tests\Support\DatabaseSetupTrait;

final class ExpireServiceTest extends TestCase
{
    use DatabaseSetupTrait;

    protected function setUp(): void
    {
        $this->setUpDatabase();
    }

    protected function tearDown(): void
    {
        $this->tearDownDatabase();
    }

    /**
     * @return iterable<string, array{int, bool}>
     */
    public static function checkPasswordExpirationAgeProvider(): iterable
    {
        yield 'expired' => [31, true];
        yield 'not expired' => [15, false];
    }

    /**
     * @return iterable<string, array{int, bool}>
     */
    public static function isExpiredAgeProvider(): iterable
    {
        yield 'age above max' => [100, true];
        yield 'age at max' => [90, true];
        yield 'age below max' => [50, false];
    }

    public function testCheckPasswordExpirationDisabledIgnoresExpiredUser(): void
    {
        $config = new ModuleConfig(enablePasswordExpiration: false, maxPasswordAge: 30);
        $service = new ExpireService($config);
        $user = $this->createUser();
        $user->setPasswordChangedAt(time() - 100 * 86400);

        self::assertFalse($service->checkPasswordExpiration($user));
    }

    public function testCheckPasswordExpirationDisabledReturnsFalse(): void
    {
        $config = new ModuleConfig(enablePasswordExpiration: false);
        $service = new ExpireService($config);
        $user = $this->createUser();

        self::assertFalse($service->checkPasswordExpiration($user));
    }

    #[DataProvider('checkPasswordExpirationAgeProvider')]
    public function testCheckPasswordExpirationEnabled(int $ageDays, bool $expected): void
    {
        $config = new ModuleConfig(enablePasswordExpiration: true, maxPasswordAge: 30);
        $service = new ExpireService($config);
        $user = $this->createUser();
        $user->setPasswordChangedAt(time() - $ageDays * 86400);

        self::assertSame($expected, $service->checkPasswordExpiration($user));
    }

    #[DataProvider('isExpiredAgeProvider')]
    public function testIsExpiredWithAge(int $ageDays, bool $expected): void
    {
        $config = new ModuleConfig(maxPasswordAge: 90);
        $service = new ExpireService($config);
        $user = $this->createUser();
        $user->setPasswordChangedAt(time() - $ageDays * 86400);

        self::assertSame($expected, $service->isExpired($user));
    }

    public function testIsExpiredWithNullMaxAgeReturnsFalse(): void
    {
        $config = new ModuleConfig(maxPasswordAge: null);
        $service = new ExpireService($config);
        $user = $this->createUser();

        self::assertFalse($service->isExpired($user));
    }

    public function testIsExpiredWithPasswordAge9999WhenNeverChanged(): void
    {
        $config = new ModuleConfig(maxPasswordAge: 90);
        $service = new ExpireService($config);
        $user = $this->createUser();
        $user->setPasswordChangedAt(null);

        self::assertTrue($service->isExpired($user));
    }

    public function testRun(): void
    {
        $config = new ModuleConfig();
        $service = new ExpireService($config);
        $user = $this->createUser();
        $user->setPasswordChangedAt(time() - 100 * 86400);
        $user->setUsername('expire_user');
        $user->setEmail('expire@example.com');
        $user->setPasswordHash('hash');
        $user->setAuthKey('key');
        $user->setCreatedAt(time());
        $user->setUpdatedAt(time());
        $user->save();

        self::assertTrue($service->run($user));
        self::assertSame(0, $user->getPasswordChangedAt());

        $reloaded = User::findById((int) $user->getId());
        self::assertNotNull($reloaded);
        self::assertSame(0, $reloaded->getPasswordChangedAt());
    }
    private function createUser(): User
    {
        return new User();
    }
}
