<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Service\Auth;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Model\UserSocialAccount;
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

    /**
     * @return iterable<string, array{int|string}>
     */
    public static function invalidSessionCodeProvider(): iterable
    {
        yield 'empty string' => [''];
        yield 'non-string' => [5];
    }

    /**
     * @return iterable<string, array{null|string, null|string}>
     */
    public static function rememberCodeProvider(): iterable
    {
        yield 'empty string code does not store' => ['', null];
        yield 'non-null non-empty code stores' => ['remember_code', 'remember_code'];
        yield 'null code does not store' => [null, null];
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

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidSessionCodeProvider')]
    public function testGetPendingAccountWithInvalidSessionCodeReturnsNull(int|string $sessionCode): void
    {
        $this->session->set('social_network_account_code', $sessionCode);

        $result = $this->service->getPendingAccount();
        self::assertNull($result);
    }

    public function testGetPendingAccountWithNoCodeReturnsNull(): void
    {
        $account = $this->service->getPendingAccount();
        self::assertNull($account);
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

    #[\PHPUnit\Framework\Attributes\DataProvider('rememberCodeProvider')]
    public function testRememberStoresCodeInSession(?string $code, ?string $expectedStored): void
    {
        $account = new UserSocialAccount();
        $account->setProvider('github');
        $account->setClientId('101');
        $account->setCode($code);
        $account->setData('{}');
        $account->setCreatedAt(time());

        $this->service->remember($account);

        if ($expectedStored === null) {
            self::assertFalse($this->session->has('social_network_account_code'));
        } else {
            self::assertSame($expectedStored, $this->session->get('social_network_account_code'));
        }
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
