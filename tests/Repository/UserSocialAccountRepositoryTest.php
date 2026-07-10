<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Repository;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Entity\UserSocialAccount;
use YiiRocks\Voyti\Repository\UserSocialAccountRepository;
use YiiRocks\Voyti\tests\Support\DatabaseSetupTrait;

final class UserSocialAccountRepositoryTest extends TestCase
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

    public function testFindByCodeReturnsMatch(): void
    {
        $repository = new UserSocialAccountRepository();
        $this->createAccount('github', 'client-1', 'code-a');
        $this->createAccount('github', 'client-2', 'code-b');

        $account = $repository->findByCode('code-a');

        self::assertNotNull($account);
        self::assertSame('client-1', $account->getClientId());
    }

    public function testFindByProviderAndClientIdReturnsMatch(): void
    {
        $repository = new UserSocialAccountRepository();
        $this->createAccount('github', 'client-1', 'code-a');
        $this->createAccount('gitlab', 'client-1', 'code-b');

        $account = $repository->findByProviderAndClientId('github', 'client-1');

        self::assertNotNull($account);
        self::assertSame('code-a', $account->getCode());
    }

    public function testFindByUserIdReturnsMatches(): void
    {
        $repository = new UserSocialAccountRepository();
        $account = $this->createAccount('github', 'client-1', 'code-a');
        $account->setUserId(1);
        $account->save();
        $this->createAccount('gitlab', 'client-2', 'code-b');

        $accounts = $repository->findByUserId(1);

        self::assertCount(1, $accounts);
        self::assertSame('github', $accounts[0]->getProvider());
    }

    public function testSaveStoresRecord(): void
    {
        $repository = new UserSocialAccountRepository();
        $account = new UserSocialAccount();
        $account->setProvider('github');
        $account->setClientId('client-1');
        $account->setCode('code-a');
        $account->setCreatedAt(time());

        $repository->save($account);

        self::assertNotNull($account->getId());
    }

    private function createAccount(string $provider, string $clientId, string $code): UserSocialAccount
    {
        $account = new UserSocialAccount();
        $account->setProvider($provider);
        $account->setClientId($clientId);
        $account->setCode($code);
        $account->setCreatedAt(time());
        $account->save();

        return $account;
    }
}
