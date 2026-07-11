<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Service\Auth;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\Entity\UserSocialAccount;
use YiiRocks\Voyti\Service\Auth\PendingSocialAccountService;
use YiiRocks\Voyti\tests\Support\DatabaseSetupTrait;
use YiiRocks\Voyti\tests\Support\FakeSession;

final class PendingSocialAccountServiceTest extends TestCase
{
    use DatabaseSetupTrait;
    private PendingSocialAccountService $service;

    private FakeSession $session;

    protected function setUp(): void
    {
        $this->setUpDatabase();
        $this->session = new FakeSession();
        $this->session->open();
        $this->service = new PendingSocialAccountService($this->session);
    }

    protected function tearDown(): void
    {
        $this->tearDownDatabase();
    }

    public function testClearRemovesSessionKey(): void
    {
        $this->session->set('social_network_account_code', 'some_code');
        self::assertTrue($this->session->has('social_network_account_code'));

        $this->service->clear();
        self::assertFalse($this->session->has('social_network_account_code'));
    }

    public function testConnectWithNoPendingAccountReturnsSuccess(): void
    {
        $user = new User();
        $user->setUsername('test');
        $user->setEmail('test@example.com');
        $user->setPasswordHash('hash');
        $user->setAuthKey('key');
        $user->setCreatedAt(time());
        $user->setUpdatedAt(time());
        $user->save();

        $result = $this->service->connect($user);
        self::assertTrue($result->isSuccess());
    }

    public function testConnectWithPendingAccountConnectsAndClears(): void
    {
        $user = new User();
        $user->setUsername('test');
        $user->setEmail('test@example.com');
        $user->setPasswordHash('hash');
        $user->setAuthKey('key');
        $user->setCreatedAt(time());
        $user->setUpdatedAt(time());
        $user->save();

        $account = new UserSocialAccount();
        $account->setProvider('github');
        $account->setClientId('123');
        $account->setCode('pending_code');
        $account->setData('{}');
        $account->setCreatedAt(time());
        $account->save();

        $this->session->set('social_network_account_code', 'pending_code');

        $result = $this->service->connect($user);
        self::assertTrue($result->isSuccess());

        $loaded = UserSocialAccount::findByCode('pending_code');
        self::assertNull($loaded);

        self::assertFalse($this->session->has('social_network_account_code'));
    }

    public function testGetPendingAccountWithConnectedAccountClearsSession(): void
    {
        $user = new User();
        $user->setUsername('test');
        $user->setEmail('test@example.com');
        $user->setPasswordHash('hash');
        $user->setAuthKey('key');
        $user->setCreatedAt(time());
        $user->setUpdatedAt(time());
        $user->save();

        $account = new UserSocialAccount();
        $account->setProvider('github');
        $account->setClientId('106');
        $account->setCode('connected_get_code');
        $account->setData('{}');
        $account->setUserId((int) $user->getId());
        $account->setCreatedAt(time());
        $account->save();

        $this->session->set('social_network_account_code', 'connected_get_code');

        $result = $this->service->getPendingAccount();
        self::assertNull($result);
        self::assertFalse($this->session->has('social_network_account_code'));
    }

    public function testGetPendingAccountWithConnectedAccountReturnsNull(): void
    {
        $user = new User();
        $user->setUsername('test');
        $user->setEmail('test@example.com');
        $user->setPasswordHash('hash');
        $user->setAuthKey('key');
        $user->setCreatedAt(time());
        $user->setUpdatedAt(time());
        $user->save();

        $account = new UserSocialAccount();
        $account->setProvider('github');
        $account->setClientId('456');
        $account->setCode('connected_code');
        $account->setData('{}');
        $account->setUserId((int) $user->getId());
        $account->setCreatedAt(time());
        $account->save();

        $this->session->set('social_network_account_code', 'connected_code');

        $result = $this->service->getPendingAccount();
        self::assertNull($result);
        self::assertFalse($this->session->has('social_network_account_code'));
    }

    public function testGetPendingAccountWithEmptyStringSessionCodeReturnsNull(): void
    {
        $this->session->set('social_network_account_code', '');

        $result = $this->service->getPendingAccount();
        self::assertNull($result);
    }

    public function testGetPendingAccountWithNoCodeReturnsNull(): void
    {
        $account = $this->service->getPendingAccount();
        self::assertNull($account);
    }

    public function testGetPendingAccountWithNonStringSessionCodeReturnsNull(): void
    {
        $this->session->set('social_network_account_code', 5);

        $result = $this->service->getPendingAccount();
        self::assertNull($result);
    }

    public function testGetPendingAccountWithValidAccountReturnsIt(): void
    {
        $account = new UserSocialAccount();
        $account->setProvider('github');
        $account->setClientId('789');
        $account->setCode('valid_code');
        $account->setData('{}');
        $account->setCreatedAt(time());
        $account->save();

        $this->session->set('social_network_account_code', 'valid_code');

        $result = $this->service->getPendingAccount();
        self::assertNotNull($result);
        self::assertSame('valid_code', $result->getCode());
    }

    public function testRememberWithEmptyStringCodeDoesNotStore(): void
    {
        $account = new UserSocialAccount();
        $account->setProvider('github');
        $account->setClientId('103');
        $account->setCode('');
        $account->setData('{}');
        $account->setCreatedAt(time());

        $this->service->remember($account);
        self::assertFalse($this->session->has('social_network_account_code'));
    }

    public function testRememberWithNonNullNonEmptyCodeStoresInSession(): void
    {
        $account = new UserSocialAccount();
        $account->setProvider('github');
        $account->setClientId('101');
        $account->setCode('remember_code');
        $account->setData('{}');
        $account->setCreatedAt(time());

        $this->service->remember($account);
        self::assertSame('remember_code', $this->session->get('social_network_account_code'));
    }

    public function testRememberWithNullCodeDoesNotStore(): void
    {
        $account = new UserSocialAccount();
        $account->setProvider('github');
        $account->setClientId('102');
        $account->setCode(null);
        $account->setData('{}');
        $account->setCreatedAt(time());

        $this->service->remember($account);
        self::assertFalse($this->session->has('social_network_account_code'));
    }

    public function testUseCodeWithConnectedAccountClearsSessionOnFailure(): void
    {
        $user = new User();
        $user->setUsername('test');
        $user->setEmail('test@example.com');
        $user->setPasswordHash('hash');
        $user->setAuthKey('key');
        $user->setCreatedAt(time());
        $user->setUpdatedAt(time());
        $user->save();

        $account = new UserSocialAccount();
        $account->setProvider('github');
        $account->setClientId('107');
        $account->setCode('connected_use_clear');
        $account->setData('{}');
        $account->setUserId((int) $user->getId());
        $account->setCreatedAt(time());
        $account->save();

        $this->session->set('social_network_account_code', 'connected_use_clear');

        $result = $this->service->useCode('connected_use_clear');
        self::assertNull($result);
        self::assertFalse($this->session->has('social_network_account_code'));
    }

    public function testUseCodeWithConnectedAccountReturnsNull(): void
    {
        $user = new User();
        $user->setUsername('test');
        $user->setEmail('test@example.com');
        $user->setPasswordHash('hash');
        $user->setAuthKey('key');
        $user->setCreatedAt(time());
        $user->setUpdatedAt(time());
        $user->save();

        $account = new UserSocialAccount();
        $account->setProvider('github');
        $account->setClientId('105');
        $account->setCode('connected_use_code');
        $account->setData('{}');
        $account->setUserId((int) $user->getId());
        $account->setCreatedAt(time());
        $account->save();

        $result = $this->service->useCode('connected_use_code');
        self::assertNull($result);
        self::assertFalse($this->session->has('social_network_account_code'));
    }

    public function testUseCodeWithExistingUnconnectedAccountStoresInSession(): void
    {
        $account = new UserSocialAccount();
        $account->setProvider('github');
        $account->setClientId('104');
        $account->setCode('use_code');
        $account->setData('{}');
        $account->setCreatedAt(time());
        $account->save();

        $result = $this->service->useCode('use_code');
        self::assertNotNull($result);
        self::assertSame('use_code', $result->getCode());
        self::assertSame('use_code', $this->session->get('social_network_account_code'));
    }

    public function testUseCodeWithNonExistentCodeReturnsNull(): void
    {
        $result = $this->service->useCode('nonexistent');
        self::assertNull($result);
        self::assertFalse($this->session->has('social_network_account_code'));
    }
}
