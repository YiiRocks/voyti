<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Repository;

use YiiRocks\Voyti\Entity\UserProfile;
use YiiRocks\Voyti\Repository\UserProfileRepository;
use YiiRocks\Voyti\tests\TestCase;
use Yiisoft\Db\Connection\ConnectionProvider;

final class UserProfileRepositoryTest extends TestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        ConnectionProvider::set($this->getDb());
        $db = $this->getDb();
        $db->createCommand('CREATE TABLE {{%user_profile}} (
            user_id INTEGER NOT NULL PRIMARY KEY,
            name VARCHAR(255),
            public_email VARCHAR(255),
            gravatar_email VARCHAR(255),
            location VARCHAR(255),
            website VARCHAR(255),
            bio TEXT,
            timezone VARCHAR(40)
        )')->execute();
    }

    #[\Override]
    protected function tearDown(): void
    {
        if ($this->hasSqliteConnection()) {
            $this->getDb()->createCommand('DROP TABLE IF EXISTS {{%user_profile}}')->execute();
            ConnectionProvider::clear();
        }

        parent::tearDown();
    }

    public function testFindByUserIdReturnsNullWhenNoProfileMatches(): void
    {
        $profile = new UserProfile();
        $profile->setUserId(1);
        $profile->setName('Alice');
        $profile->save();

        $repository = new UserProfileRepository();

        self::assertNull($repository->findByUserId(999));
    }

    public function testFindByUserIdReturnsProfileMatchingGivenUserId(): void
    {
        $first = new UserProfile();
        $first->setUserId(1);
        $first->setName('Alice');
        $first->save();

        $second = new UserProfile();
        $second->setUserId(2);
        $second->setName('Bob');
        $second->save();

        $repository = new UserProfileRepository();

        $found = $repository->findByUserId(2);

        self::assertSame(2, $found->getUserId());
        self::assertSame('Bob', $found->getName());
    }
}
