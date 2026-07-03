<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Service\Auth;

use YiiRocks\Voyti\Entity\UserSocialAccount;
use YiiRocks\Voyti\Repository\UserSocialAccountRepository;
use YiiRocks\Voyti\Service\Auth\UserSocialAccountConnectService;
use YiiRocks\Voyti\tests\TestCase;
use Yiisoft\Db\Connection\ConnectionProvider;

final class UserSocialAccountConnectServiceTest extends TestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        ConnectionProvider::set($this->getDb());
        $this->getDb()->createCommand('CREATE TABLE {{%user_social_account}} (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            provider VARCHAR(255) NOT NULL,
            client_id VARCHAR(255) NOT NULL,
            user_id INTEGER,
            username VARCHAR(255),
            email VARCHAR(255),
            code VARCHAR(255),
            data TEXT,
            created_at INTEGER NOT NULL
        )')->execute();
    }

    #[\Override]
    protected function tearDown(): void
    {
        if ($this->hasSqliteConnection()) {
            $this->getDb()->createCommand('DROP TABLE IF EXISTS {{%user_social_account}}')->execute();
            ConnectionProvider::clear();
        }

        parent::tearDown();
    }

    public function testRunClearsUsernameEmailAndCodeOnConnect(): void
    {
        $existing = new UserSocialAccount();
        $existing->setProvider('github');
        $existing->setClientId('client-4');
        $existing->setUserId(null);
        $existing->setUsername('octocat');
        $existing->setEmail('octocat@example.com');
        $existing->setCode('secret-code');
        $existing->setCreatedAt(time());
        $existing->save();

        $service = $this->createService();

        $result = $service->run('github', 'client-4', ['id' => 'client-4'], 11);

        self::assertTrue($result->isSuccess());

        $reloaded = UserSocialAccount::query()->where(['provider' => 'github', 'client_id' => 'client-4'])->one();
        self::assertInstanceOf(UserSocialAccount::class, $reloaded);
        self::assertSame(11, $reloaded->getUserId());
        self::assertNull($reloaded->getUsername());
        self::assertNull($reloaded->getEmail());
        self::assertNull($reloaded->getCode());
    }

    public function testRunCreatesNewAccountWithEncodedDataAndCreationTimestamp(): void
    {
        $service = $this->createService();

        $before = time();
        $result = $service->run('github', 'client-3', ['id' => 'client-3', 'name' => 'Octo Cat'], 7);
        $after = time();

        self::assertTrue($result->isSuccess());

        $reloaded = UserSocialAccount::query()->where(['provider' => 'github', 'client_id' => 'client-3'])->one();
        self::assertInstanceOf(UserSocialAccount::class, $reloaded);
        self::assertSame(7, $reloaded->getUserId());
        self::assertSame(
            json_encode(['id' => 'client-3', 'name' => 'Octo Cat'], JSON_THROW_ON_ERROR),
            $reloaded->getData(),
        );
        self::assertGreaterThanOrEqual($before, $reloaded->getCreatedAt());
        self::assertLessThanOrEqual($after, $reloaded->getCreatedAt());
    }

    public function testRunFailsWhenAccountAlreadyConnectedToAnotherUser(): void
    {
        $existing = new UserSocialAccount();
        $existing->setProvider('github');
        $existing->setClientId('client-1');
        $existing->setUserId(999);
        $existing->setCreatedAt(time());
        $existing->save();

        $service = $this->createService();

        $result = $service->run('github', 'client-1', ['id' => 'client-1'], 1);

        self::assertFalse($result->isSuccess());
        self::assertSame('This account has already been connected to another user', $result->getMessage());

        $reloaded = UserSocialAccount::query()->where(['provider' => 'github', 'client_id' => 'client-1'])->one();
        self::assertInstanceOf(UserSocialAccount::class, $reloaded);
        self::assertSame(999, $reloaded->getUserId());
    }

    private function createService(): UserSocialAccountConnectService
    {
        return new UserSocialAccountConnectService(new UserSocialAccountRepository());
    }
}
