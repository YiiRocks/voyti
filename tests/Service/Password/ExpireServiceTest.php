<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Service\Password;

use PHPUnit\Framework\Attributes\DataProvider;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Service\Password\ExpireService;
use YiiRocks\Voyti\tests\Support\DatabaseTestCase;
use YiiRocks\Voyti\tests\Support\VoytiConfigFactory;

final class ExpireServiceTest extends DatabaseTestCase
{
    public static function isExpiredAgeProvider(): iterable
    {
        yield 'age above max' => [100, true];
        yield 'age at max' => [90, true];
        yield 'age below max' => [50, false];
    }

    #[DataProvider('isExpiredAgeProvider')]
    public function testIsExpiredWithAge(int $ageDays, bool $expected): void
    {
        $config = VoytiConfigFactory::create(maxPasswordAge: 90);
        $service = new ExpireService($config);
        $user = $this->createUser();
        $user->setPasswordChangedAt(time() - $ageDays * 86400);

        self::assertSame($expected, $service->isExpired($user));
    }

    public function testRun(): void
    {
        $config = VoytiConfigFactory::create();
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
