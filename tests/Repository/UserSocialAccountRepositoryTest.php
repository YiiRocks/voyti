<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Repository;

use YiiRocks\Voyti\Entity\UserSocialAccount;
use YiiRocks\Voyti\Repository\UserSocialAccountRepository;
use YiiRocks\Voyti\tests\TestCase;
use Yiisoft\Db\Connection\ConnectionProvider;

final class UserSocialAccountRepositoryTest extends TestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        ConnectionProvider::set($this->getDb());
        $db = $this->getDb();
        $db->createCommand('CREATE TABLE {{%user_social_account}} (
            id INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            provider VARCHAR(255) NOT NULL,
            client_id VARCHAR(255) NOT NULL,
            code VARCHAR(255),
            username VARCHAR(255),
            email VARCHAR(255),
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

    public function testFindByCodeReturnsAccountMatchingGivenCode(): void
    {
        $first = $this->createAccount('github', 'client-1', 'code-1');
        $first->save();

        $second = $this->createAccount('github', 'client-2', 'code-2');
        $second->save();

        $repository = new UserSocialAccountRepository();

        $found = $repository->findByCode('code-2');

        self::assertSame('code-2', $found->getCode());
        self::assertSame('client-2', $found->getClientId());
    }

    public function testFindByCodeReturnsNullWhenCodeDoesNotMatch(): void
    {
        $account = $this->createAccount('github', 'client-1', 'code-1');
        $account->save();

        $repository = new UserSocialAccountRepository();

        self::assertNull($repository->findByCode('code-2'));
    }

    public function testFindByProviderAndClientIdReturnsAccountMatchingBothProviderAndClientId(): void
    {
        $github = $this->createAccount('github', 'shared-client-id', 'code-1');
        $github->save();

        $keycloak = $this->createAccount('keycloak', 'shared-client-id', 'code-2');
        $keycloak->save();

        $repository = new UserSocialAccountRepository();

        $found = $repository->findByProviderAndClientId('keycloak', 'shared-client-id');

        self::assertSame('keycloak', $found->getProvider());
        self::assertSame('code-2', $found->getCode());
    }

    public function testFindByProviderAndClientIdReturnsNullWhenProviderDoesNotMatch(): void
    {
        $account = $this->createAccount('github', 'shared-client-id', 'code-1');
        $account->save();

        $repository = new UserSocialAccountRepository();

        self::assertNull($repository->findByProviderAndClientId('keycloak', 'shared-client-id'));
    }

    public function testFindByUserIdReturnsOnlyAccountsMatchingGivenUserId(): void
    {
        $ownAccount = $this->createAccount('github', 'client-1', 'code-1');
        $ownAccount->setUserId(1);
        $ownAccount->save();

        $otherAccount = $this->createAccount('keycloak', 'client-2', 'code-2');
        $otherAccount->setUserId(2);
        $otherAccount->save();

        $repository = new UserSocialAccountRepository();

        $found = $repository->findByUserId(1);

        self::assertCount(1, $found);
        self::assertSame('client-1', $found[0]->getClientId());
    }

    private function createAccount(string $provider, string $clientId, string $code): UserSocialAccount
    {
        $account = new UserSocialAccount();
        $account->setProvider($provider);
        $account->setClientId($clientId);
        $account->setCode($code);
        $account->setCreatedAt(time());
        return $account;
    }
}
