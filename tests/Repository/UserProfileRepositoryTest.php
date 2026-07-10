<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Repository;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Entity\UserProfile;
use YiiRocks\Voyti\Repository\UserProfileRepository;
use YiiRocks\Voyti\tests\Support\DatabaseSetupTrait;

final class UserProfileRepositoryTest extends TestCase
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

    public function testFindByUserIdReturnsMatchingProfileAmongMultiple(): void
    {
        $repository = new UserProfileRepository();

        $profile1 = new UserProfile();
        $profile1->setUserId(1);
        $profile1->setName('Alice');
        $profile1->save();

        $profile2 = new UserProfile();
        $profile2->setUserId(2);
        $profile2->setName('Bob');
        $profile2->save();

        $found = $repository->findByUserId(2);

        self::assertNotNull($found);
        self::assertSame('Bob', $found->getName());
    }

    public function testFindByUserIdReturnsNullWhenNoneExists(): void
    {
        $repository = new UserProfileRepository();

        self::assertNull($repository->findByUserId(1));
    }
}
