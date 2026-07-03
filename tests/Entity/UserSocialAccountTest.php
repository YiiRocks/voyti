<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Entity;

use YiiRocks\Voyti\Entity\User;
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
            user_id INTEGER,
            provider VARCHAR(255) NOT NULL,
            client_id VARCHAR(255) NOT NULL,
            data TEXT,
            code VARCHAR(32),
            email VARCHAR(255),
            username VARCHAR(255),
            created_at INTEGER NOT NULL
        )')->execute();
        $db->createCommand('CREATE TABLE {{%user}} (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL,
            password_hash VARCHAR(60) NOT NULL,
            auth_key VARCHAR(32) NOT NULL,
            unconfirmed_email VARCHAR(255),
            registration_ip VARCHAR(45),
            flags INTEGER NOT NULL DEFAULT 0,
            confirmed_at INTEGER,
            blocked_at INTEGER,
            updated_at INTEGER NOT NULL,
            created_at INTEGER NOT NULL,
            last_login_at INTEGER,
            auth_tf_key VARCHAR(64),
            auth_tf_enabled INTEGER DEFAULT 0,
            password_changed_at INTEGER,
            last_login_ip VARCHAR(45),
            gdpr_deleted INTEGER DEFAULT 0,
            gdpr_consent INTEGER DEFAULT 0,
            gdpr_consent_date INTEGER,
            auth_tf_type VARCHAR(20)
        )')->execute();
    }

    #[\Override]
    protected function tearDown(): void
    {
        if ($this->hasSqliteConnection()) {
            $this->getDb()->createCommand('DROP TABLE IF EXISTS {{%user_social_account}}')->execute();
            $this->getDb()->createCommand('DROP TABLE IF EXISTS {{%user}}')->execute();
            ConnectionProvider::clear();
        }
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

    public function testConnectClearsUsernameEmailAndCode(): void
    {
        $user = new User();
        $user->setUsername('connectuser');
        $user->setEmail('connectuser@example.com');
        $user->setPasswordHash('hash');
        $user->setAuthKey('authkey');
        $user->setCreatedAt(time());
        $user->setUpdatedAt(time());
        $user->save();

        $account = new UserSocialAccount();
        $account->setProvider('github');
        $account->setClientId('gh_connect_client');
        $account->setUsername('leftover_username');
        $account->setEmail('leftover@example.com');
        $account->setCode('leftover_code');
        $account->setCreatedAt(time());

        $result = $account->connect($user);

        $this->assertTrue($result);
        $this->assertSame((int) $user->getId(), $account->getUserId());
        $this->assertNull($account->getUsername());
        $this->assertNull($account->getEmail());
        $this->assertNull($account->getCode());

        $found = UserSocialAccount::query()->where(['client_id' => 'gh_connect_client'])->one();
        $this->assertInstanceOf(UserSocialAccount::class, $found);
        $this->assertSame((int) $user->getId(), $found->getUserId());
        $this->assertNull($found->getUsername());
        $this->assertNull($found->getEmail());
    }

    public function testConnectWithUnsavedUserSetsUserIdToZero(): void
    {
        $user = new User();
        $this->assertNull($user->getId());

        $account = new UserSocialAccount();
        $account->setProvider('github');
        $account->setClientId('gh_connect_no_id');
        $account->setCreatedAt(time());

        $account->connect($user);

        $this->assertSame(0, $account->getUserId());
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

        $found = UserSocialAccount::query()->where(['provider' => 'github', 'client_id' => 'gh_client_123'])->one();
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
