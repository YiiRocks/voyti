<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Adapter;

use YiiRocks\Voyti\Adapter\IdentityAdapter;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\tests\Support\DatabaseTestCase;
use YiiRocks\Voyti\tests\Support\UserFactoryTrait;

final class IdentityAdapterTest extends DatabaseTestCase
{
    use UserFactoryTrait;

    public function testFindIdentityDelegatesToUserRepository(): void
    {
        $user = $this->createUser();
        $adapter = $this->createAdapter();

        $found = $adapter->findIdentity((string) $user->getId());

        self::assertInstanceOf(User::class, $found);
        self::assertSame($user->getId(), $found->getId());
    }

    public function testFindIdentityReturnsNullForUnknownId(): void
    {
        $adapter = $this->createAdapter();

        self::assertNull($adapter->findIdentity('999999'));
    }

    private function createAdapter(): IdentityAdapter
    {
        return new IdentityAdapter();
    }
}
