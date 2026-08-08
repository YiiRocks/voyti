<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Service\Auth;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Psr\EventDispatcher\EventDispatcherInterface;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Model\UserSocialAccount;
use YiiRocks\Voyti\Service\Auth\PendingSocialAccountService;
use YiiRocks\Voyti\Service\Auth\UserSocialAuthenticateService;
use YiiRocks\Voyti\Service\Password\PasswordHistoryService;
use YiiRocks\Voyti\Service\User\UserCreationHelper;
use YiiRocks\Voyti\tests\Support\CurrentUserTrait;
use YiiRocks\Voyti\tests\Support\DatabaseTestCase;
use YiiRocks\Voyti\tests\Support\FakeSession;
use YiiRocks\Voyti\tests\Support\MailCapture;
use YiiRocks\Voyti\tests\Support\MailServiceFactoryTrait;
use YiiRocks\Voyti\tests\Support\TestPasswordHasherFactory;
use YiiRocks\Voyti\tests\Support\UserFactoryTrait;
use YiiRocks\Voyti\tests\Support\VoytiConfigFactory;
use YiiRocks\Voyti\VoytiConfig;
use Yiisoft\User\CurrentUser;

#[AllowMockObjectsWithoutExpectations]
final class UserSocialAuthenticateServiceTest extends DatabaseTestCase
{
    use CurrentUserTrait;
    use MailServiceFactoryTrait;
    use UserFactoryTrait;

    private FakeSession $session;

    protected function setUp(): void
    {
        parent::setUp();
        $this->session = new FakeSession();
        $this->session->open();
    }

    public function testRunClearsOauthClientDataOnLogin(): void
    {
        $user = $this->createUser('clear_oauth', 'clear_oauth@example.com');
        $this->createConnectedAccount('clear_oauth_client', (int) $user->getId());
        $this->session->set('oauth_client_data', ['some' => 'data']);

        $this->createService(VoytiConfigFactory::create(enableSocialNetworkRegistration: true))
            ->run('github', 'clear_oauth_client', ['email' => 'test@example.com']);

        self::assertFalse($this->session->has('oauth_client_data'));
    }

    public function testRunCoercesNumericAttributesToString(): void
    {
        $this->createUser('existing', '42@example.com');

        $result = $this->createService(VoytiConfigFactory::create(enableSocialNetworkRegistration: true))
            ->run('github', 'numeric_client', ['username' => 42, 'email' => '42@example.com']);

        self::assertTrue($result->isSuccess());

        $saved = UserSocialAccount::findByProviderAndClientId('github', 'numeric_client');
        self::assertNotNull($saved);
        self::assertSame('42', $saved->getUsername());
    }

    public function testRunCreatesNewAccountWhenNotFound(): void
    {
        $currentUser = $this->createCurrentUser();

        $result = $this->createService(VoytiConfigFactory::create(enableSocialNetworkRegistration: true), $currentUser)
            ->run('github', 'new_account', ['username' => 'newuser', 'email' => 'new@example.com'], ['REMOTE_ADDR' => '198.51.100.7']);

        self::assertTrue($result->isSuccess());
        self::assertFalse($currentUser->isGuest());

        $user = User::findByEmail('new@example.com');
        self::assertNotNull($user);
        self::assertSame('newuser', $user->getUsername());
        self::assertTrue($user->isConfirmed());
        // The auto-registered user records the request's registration IP.
        self::assertSame('198.51.100.7', $user->getRegistrationIp());

        $saved = UserSocialAccount::findByProviderAndClientId('github', 'new_account');
        self::assertNotNull($saved);
        self::assertSame((int) $user->getId(), $saved->getUserId());
        self::assertNull($saved->getCode());
        // Once linked to a user the account's own username/email are cleared, but the raw attributes
        // and creation timestamp are retained.
        self::assertNull($saved->getUsername());
        self::assertNull($saved->getEmail());
        self::assertGreaterThan(0, $saved->getCreatedAt());
        self::assertSame(['username' => 'newuser', 'email' => 'new@example.com'], json_decode((string) $saved->getData(), true));
    }

    public function testRunEmptyClientId(): void
    {
        // With non-array session data: fails
        $this->session->set('oauth_client_data', 'not-an-array');
        $result = $this->createService(VoytiConfigFactory::create(enableSocialNetworkRegistration: true))
            ->run('github', '', ['email' => 'empty1@example.com']);
        self::assertTrue($result->isFailure());
        self::assertSame('Unable to determine social network client ID', $result->getMessage());

        // Without session data: fails
        $result = $this->createService(VoytiConfigFactory::create(enableSocialNetworkRegistration: true))
            ->run('github', '', ['email' => 'empty2@example.com']);
        self::assertTrue($result->isFailure());
        self::assertSame('Unable to determine social network client ID', $result->getMessage());

        // With session data: uses session
        $this->session->set('oauth_client_data', ['user_id' => 'session_user_123']);
        $result = $this->createService(VoytiConfigFactory::create(enableSocialNetworkRegistration: true))
            ->run('github', '', ['email' => 'empty3@example.com']);
        self::assertTrue($result->isSuccess());
    }

    public function testRunErrors(): void
    {
        // Account without user ID and empty code: fails
        $this->createPendingAccount('empty_code_client', '');
        $result = $this->createService(VoytiConfigFactory::create(enableSocialNetworkRegistration: true))
            ->run('github', 'empty_code_client', ['email' => 'test@example.com']);
        self::assertTrue($result->isFailure());
        self::assertSame('Unable to prepare the social account connection', $result->getMessage());

        // Account with orphaned user: fails
        $this->createConnectedAccount('orphan_client', 99999);
        $result = $this->createService(VoytiConfigFactory::create(enableSocialNetworkRegistration: true))
            ->run('github', 'orphan_client', ['email' => 'test@example.com']);
        self::assertTrue($result->isFailure());
        self::assertSame('Associated user not found', $result->getMessage());

        // Blocked user: fails
        $user = $this->createUser('blocked', 'blocked@example.com', blockedAt: time());
        $this->createConnectedAccount('blocked_client', (int) $user->getId());
        $result = $this->createService(VoytiConfigFactory::create(enableSocialNetworkRegistration: true))
            ->run('github', 'blocked_client', ['email' => 'test@example.com']);
        self::assertTrue($result->isFailure());
        self::assertSame('Your account has been blocked', $result->getMessage());
    }

    public function testRunMergesSessionOauthDataWithCallbackAttributes(): void
    {
        // clientId comes from the session, the email from the callback attributes; both must survive
        // the merge so auto-registration uses the session-provided name and the callback email.
        $this->session->set('oauth_client_data', ['user_id' => 'sess-uid', 'name' => 'sessionname']);

        $result = $this->createService(VoytiConfigFactory::create(enableSocialNetworkRegistration: true))
            ->run('github', '', ['email' => 'merged@example.com']);

        self::assertTrue($result->isSuccess());

        $user = User::findByEmail('merged@example.com');
        self::assertNotNull($user);
        self::assertSame('sessionname', $user->getUsername());
    }

    public function testRunNewAccountWithNoEmailNoUsername(): void
    {
        $result = $this->createService(VoytiConfigFactory::create(enableSocialNetworkRegistration: true))
            ->run('github', 'bare_client', ['id' => 'bare_user_123']);

        self::assertTrue($result->isSuccess());

        $saved = UserSocialAccount::findByProviderAndClientId('github', 'bare_client');
        self::assertNotNull($saved);
        self::assertNull($saved->getUsername());
        self::assertNull($saved->getEmail());
        self::assertNotNull($saved->getCode());
        // The pending connection code is a 32-character random token.
        self::assertSame(32, strlen((string) $saved->getCode()));
    }

    public function testRunUsernameDuplication(): void
    {
        // Base name taken: uses first numeric suffix
        $currentUser = $this->createCurrentUser();
        $this->createUser('dupe1', 'dupe1@example.com');
        $result = $this->createService(VoytiConfigFactory::create(enableSocialNetworkRegistration: true), $currentUser)
            ->run('github', 'dupe_account', ['username' => 'dupe1', 'email' => 'new_dupe1@example.com']);
        self::assertTrue($result->isSuccess());
        self::assertFalse($currentUser->isGuest());
        self::assertSame('dupe1_2', User::findByEmail('new_dupe1@example.com')?->getUsername());

        // Base and _2 taken: increments to _3
        $currentUser2 = $this->createCurrentUser();
        $this->createUser('dupe2', 'dupe2@example.com');
        $this->createUser('dupe2_2', 'dupe22@example.com');
        $this->createService(VoytiConfigFactory::create(enableSocialNetworkRegistration: true), $currentUser2)
            ->run('github', 'dupe_account2', ['username' => 'dupe2', 'email' => 'new_dupe2@example.com']);
        self::assertSame('dupe2_3', User::findByEmail('new_dupe2@example.com')?->getUsername());
    }

    public function testRunUsernameHandling(): void
    {
        // Derives from email prefix when no username
        $result = $this->createService(VoytiConfigFactory::create(enableSocialNetworkRegistration: true))
            ->run('github', 'prefix_client', ['email' => 'prefixname@example.com']);
        self::assertTrue($result->isSuccess());
        self::assertSame('prefixname', User::findByEmail('prefixname@example.com')?->getUsername());

        // Uses name attribute as fallback
        $result = $this->createService(VoytiConfigFactory::create(enableSocialNetworkRegistration: true))
            ->run('github', 'name_fallback_client', ['name' => 'fallback_user']);
        self::assertTrue($result->isSuccess());
        self::assertSame('fallback_user', UserSocialAccount::findByProviderAndClientId('github', 'name_fallback_client')?->getUsername());

        // Prefers username over name
        $result = $this->createService(VoytiConfigFactory::create(enableSocialNetworkRegistration: true))
            ->run('github', 'prefer_client', ['username' => 'chosen', 'name' => 'ignored', 'email' => 'prefer@example.com']);
        self::assertTrue($result->isSuccess());
        self::assertSame('chosen', User::findByEmail('prefer@example.com')?->getUsername());

        // Treats empty username as absent and falls back to name
        $result = $this->createService(VoytiConfigFactory::create(enableSocialNetworkRegistration: true))
            ->run('github', 'empty_username_client', ['username' => '', 'name' => 'realname', 'email' => 'emptyu@example.com']);
        self::assertTrue($result->isSuccess());
        self::assertSame('realname', User::findByEmail('emptyu@example.com')?->getUsername());

        // Coerces numeric attributes to string
        $this->createUser('existing', '42@example.com');
        $result = $this->createService(VoytiConfigFactory::create(enableSocialNetworkRegistration: true))
            ->run('github', 'numeric_client', ['username' => 42, 'email' => '42@example.com']);
        self::assertTrue($result->isSuccess());
        self::assertSame('42', UserSocialAccount::findByProviderAndClientId('github', 'numeric_client')?->getUsername());

        // Truncates long usernames to 250 characters
        $result = $this->createService(VoytiConfigFactory::create(enableSocialNetworkRegistration: true))
            ->run('github', 'long_client', ['username' => str_repeat('a', 300), 'email' => 'long@example.com']);
        self::assertTrue($result->isSuccess());
        self::assertSame(250, strlen((string) User::findByEmail('long@example.com')?->getUsername()));
    }

    public function testRunWithLoggedInUserNoRemoteAddrDefaultsTo127(): void
    {
        $currentUser = $this->createCurrentUser();

        $user = $this->createUser('noremote', 'noremote@example.com');
        $this->createConnectedAccount('noremote_client', (int) $user->getId());

        $this->createService(VoytiConfigFactory::create(enableSocialNetworkRegistration: true), $currentUser)
            ->run('github', 'noremote_client', []);

        $updated = User::findByEmail('noremote@example.com');
        self::assertSame('127.0.0.1', $updated->getLastLoginIp());
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
        VoytiConfig $config,
        ?CurrentUser $currentUser = null,
        ?EventDispatcherInterface $eventDispatcher = null,
    ): UserSocialAuthenticateService {
        $currentUser ??= $this->createCurrentUser();
        $eventDispatcher ??= $this->createMock(EventDispatcherInterface::class);

        return new UserSocialAuthenticateService(
            $config,
            $currentUser,
            $this->session,
            $eventDispatcher,
            $this->createUserCreationHelper($config, $eventDispatcher),
            new PendingSocialAccountService($this->session),
        );
    }

    private function createUserCreationHelper(VoytiConfig $config, EventDispatcherInterface $eventDispatcher): UserCreationHelper
    {
        $passwordHasher = TestPasswordHasherFactory::create();

        return new UserCreationHelper(
            $this->createMailService(new MailCapture()),
            $eventDispatcher,
            $passwordHasher,
            $config,
            new PasswordHistoryService($passwordHasher, $config),
        );
    }
}
