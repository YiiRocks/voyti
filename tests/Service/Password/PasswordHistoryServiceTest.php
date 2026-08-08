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

    public function testRecordDoesNothingWhenDisabled(): void
    {
        $config = VoytiConfigFactory::create();
        $passwordHasher = TestPasswordHasherFactory::create();
        $user = $this->createUser(
            username: 'historyuser',
            email: 'history@example.com',
            passwordHash: $passwordHasher->hash('currentpass'),
        );
        $service = new PasswordHistoryService($passwordHasher, $config);

        $service->record($user);

        self::assertCount(0, UserPasswordHistory::findByUserId($user->getIdOrZero()));
    }

    public function testRecordPrunesBeyondLimit(): void
    {
        $config = VoytiConfigFactory::create(maxPasswordAge: 90, passwordHistoryLimit: 2);
        $passwordHasher = TestPasswordHasherFactory::create();
        $user = $this->createUser(
            username: 'historyuser',
            email: 'history@example.com',
            passwordHash: $passwordHasher->hash('pass0'),
        );
        $service = new PasswordHistoryService($passwordHasher, $config);

        $service->record($user);

        $user->setPasswordHash($passwordHasher->hash('pass1'));
        $user->save();
        $service->record($user);

        $user->setPasswordHash($passwordHasher->hash('pass2'));
        $user->save();
        $service->record($user);

        self::assertCount(2, UserPasswordHistory::findByUserId($user->getIdOrZero()));
    }

    public function testRecordPruneScopesDeletionToOwningUser(): void
    {
        $config = VoytiConfigFactory::create(maxPasswordAge: 90, passwordHistoryLimit: 2);
        $passwordHasher = TestPasswordHasherFactory::create();
        $user1 = $this->createUser(username: 'pruneuser1', email: 'prune1@example.com');
        $user2 = $this->createUser(username: 'pruneuser2', email: 'prune2@example.com');
        $service = new PasswordHistoryService($passwordHasher, $config);

        // user2's only history entry shares its hash with what will become user1's oldest entry,
        // so a prune that forgot to scope by user_id would delete this row too.
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

    public function testRecordStoresCurrentHash(): void
    {
        $config = VoytiConfigFactory::create(maxPasswordAge: 90);
        $passwordHasher = TestPasswordHasherFactory::create();
        $user = $this->createUser(
            username: 'historyuser',
            email: 'history@example.com',
            passwordHash: $passwordHasher->hash('currentpass'),
        );
        $service = new PasswordHistoryService($passwordHasher, $config);

        $beforeRecord = time();
        $service->record($user);

        $history = UserPasswordHistory::findByUserId($user->getIdOrZero());
        self::assertCount(1, $history);
        self::assertTrue($passwordHasher->validate('currentpass', $history[0]->getPasswordHash()));
        self::assertGreaterThanOrEqual($beforeRecord, $history[0]->getCreatedAt());
    }

    public function testWasUsedRecentlyChecksHistoryEntries(): void
    {
        $config = VoytiConfigFactory::create(maxPasswordAge: 90);
        $passwordHasher = TestPasswordHasherFactory::create();
        $user = $this->createUser(
            username: 'historyuser',
            email: 'history@example.com',
            passwordHash: $passwordHasher->hash('originalpass'),
        );
        $service = new PasswordHistoryService($passwordHasher, $config);
        $service->record($user);

        $user->setPasswordHash($passwordHasher->hash('secondpass'));
        $user->save();
        $service->record($user);

        self::assertTrue($service->wasUsedRecently($user, 'originalpass'));
        self::assertTrue($service->wasUsedRecently($user, 'secondpass'));
        self::assertFalse($service->wasUsedRecently($user, 'neverusedpass'));
    }

    public function testWasUsedRecentlyIsFalseWhenDisabled(): void
    {
        $config = VoytiConfigFactory::create();
        $passwordHasher = TestPasswordHasherFactory::create();
        $user = $this->createUser(
            username: 'historyuser',
            email: 'history@example.com',
            passwordHash: $passwordHasher->hash('currentpass'),
        );
        $service = new PasswordHistoryService($passwordHasher, $config);

        self::assertFalse($service->wasUsedRecently($user, 'currentpass'));
    }

    public function testWasUsedRecentlyMatchesCurrentHash(): void
    {
        $config = VoytiConfigFactory::create(maxPasswordAge: 90);
        $passwordHasher = TestPasswordHasherFactory::create();
        $user = $this->createUser(
            username: 'historyuser',
            email: 'history@example.com',
            passwordHash: $passwordHasher->hash('currentpass'),
        );
        $service = new PasswordHistoryService($passwordHasher, $config);

        self::assertTrue($service->wasUsedRecently($user, 'currentpass'));
    }
}
