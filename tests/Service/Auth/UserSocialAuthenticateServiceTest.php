<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Service\Auth;

use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\Entity\UserSocialAccount;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Service\Auth\UserSocialAuthenticateService;
use YiiRocks\Voyti\tests\Support\DatabaseSetupTrait;
use YiiRocks\Voyti\tests\Support\FakeSession;
use Yiisoft\User\CurrentUser;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
final class UserSocialAuthenticateServiceTest extends TestCase
{
    use DatabaseSetupTrait;

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
        $config = new ModuleConfig(enableSocialNetworkRegistration: true);
        $currentUser = $this->createMock(CurrentUser::class);
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $account = new UserSocialAccount();
        $account->setProvider('github');
        $account->setClientId('empty_code_client');
        $account->setCode('');
        $account->setData('{}');
        $account->setCreatedAt(time());
        $account->save();

        $service = new UserSocialAuthenticateService(
            $config,
            $currentUser,
            $this->session,
            $eventDispatcher,
        );

        $result = $service->run('github', 'empty_code_client', ['email' => 'test@example.com']);
        self::assertTrue($result->isFailure());
        self::assertSame('Unable to prepare the social account connection', $result->getMessage());
    }

    public function testRunAccountWithoutUserIdAndNoCodeReturnsFailure(): void
    {
        $config = new ModuleConfig(enableSocialNetworkRegistration: true);
        $currentUser = $this->createMock(CurrentUser::class);
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $account = new UserSocialAccount();
        $account->setProvider('github');
        $account->setClientId('no_code_client');
        $account->setCode(null);
        $account->setData('{}');
        $account->setCreatedAt(time());
        $account->save();

        $service = new UserSocialAuthenticateService(
            $config,
            $currentUser,
            $this->session,
            $eventDispatcher,
        );

        $result = $service->run('github', 'no_code_client', ['email' => 'test@example.com']);
        self::assertTrue($result->isFailure());
        self::assertSame('Unable to prepare the social account connection', $result->getMessage());
    }

    public function testRunAccountWithoutUserIdSetsSessionCode(): void
    {
        $config = new ModuleConfig(enableSocialNetworkRegistration: true);
        $currentUser = $this->createMock(CurrentUser::class);
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $account = new UserSocialAccount();
        $account->setProvider('github');
        $account->setClientId('pending_client');
        $account->setCode('pending_code_123');
        $account->setData('{}');
        $account->setCreatedAt(time());
        $account->save();

        $service = new UserSocialAuthenticateService(
            $config,
            $currentUser,
            $this->session,
            $eventDispatcher,
        );

        $result = $service->run('github', 'pending_client', ['email' => 'test@example.com']);
        self::assertTrue($result->isSuccess());
        self::assertSame('pending_code_123', $this->session->get('social_network_account_code'));
    }

    public function testRunAccountWithUserIdUserNotFoundReturnsFailure(): void
    {
        $config = new ModuleConfig(enableSocialNetworkRegistration: true);
        $currentUser = $this->createMock(CurrentUser::class);
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $account = new UserSocialAccount();
        $account->setProvider('github');
        $account->setClientId('orphan_client');
        $account->setUserId(99999);
        $account->setData('{}');
        $account->setCreatedAt(time());
        $account->save();

        $service = new UserSocialAuthenticateService(
            $config,
            $currentUser,
            $this->session,
            $eventDispatcher,
        );

        $result = $service->run('github', 'orphan_client', ['email' => 'test@example.com']);
        self::assertTrue($result->isFailure());
        self::assertSame('Associated user not found', $result->getMessage());
    }

    public function testRunClearsOauthClientDataOnLogin(): void
    {
        $config = new ModuleConfig(enableSocialNetworkRegistration: true);
        $currentUser = $this->createMock(CurrentUser::class);
        $currentUser->method('login');
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->method('dispatch');

        $user = new User();
        $user->setUsername('clear_oauth');
        $user->setEmail('clear_oauth@example.com');
        $user->setPasswordHash('hash');
        $user->setAuthKey('key');
        $user->setCreatedAt(time());
        $user->setUpdatedAt(time());
        $user->save();

        $account = new UserSocialAccount();
        $account->setProvider('github');
        $account->setClientId('clear_oauth_client');
        $account->setUserId((int) $user->getId());
        $account->setData('{}');
        $account->setCreatedAt(time());
        $account->save();

        $this->session->set('oauth_client_data', ['some' => 'data']);

        $service = new UserSocialAuthenticateService(
            $config,
            $currentUser,
            $this->session,
            $eventDispatcher,
        );

        $service->run('github', 'clear_oauth_client', ['email' => 'test@example.com']);
        self::assertFalse($this->session->has('oauth_client_data'));
    }

    public function testRunCreatesNewAccountWhenNotFound(): void
    {
        $config = new ModuleConfig(enableSocialNetworkRegistration: true);
        $currentUser = $this->createMock(CurrentUser::class);
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $service = new UserSocialAuthenticateService(
            $config,
            $currentUser,
            $this->session,
            $eventDispatcher,
        );

        $result = $service->run('github', 'new_account', ['username' => 'newuser', 'email' => 'new@example.com']);
        self::assertTrue($result->isSuccess());

        $saved = UserSocialAccount::findByProviderAndClientId('github', 'new_account');
        self::assertNotNull($saved);
        self::assertSame('newuser', $saved->getUsername());
        self::assertSame('new@example.com', $saved->getEmail());
        self::assertNotNull($saved->getCode());
    }

    public function testRunEmptyClientIdWithoutSessionDataReturnsFailure(): void
    {
        $config = new ModuleConfig(enableSocialNetworkRegistration: true);
        $currentUser = $this->createMock(CurrentUser::class);
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $service = new UserSocialAuthenticateService(
            $config,
            $currentUser,
            $this->session,
            $eventDispatcher,
        );

        $result = $service->run('github', '', ['email' => 'test@example.com']);
        self::assertTrue($result->isFailure());
        self::assertSame('Unable to determine social network client ID', $result->getMessage());
    }

    public function testRunEmptyClientIdWithSessionDataUsesSession(): void
    {
        $config = new ModuleConfig(enableSocialNetworkRegistration: true);
        $currentUser = $this->createMock(CurrentUser::class);
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $this->session->set('oauth_client_data', ['user_id' => 'session_user_123']);

        $service = new UserSocialAuthenticateService(
            $config,
            $currentUser,
            $this->session,
            $eventDispatcher,
        );

        $result = $service->run('github', '', ['email' => 'test@example.com']);
        self::assertTrue($result->isSuccess());
    }

    public function testRunNewAccountWithExistingEmailConnectsUser(): void
    {
        $config = new ModuleConfig(enableSocialNetworkRegistration: true);

        $existing = new User();
        $existing->setUsername('existing');
        $existing->setEmail('existing@example.com');
        $existing->setPasswordHash('hash');
        $existing->setAuthKey('key');
        $existing->setCreatedAt(time());
        $existing->setUpdatedAt(time());
        $existing->save();

        $currentUser = $this->createMock(CurrentUser::class);
        $currentUser->expects($this->once())->method('login');
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->once())->method('dispatch');

        $service = new UserSocialAuthenticateService(
            $config,
            $currentUser,
            $this->session,
            $eventDispatcher,
        );

        $result = $service->run('github', 'existing_email_client', ['email' => 'existing@example.com', 'username' => 'ext']);
        self::assertTrue($result->isSuccess());

        $saved = UserSocialAccount::findByProviderAndClientId('github', 'existing_email_client');
        self::assertNotNull($saved);
        self::assertSame((int) $existing->getId(), $saved->getUserId());
        self::assertNull($saved->getCode());
    }

    public function testRunNewAccountWithNameAttributeAsFallback(): void
    {
        $config = new ModuleConfig(enableSocialNetworkRegistration: true);
        $currentUser = $this->createMock(CurrentUser::class);
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $service = new UserSocialAuthenticateService(
            $config,
            $currentUser,
            $this->session,
            $eventDispatcher,
        );

        $result = $service->run('github', 'name_fallback_client', ['name' => 'fallback_user']);
        self::assertTrue($result->isSuccess());

        $saved = UserSocialAccount::findByProviderAndClientId('github', 'name_fallback_client');
        self::assertNotNull($saved);
        self::assertSame('fallback_user', $saved->getUsername());
    }

    public function testRunNewAccountWithNoEmailNoUsername(): void
    {
        $config = new ModuleConfig(enableSocialNetworkRegistration: true);
        $currentUser = $this->createMock(CurrentUser::class);
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $service = new UserSocialAuthenticateService(
            $config,
            $currentUser,
            $this->session,
            $eventDispatcher,
        );

        $result = $service->run('github', 'bare_client', ['id' => 'bare_user_123']);
        self::assertTrue($result->isSuccess());

        $saved = UserSocialAccount::findByProviderAndClientId('github', 'bare_client');
        self::assertNotNull($saved);
        self::assertNull($saved->getUsername());
        self::assertNull($saved->getEmail());
        self::assertNotNull($saved->getCode());
    }

    public function testRunSocialRegistrationDisabledReturnsFailure(): void
    {
        $config = new ModuleConfig(enableSocialNetworkRegistration: false);
        $currentUser = $this->createMock(CurrentUser::class);
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $service = new UserSocialAuthenticateService(
            $config,
            $currentUser,
            $this->session,
            $eventDispatcher,
        );

        $result = $service->run('github', 'client123', ['email' => 'test@example.com']);
        self::assertTrue($result->isFailure());
        self::assertSame('Social network registration is disabled', $result->getMessage());
    }

    public function testRunWithBlockedUserReturnsFailure(): void
    {
        $config = new ModuleConfig(enableSocialNetworkRegistration: true);
        $currentUser = $this->createMock(CurrentUser::class);
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $user = new User();
        $user->setUsername('blocked');
        $user->setEmail('blocked@example.com');
        $user->setPasswordHash('hash');
        $user->setAuthKey('key');
        $user->setBlockedAt(time());
        $user->setCreatedAt(time());
        $user->setUpdatedAt(time());
        $user->save();

        $account = new UserSocialAccount();
        $account->setProvider('github');
        $account->setClientId('blocked_client');
        $account->setUserId((int) $user->getId());
        $account->setData('{}');
        $account->setCreatedAt(time());
        $account->save();

        $service = new UserSocialAuthenticateService(
            $config,
            $currentUser,
            $this->session,
            $eventDispatcher,
        );

        $result = $service->run('github', 'blocked_client', ['email' => 'test@example.com']);
        self::assertTrue($result->isFailure());
        self::assertSame('Your account has been blocked', $result->getMessage());
    }

    public function testRunWithLoggedInUserNoRemoteAddrDefaultsTo127(): void
    {
        $config = new ModuleConfig(enableSocialNetworkRegistration: true);
        $currentUser = $this->createMock(CurrentUser::class);
        $currentUser->method('login');
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->method('dispatch');

        $user = new User();
        $user->setUsername('noremote');
        $user->setEmail('noremote@example.com');
        $user->setPasswordHash('hash');
        $user->setAuthKey('key');
        $user->setCreatedAt(time());
        $user->setUpdatedAt(time());
        $user->save();

        $account = new UserSocialAccount();
        $account->setProvider('github');
        $account->setClientId('noremote_client');
        $account->setUserId((int) $user->getId());
        $account->setData('{}');
        $account->setCreatedAt(time());
        $account->save();

        $service = new UserSocialAuthenticateService(
            $config,
            $currentUser,
            $this->session,
            $eventDispatcher,
        );

        $service->run('github', 'noremote_client', []);

        $updated = User::findByEmail('noremote@example.com');
        self::assertSame('127.0.0.1', $updated->getLastLoginIp());
    }

    public function testRunWithLoggedInUserUpdatesLastLoginIp(): void
    {
        $config = new ModuleConfig(enableSocialNetworkRegistration: true);
        $currentUser = $this->createMock(CurrentUser::class);
        $currentUser->method('login');
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->method('dispatch');

        $user = new User();
        $user->setUsername('iplogin');
        $user->setEmail('iplogin@example.com');
        $user->setPasswordHash('hash');
        $user->setAuthKey('key');
        $user->setCreatedAt(time());
        $user->setUpdatedAt(time());
        $user->save();

        $account = new UserSocialAccount();
        $account->setProvider('github');
        $account->setClientId('ip_login_client');
        $account->setUserId((int) $user->getId());
        $account->setData('{}');
        $account->setCreatedAt(time());
        $account->save();

        $service = new UserSocialAuthenticateService(
            $config,
            $currentUser,
            $this->session,
            $eventDispatcher,
        );

        $service->run('github', 'ip_login_client', [], ['REMOTE_ADDR' => '192.168.1.50']);

        $updated = User::findByEmail('iplogin@example.com');
        self::assertSame('192.168.1.50', $updated->getLastLoginIp());
    }

    public function testRunWithValidConnectedUserAndDisabledIpLogging(): void
    {
        $config = new ModuleConfig(enableSocialNetworkRegistration: true, disableIpLogging: true);
        $currentUser = $this->createMock(CurrentUser::class);
        $currentUser->expects($this->once())->method('login');
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->once())->method('dispatch');

        $user = new User();
        $user->setUsername('active2');
        $user->setEmail('active2@example.com');
        $user->setPasswordHash('hash');
        $user->setAuthKey('key');
        $user->setLastLoginIp('1.2.3.4');
        $user->setCreatedAt(time());
        $user->setUpdatedAt(time());
        $user->save();

        $account = new UserSocialAccount();
        $account->setProvider('github');
        $account->setClientId('active_client2');
        $account->setUserId((int) $user->getId());
        $account->setData('{}');
        $account->setCreatedAt(time());
        $account->save();

        $service = new UserSocialAuthenticateService(
            $config,
            $currentUser,
            $this->session,
            $eventDispatcher,
        );

        $result = $service->run('github', 'active_client2', ['email' => 'test@example.com']);
        self::assertTrue($result->isSuccess());

        $updatedUser = User::findByEmail('active2@example.com');
        self::assertSame('127.0.0.1', $updatedUser->getLastLoginIp());
    }

    public function testRunWithValidConnectedUserLogsIn(): void
    {
        $config = new ModuleConfig(enableSocialNetworkRegistration: true);
        $currentUser = $this->createMock(CurrentUser::class);
        $currentUser->expects($this->once())->method('login');
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->once())->method('dispatch');

        $user = new User();
        $user->setUsername('active');
        $user->setEmail('active@example.com');
        $user->setPasswordHash('hash');
        $user->setAuthKey('key');
        $user->setCreatedAt(time());
        $user->setUpdatedAt(time());
        $user->save();

        $account = new UserSocialAccount();
        $account->setProvider('github');
        $account->setClientId('active_client');
        $account->setUserId((int) $user->getId());
        $account->setData('{}');
        $account->setCreatedAt(time());
        $account->save();

        $service = new UserSocialAuthenticateService(
            $config,
            $currentUser,
            $this->session,
            $eventDispatcher,
        );

        $result = $service->run('github', 'active_client', ['email' => 'test@example.com'], ['REMOTE_ADDR' => '10.0.0.1']);
        self::assertTrue($result->isSuccess());
    }
}
