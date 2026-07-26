<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Service\Password;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Service\Password\ExpireService;
use YiiRocks\Voyti\tests\Support\DatabaseSetupTrait;
use YiiRocks\Voyti\tests\Support\ModuleConfigFactory;

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
    public static function isExpiredAgeProvider(): iterable
    {
        yield 'age above max' => [100, true];
        yield 'age at max' => [90, true];
        yield 'age below max' => [50, false];
    }

    #[DataProvider('isExpiredAgeProvider')]
    public function testIsExpiredWithAge(int $ageDays, bool $expected): void
    {
        $config = ModuleConfigFactory::create(maxPasswordAge: 90);
        $service = new ExpireService($config);
        $user = $this->createUser();
        $user->setPasswordChangedAt(time() - $ageDays * 86400);

        self::assertSame($expected, $service->isExpired($user));
    }

    public function testIsExpiredWithMaxPasswordAgeZeroIgnoresExpiredUser(): void
    {
        $config = ModuleConfigFactory::create(maxPasswordAge: 0);
        $service = new ExpireService($config);
        $user = $this->createUser();
        $user->setPasswordChangedAt(time() - 100 * 86400);

        self::assertFalse($service->isExpired($user));
    }

    public function testIsExpiredWithPasswordAge9999WhenNeverChanged(): void
    {
        $config = ModuleConfigFactory::create(maxPasswordAge: 90);
        $service = new ExpireService($config);
        $user = $this->createUser();
        $user->setPasswordChangedAt(null);

        self::assertTrue($service->isExpired($user));
    }

    public function testRun(): void
    {
        $config = ModuleConfigFactory::create();
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
