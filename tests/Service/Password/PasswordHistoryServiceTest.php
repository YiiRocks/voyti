<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Service\Password;

use YiiRocks\Voyti\Model\UserPasswordHistory;
use YiiRocks\Voyti\Service\Password\PasswordHistoryService;
use YiiRocks\Voyti\tests\Support\DatabaseTestCase;
use YiiRocks\Voyti\tests\Support\TestPasswordHasherFactory;
use YiiRocks\Voyti\tests\Support\UserFactoryTrait;
use YiiRocks\Voyti\tests\Support\VoytiConfigFactory;

final class PasswordHistoryServiceTest extends DatabaseTestCase
{
    use UserFactoryTrait;

    public function testRecord(): void
    {
        // Disabled: does nothing
        $config = VoytiConfigFactory::create();
        $passwordHasher = TestPasswordHasherFactory::create();
        $disabledUser = $this->createUser(
            username: 'disabledhistory',
            email: 'disabledhistory@example.com',
            passwordHash: $passwordHasher->hash('currentpass'),
        );
        $service = new PasswordHistoryService($passwordHasher, $config);
        $service->record($disabledUser);
        self::assertCount(0, UserPasswordHistory::findByUserId($disabledUser->getIdOrZero()));

        // Stores current hash
        $config = VoytiConfigFactory::create(maxPasswordAge: 90);
        $passwordHasher = TestPasswordHasherFactory::create();
        $storeUser = $this->createUser(
            username: 'storehistory',
            email: 'storehistory@example.com',
            passwordHash: $passwordHasher->hash('currentpass'),
        );
        $service = new PasswordHistoryService($passwordHasher, $config);
        $beforeRecord = time();
        $service->record($storeUser);
        $history = UserPasswordHistory::findByUserId($storeUser->getIdOrZero());
        self::assertCount(1, $history);
        self::assertTrue($passwordHasher->validate('currentpass', $history[0]->getPasswordHash()));
        self::assertGreaterThanOrEqual($beforeRecord, $history[0]->getCreatedAt());

        // Prunes beyond limit
        $config = VoytiConfigFactory::create(maxPasswordAge: 90, passwordHistoryLimit: 2);
        $passwordHasher = TestPasswordHasherFactory::create();
        $pruneUser = $this->createUser(
            username: 'prunehistory',
            email: 'prunehistory@example.com',
            passwordHash: $passwordHasher->hash('pass0'),
        );
        $service = new PasswordHistoryService($passwordHasher, $config);
        $service->record($pruneUser);
        $pruneUser->setPasswordHash($passwordHasher->hash('pass1'));
        $pruneUser->save();
        $service->record($pruneUser);
        $pruneUser->setPasswordHash($passwordHasher->hash('pass2'));
        $pruneUser->save();
        $service->record($pruneUser);
        self::assertCount(2, UserPasswordHistory::findByUserId($pruneUser->getIdOrZero()));

        // Prune scopes deletion to owning user
        $config = VoytiConfigFactory::create(maxPasswordAge: 90, passwordHistoryLimit: 2);
        $passwordHasher = TestPasswordHasherFactory::create();
        $user1 = $this->createUser(username: 'pruneuser1', email: 'prune1@example.com');
        $user2 = $this->createUser(username: 'pruneuser2', email: 'prune2@example.com');
        $service = new PasswordHistoryService($passwordHasher, $config);
        $user2History = new UserPasswordHistory();
        $user2History->setUserId($user2->getIdOrZero());
        $user2History->setPasswordHash('shared-hash');
        $user2History->setCreatedAt(1000);
        $user2History->save();
        $user1OldestHistory = new UserPasswordHistory();
        $user1OldestHistory->setUserId($user1->getIdOrZero());
        $user1OldestHistory->setPasswordHash('shared-hash');
        $user1OldestHistory->setCreatedAt(1000);
        $user1OldestHistory->save();
        $user1MiddleHistory = new UserPasswordHistory();
        $user1MiddleHistory->setUserId($user1->getIdOrZero());
        $user1MiddleHistory->setPasswordHash('user1-hash-2');
        $user1MiddleHistory->setCreatedAt(2000);
        $user1MiddleHistory->save();
        $user1->setPasswordHash('user1-hash-3');
        $user1->save();
        $service->record($user1);
        self::assertCount(2, UserPasswordHistory::findByUserId($user1->getIdOrZero()));
        self::assertCount(1, UserPasswordHistory::findByUserId($user2->getIdOrZero()));
    }

    public function testWasUsedRecently(): void
    {
        // Disabled: returns false
        $config = VoytiConfigFactory::create();
        $passwordHasher = TestPasswordHasherFactory::create();
        $disabledUser = $this->createUser(
            username: 'disabledwasused',
            email: 'disabledwasused@example.com',
            passwordHash: $passwordHasher->hash('currentpass'),
        );
        $service = new PasswordHistoryService($passwordHasher, $config);
        self::assertFalse($service->wasUsedRecently($disabledUser, 'currentpass'));

        // Matches current hash
        $config = VoytiConfigFactory::create(maxPasswordAge: 90);
        $passwordHasher = TestPasswordHasherFactory::create();
        $currentHashUser = $this->createUser(
            username: 'currenthashuser',
            email: 'currenthashuser@example.com',
            passwordHash: $passwordHasher->hash('currentpass'),
        );
        $service = new PasswordHistoryService($passwordHasher, $config);
        self::assertTrue($service->wasUsedRecently($currentHashUser, 'currentpass'));

        // Checks history entries
        $config = VoytiConfigFactory::create(maxPasswordAge: 90);
        $passwordHasher = TestPasswordHasherFactory::create();
        $historyUser = $this->createUser(
            username: 'historyuser',
            email: 'historyuser@example.com',
            passwordHash: $passwordHasher->hash('originalpass'),
        );
        $service = new PasswordHistoryService($passwordHasher, $config);
        $service->record($historyUser);
        $historyUser->setPasswordHash($passwordHasher->hash('secondpass'));
        $historyUser->save();
        $service->record($historyUser);
        self::assertTrue($service->wasUsedRecently($historyUser, 'originalpass'));
        self::assertTrue($service->wasUsedRecently($historyUser, 'secondpass'));
        self::assertFalse($service->wasUsedRecently($historyUser, 'neverusedpass'));
    }
}
