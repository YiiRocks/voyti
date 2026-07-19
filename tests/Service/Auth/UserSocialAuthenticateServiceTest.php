<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Service\Auth;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Model\UserSocialAccount;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Service\Auth\UserSocialAuthenticateService;
use YiiRocks\Voyti\Service\MailService;
use YiiRocks\Voyti\Service\Password\PasswordHistoryService;
use YiiRocks\Voyti\Service\User\UserCreationHelper;
use YiiRocks\Voyti\tests\Support\DatabaseSetupTrait;
use YiiRocks\Voyti\tests\Support\FakeSession;
use YiiRocks\Voyti\tests\Support\UserFactoryTrait;
use Yiisoft\Security\PasswordHasher;
use Yiisoft\User\CurrentUser;

#[AllowMockObjectsWithoutExpectations]
final class UserSocialAuthenticateServiceTest extends TestCase
{
    use DatabaseSetupTrait;
    use UserFactoryTrait;

    private FakeSession $session;

    protected function setUp(): void
    {
        $this->setUpDatabase();
        $this->session = new FakeSession();
        $this->session->open();
    }

    protected function tearDown(): void
    {
        $this->tearDownDatabase();
    }

    public function testRunAccountWithoutUserIdAndEmptyCodeReturnsFailure(): void
    {
        $this->createPendingAccount('empty_code_client', '');

        $result = $this->createService(new ModuleConfig(enableSocialNetworkRegistration: true))
            ->run('github', 'empty_code_client', ['email' => 'test@example.com']);

        self::assertTrue($result->isFailure());
        self::assertSame('Unable to prepare the social account connection', $result->getMessage());
    }

    public function testRunAccountWithoutUserIdAndNoCodeReturnsFailure(): void
    {
        $this->createPendingAccount('no_code_client', null);

        $result = $this->createService(new ModuleConfig(enableSocialNetworkRegistration: true))
            ->run('github', 'no_code_client', ['email' => 'test@example.com']);

        self::assertTrue($result->isFailure());
        self::assertSame('Unable to prepare the social account connection', $result->getMessage());
    }

    public function testRunAccountWithoutUserIdSetsSessionCode(): void
    {
        $this->createPendingAccount('pending_client', 'pending_code_123');

        $result = $this->createService(new ModuleConfig(enableSocialNetworkRegistration: true))
            ->run('github', 'pending_client', ['email' => 'test@example.com']);

        self::assertTrue($result->isSuccess());
        self::assertSame('pending_code_123', $this->session->get('social_network_account_code'));
    }

    public function testRunAccountWithUserIdUserNotFoundReturnsFailure(): void
    {
        $this->createConnectedAccount('orphan_client', 99999);

        $result = $this->createService(new ModuleConfig(enableSocialNetworkRegistration: true))
            ->run('github', 'orphan_client', ['email' => 'test@example.com']);

        self::assertTrue($result->isFailure());
        self::assertSame('Associated user not found', $result->getMessage());
    }

    public function testRunClearsOauthClientDataOnLogin(): void
    {
        $user = $this->createUser('clear_oauth', 'clear_oauth@example.com');
        $this->createConnectedAccount('clear_oauth_client', (int) $user->getId());
        $this->session->set('oauth_client_data', ['some' => 'data']);

        $this->createService(new ModuleConfig(enableSocialNetworkRegistration: true))
            ->run('github', 'clear_oauth_client', ['email' => 'test@example.com']);

        self::assertFalse($this->session->has('oauth_client_data'));
    }

    public function testRunCreatesNewAccountWhenNotFound(): void
    {
        $currentUser = $this->createMock(CurrentUser::class);
        $currentUser->expects($this->once())->method('login');

        $result = $this->createService(new ModuleConfig(enableSocialNetworkRegistration: true), $currentUser)
            ->run('github', 'new_account', ['username' => 'newuser', 'email' => 'new@example.com']);

        self::assertTrue($result->isSuccess());

        $user = User::findByEmail('new@example.com');
        self::assertNotNull($user);
        self::assertSame('newuser', $user->getUsername());
        self::assertTrue($user->isConfirmed());

        $saved = UserSocialAccount::findByProviderAndClientId('github', 'new_account');
        self::assertNotNull($saved);
        self::assertSame((int) $user->getId(), $saved->getUserId());
        self::assertNull($saved->getCode());
    }

    public function testRunCreatesNewAccountWithDeduplicatedUsernameOnCollision(): void
    {
        $currentUser = $this->createMock(CurrentUser::class);
        $currentUser->expects($this->once())->method('login');

        $this->createUser('dupeuser', 'dupeuser@example.com');
        $this->createUser('dupeuser_2', 'dupeuser2@example.com');

        $result = $this->createService(new ModuleConfig(enableSocialNetworkRegistration: true), $currentUser)
            ->run('github', 'dupe_account', ['username' => 'dupeuser', 'email' => 'new_dupe@example.com']);

        self::assertTrue($result->isSuccess());

        $user = User::findByEmail('new_dupe@example.com');
        self::assertNotNull($user);
        self::assertSame('dupeuser_3', $user->getUsername());
    }

    public function testRunEmptyClientIdWithoutSessionDataReturnsFailure(): void
    {
        $result = $this->createService(new ModuleConfig(enableSocialNetworkRegistration: true))
            ->run('github', '', ['email' => 'test@example.com']);

        self::assertTrue($result->isFailure());
        self::assertSame('Unable to determine social network client ID', $result->getMessage());
    }

    public function testRunEmptyClientIdWithSessionDataUsesSession(): void
    {
        $this->session->set('oauth_client_data', ['user_id' => 'session_user_123']);

        $result = $this->createService(new ModuleConfig(enableSocialNetworkRegistration: true))
            ->run('github', '', ['email' => 'test@example.com']);

        self::assertTrue($result->isSuccess());
    }

    public function testRunNewAccountWithExistingEmailRemainsPendingForPasswordLinking(): void
    {
        $this->createUser('existing', 'existing@example.com');

        $currentUser = $this->createMock(CurrentUser::class);
        $currentUser->expects($this->never())->method('login');

        $result = $this->createService(new ModuleConfig(enableSocialNetworkRegistration: true), $currentUser)
            ->run('github', 'existing_email_client', ['email' => 'existing@example.com', 'username' => 'ext']);

        self::assertTrue($result->isSuccess());

        $saved = UserSocialAccount::findByProviderAndClientId('github', 'existing_email_client');
        self::assertNotNull($saved);
        self::assertNull($saved->getUserId());
        self::assertNotNull($saved->getCode());
        self::assertSame($saved->getCode(), $this->session->get('social_network_account_code'));
    }

    public function testRunNewAccountWithNameAttributeAsFallback(): void
    {
        $result = $this->createService(new ModuleConfig(enableSocialNetworkRegistration: true))
            ->run('github', 'name_fallback_client', ['name' => 'fallback_user']);

        self::assertTrue($result->isSuccess());

        $saved = UserSocialAccount::findByProviderAndClientId('github', 'name_fallback_client');
        self::assertNotNull($saved);
        self::assertSame('fallback_user', $saved->getUsername());
    }

    public function testRunNewAccountWithNoEmailNoUsername(): void
    {
        $result = $this->createService(new ModuleConfig(enableSocialNetworkRegistration: true))
            ->run('github', 'bare_client', ['id' => 'bare_user_123']);

        self::assertTrue($result->isSuccess());

        $saved = UserSocialAccount::findByProviderAndClientId('github', 'bare_client');
        self::assertNotNull($saved);
        self::assertNull($saved->getUsername());
        self::assertNull($saved->getEmail());
        self::assertNotNull($saved->getCode());
    }

    public function testRunNewAccountWithRegistrationDisabledRemainsPending(): void
    {
        $currentUser = $this->createMock(CurrentUser::class);
        $currentUser->expects($this->never())->method('login');

        $config = new ModuleConfig(enableSocialNetworkRegistration: true, enableRegistration: false);
        $result = $this->createService($config, $currentUser)
            ->run('github', 'no_registration_client', ['username' => 'newuser', 'email' => 'blocked_signup@example.com']);

        self::assertTrue($result->isSuccess());
        self::assertNull(User::findByEmail('blocked_signup@example.com'));

        $saved = UserSocialAccount::findByProviderAndClientId('github', 'no_registration_client');
        self::assertNotNull($saved);
        self::assertNull($saved->getUserId());
        self::assertNotNull($saved->getCode());
    }

    public function testRunSocialRegistrationDisabledReturnsFailure(): void
    {
        $result = $this->createService(new ModuleConfig(enableSocialNetworkRegistration: false))
            ->run('github', 'client123', ['email' => 'test@example.com']);

        self::assertTrue($result->isFailure());
        self::assertSame('Social network registration is disabled', $result->getMessage());
    }

    public function testRunWithBlockedUserReturnsFailure(): void
    {
        $user = $this->createUser('blocked', 'blocked@example.com', blockedAt: time());
        $this->createConnectedAccount('blocked_client', (int) $user->getId());

        $result = $this->createService(new ModuleConfig(enableSocialNetworkRegistration: true))
            ->run('github', 'blocked_client', ['email' => 'test@example.com']);

        self::assertTrue($result->isFailure());
        self::assertSame('Your account has been blocked', $result->getMessage());
    }

    public function testRunWithLoggedInUserNoRemoteAddrDefaultsTo127(): void
    {
        $currentUser = $this->createMock(CurrentUser::class);
        $currentUser->method('login');

        $user = $this->createUser('noremote', 'noremote@example.com');
        $this->createConnectedAccount('noremote_client', (int) $user->getId());

        $this->createService(new ModuleConfig(enableSocialNetworkRegistration: true), $currentUser)
            ->run('github', 'noremote_client', []);

        $updated = User::findByEmail('noremote@example.com');
        self::assertSame('127.0.0.1', $updated->getLastLoginIp());
    }

    public function testRunWithLoggedInUserUpdatesLastLoginIp(): void
    {
        $currentUser = $this->createMock(CurrentUser::class);
        $currentUser->method('login');

        $user = $this->createUser('iplogin', 'iplogin@example.com');
        $this->createConnectedAccount('ip_login_client', (int) $user->getId());

        $this->createService(new ModuleConfig(enableSocialNetworkRegistration: true), $currentUser)
            ->run('github', 'ip_login_client', [], ['REMOTE_ADDR' => '192.168.1.50']);

        $updated = User::findByEmail('iplogin@example.com');
        self::assertSame('192.168.1.50', $updated->getLastLoginIp());
    }

    public function testRunWithValidConnectedUserAndDisabledIpLogging(): void
    {
        $currentUser = $this->createMock(CurrentUser::class);
        $currentUser->expects($this->once())->method('login');
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->once())->method('dispatch');

        $user = $this->createUser('active2', 'active2@example.com', lastLoginIp: '1.2.3.4');
        $this->createConnectedAccount('active_client2', (int) $user->getId());

        $config = new ModuleConfig(enableSocialNetworkRegistration: true, disableIpLogging: true);
        $result = $this->createService($config, $currentUser, $eventDispatcher)
            ->run('github', 'active_client2', ['email' => 'test@example.com']);

        self::assertTrue($result->isSuccess());

        $updatedUser = User::findByEmail('active2@example.com');
        self::assertSame('127.0.0.1', $updatedUser->getLastLoginIp());
    }

    public function testRunWithValidConnectedUserLogsIn(): void
    {
        $currentUser = $this->createMock(CurrentUser::class);
        $currentUser->expects($this->once())->method('login');
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->once())->method('dispatch');

        $user = $this->createUser('active', 'active@example.com');
        $this->createConnectedAccount('active_client', (int) $user->getId());

        $result = $this->createService(new ModuleConfig(enableSocialNetworkRegistration: true), $currentUser, $eventDispatcher)
            ->run('github', 'active_client', ['email' => 'test@example.com'], ['REMOTE_ADDR' => '10.0.0.1']);

        self::assertTrue($result->isSuccess());
    }

    private function createConnectedAccount(string $clientId, int $userId): UserSocialAccount
    {
        $account = new UserSocialAccount();
        $account->setProvider('github');
        $account->setClientId($clientId);
        $account->setUserId($userId);
        $account->setData('{}');
        $account->setCreatedAt(time());
        $account->save();

        return $account;
    }

    private function createPendingAccount(string $clientId, ?string $code): UserSocialAccount
    {
        $account = new UserSocialAccount();
        $account->setProvider('github');
        $account->setClientId($clientId);
        $account->setCode($code);
        $account->setData('{}');
        $account->setCreatedAt(time());
        $account->save();

        return $account;
    }

    private function createService(
        ModuleConfig $config,
        ?CurrentUser $currentUser = null,
        ?EventDispatcherInterface $eventDispatcher = null,
    ): UserSocialAuthenticateService {
        $currentUser ??= $this->createMock(CurrentUser::class);
        $eventDispatcher ??= $this->createMock(EventDispatcherInterface::class);

        return new UserSocialAuthenticateService(
            $config,
            $currentUser,
            $this->session,
            $eventDispatcher,
            $this->createUserCreationHelper($config, $eventDispatcher),
        );
    }

    private function createUserCreationHelper(ModuleConfig $config, EventDispatcherInterface $eventDispatcher): UserCreationHelper
    {
        $passwordHasher = new PasswordHasher();

        return new UserCreationHelper(
            $this->createMock(MailService::class),
            $eventDispatcher,
            $passwordHasher,
            $config,
            new PasswordHistoryService($passwordHasher, $config),
        );
    }
}
