<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Entity;

use YiiRocks\Voyti\Entity\UserSocialAccount;
use YiiRocks\Voyti\tests\TestCase;
use Yiisoft\Db\Connection\ConnectionProvider;

final class UserSocialAccountTest extends TestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        ConnectionProvider::set($this->getDb());
        $db = $this->getDb();
        $db->createCommand('CREATE TABLE {{%user_social_account}} (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            userId INTEGER,
            provider VARCHAR(255) NOT NULL,
            clientId VARCHAR(255) NOT NULL,
            data TEXT,
            code VARCHAR(32),
            email VARCHAR(255),
            username VARCHAR(255),
            createdAt INTEGER NOT NULL
        )')->execute();
    }

    #[\Override]
    protected function tearDown(): void
    {
        $db = $this->getDb();
        $db->createCommand('DROP TABLE IF EXISTS {{%user_social_account}}')->execute();
        ConnectionProvider::clear();
        parent::tearDown();
    }

    public function testCode(): void
    {
        $account = new UserSocialAccount();
        $this->assertNull($account->getCode());

        $account->setCode('oauth_code_xyz');
        /** @psalm-suppress DocblockTypeContradiction */
        $this->assertSame('oauth_code_xyz', $account->getCode());
    }

    public function testCreateAndFind(): void
    {
        $account = new UserSocialAccount();
        $account->setUserId(1);
        $account->setProvider('github');
        $account->setClientId('gh_client_123');
        $account->setEmail('githubuser@example.com');
        $account->setUsername('githubuser');
        $account->setCreatedAt(time());
        $account->save();

        $found = UserSocialAccount::query()->where(['provider' => 'github', 'clientId' => 'gh_client_123'])->one();
        $this->assertInstanceOf(UserSocialAccount::class, $found);
        $this->assertSame(1, $found->getUserId());
        $this->assertSame('github', $found->getProvider());
        $this->assertSame('gh_client_123', $found->getClientId());
        $this->assertSame('githubuser@example.com', $found->getEmail());
        $this->assertSame('githubuser', $found->getUsername());
    }

    public function testDecodedData(): void
    {
        $account = new UserSocialAccount();
        $this->assertNull($account->getData());
        $this->assertNull($account->getDecodedData());

        $data = ['name' => 'John', 'avatar' => 'https://example.com/avatar.jpg'];
        /** @var string $encoded */
        $encoded = json_encode($data);
        $account->setData($encoded);
        $this->assertSame($encoded, $account->getData());
        $this->assertSame($data, $account->getDecodedData());
    }

    public function testDeleteSocialAccount(): void
    {
        $account = new UserSocialAccount();
        $account->setUserId(4);
        $account->setProvider('delete_test');
        $account->setClientId('del_client');
        $account->setCreatedAt(time());
        $account->save();

        $id = $account->getId();
        $account->delete();

        $found = UserSocialAccount::query()->where(['id' => $id])->one();
        $this->assertNull($found);
    }

    public function testIsConnected(): void
    {
        $account = new UserSocialAccount();
        $this->assertFalse($account->isConnected());

        $account->setUserId(2);
        $this->assertTrue($account->isConnected());
    }

    public function testNullUserId(): void
    {
        $account = new UserSocialAccount();
        $this->assertNull($account->getUserId());

        $account->setUserId(null);
        $this->assertNull($account->getUserId());
    }

    public function testUpdateSocialAccount(): void
    {
        $account = new UserSocialAccount();
        $account->setUserId(3);
        $account->setProvider('twitter');
        $account->setClientId('tw_client');
        $account->setUsername('oldhandle');
        $account->setCreatedAt(time());
        $account->save();

        $account->setUsername('newhandle');
        $account->save();

        $found = UserSocialAccount::query()->where(['provider' => 'twitter'])->one();
        $this->assertInstanceOf(UserSocialAccount::class, $found);
        $this->assertSame('newhandle', $found->getUsername());
    }
}
