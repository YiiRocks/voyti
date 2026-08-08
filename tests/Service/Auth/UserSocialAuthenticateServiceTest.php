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

    public function testRunAccountWithoutUserIdAndEmptyCodeReturnsFailure(): void
    {
        $this->createPendingAccount('empty_code_client', '');

        $result = $this->createService(VoytiConfigFactory::create(enableSocialNetworkRegistration: true))
            ->run('github', 'empty_code_client', ['email' => 'test@example.com']);

        self::assertTrue($result->isFailure());
        self::assertSame('Unable to prepare the social account connection', $result->getMessage());
    }

    public function testRunAccountWithUserIdUserNotFoundReturnsFailure(): void
    {
        $this->createConnectedAccount('orphan_client', 99999);

        $result = $this->createService(VoytiConfigFactory::create(enableSocialNetworkRegistration: true))
            ->run('github', 'orphan_client', ['email' => 'test@example.com']);

        self::assertTrue($result->isFailure());
        self::assertSame('Associated user not found', $result->getMessage());
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

    public function testRunCreatesNewAccountWithDeduplicatedUsernameOnCollision(): void
    {
        $currentUser = $this->createCurrentUser();

        // Only the base name is taken, so the first numeric suffix (2) must be used.
        $this->createUser('dupeuser', 'dupeuser@example.com');

        $result = $this->createService(VoytiConfigFactory::create(enableSocialNetworkRegistration: true), $currentUser)
            ->run('github', 'dupe_account', ['username' => 'dupeuser', 'email' => 'new_dupe@example.com']);

        self::assertTrue($result->isSuccess());
        self::assertFalse($currentUser->isGuest());

        $user = User::findByEmail('new_dupe@example.com');
        self::assertNotNull($user);
        self::assertSame('dupeuser_2', $user->getUsername());
    }

    public function testRunDerivesUsernameFromEmailPrefixWhenNoUsername(): void
    {
        $result = $this->createService(VoytiConfigFactory::create(enableSocialNetworkRegistration: true))
            ->run('github', 'prefix_client', ['email' => 'prefixname@example.com']);

        self::assertTrue($result->isSuccess());

        $user = User::findByEmail('prefixname@example.com');
        self::assertNotNull($user);
        self::assertSame('prefixname', $user->getUsername());
    }

    public function testRunEmptyClientIdWithNonArraySessionDataReturnsFailure(): void
    {
        // Non-array session data must be ignored (guarded), leaving the client id unresolved.
        $this->session->set('oauth_client_data', 'not-an-array');

        $result = $this->createService(VoytiConfigFactory::create(enableSocialNetworkRegistration: true))
            ->run('github', '', ['email' => 'test@example.com']);

        self::assertTrue($result->isFailure());
        self::assertSame('Unable to determine social network client ID', $result->getMessage());
    }

    public function testRunEmptyClientIdWithoutSessionDataReturnsFailure(): void
    {
        $result = $this->createService(VoytiConfigFactory::create(enableSocialNetworkRegistration: true))
            ->run('github', '', ['email' => 'test@example.com']);

        self::assertTrue($result->isFailure());
        self::assertSame('Unable to determine social network client ID', $result->getMessage());
    }

    public function testRunEmptyClientIdWithSessionDataUsesSession(): void
    {
        $this->session->set('oauth_client_data', ['user_id' => 'session_user_123']);

        $result = $this->createService(VoytiConfigFactory::create(enableSocialNetworkRegistration: true))
            ->run('github', '', ['email' => 'test@example.com']);

        self::assertTrue($result->isSuccess());
    }

    public function testRunIncrementsSuffixAcrossMultipleCollisions(): void
    {
        $currentUser = $this->createCurrentUser();

        // Both the base and _2 are taken, so the suffix must advance to _3.
        $this->createUser('dupeuser', 'dupeuser@example.com');
        $this->createUser('dupeuser_2', 'dupeuser2@example.com');

        $this->createService(VoytiConfigFactory::create(enableSocialNetworkRegistration: true), $currentUser)
            ->run('github', 'dupe_account2', ['username' => 'dupeuser', 'email' => 'new_dupe2@example.com']);

        self::assertSame('dupeuser_3', User::findByEmail('new_dupe2@example.com')?->getUsername());
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

    public function testRunNewAccountWithNameAttributeAsFallback(): void
    {
        $result = $this->createService(VoytiConfigFactory::create(enableSocialNetworkRegistration: true))
            ->run('github', 'name_fallback_client', ['name' => 'fallback_user']);

        self::assertTrue($result->isSuccess());

        $saved = UserSocialAccount::findByProviderAndClientId('github', 'name_fallback_client');
        self::assertNotNull($saved);
        self::assertSame('fallback_user', $saved->getUsername());
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

    public function testRunPrefersUsernameAttributeOverName(): void
    {
        $result = $this->createService(VoytiConfigFactory::create(enableSocialNetworkRegistration: true))
            ->run('github', 'prefer_client', ['username' => 'chosen', 'name' => 'ignored', 'email' => 'prefer@example.com']);

        self::assertTrue($result->isSuccess());

        $user = User::findByEmail('prefer@example.com');
        self::assertNotNull($user);
        self::assertSame('chosen', $user->getUsername());
    }

    public function testRunTreatsEmptyUsernameAttributeAsAbsentAndFallsBackToName(): void
    {
        $result = $this->createService(VoytiConfigFactory::create(enableSocialNetworkRegistration: true))
            ->run('github', 'empty_username_client', ['username' => '', 'name' => 'realname', 'email' => 'emptyu@example.com']);

        self::assertTrue($result->isSuccess());
        // The blank username is ignored, so the name attribute is used.
        self::assertSame('realname', User::findByEmail('emptyu@example.com')?->getUsername());
    }

    public function testRunTruncatesLongUsernameTo250Characters(): void
    {
        $result = $this->createService(VoytiConfigFactory::create(enableSocialNetworkRegistration: true))
            ->run('github', 'long_client', ['username' => str_repeat('a', 300), 'email' => 'long@example.com']);

        self::assertTrue($result->isSuccess());
        self::assertSame(250, strlen((string) User::findByEmail('long@example.com')?->getUsername()));
    }

    public function testRunWithBlockedUserReturnsFailure(): void
    {
        $user = $this->createUser('blocked', 'blocked@example.com', blockedAt: time());
        $this->createConnectedAccount('blocked_client', (int) $user->getId());

        $result = $this->createService(VoytiConfigFactory::create(enableSocialNetworkRegistration: true))
            ->run('github', 'blocked_client', ['email' => 'test@example.com']);

        self::assertTrue($result->isFailure());
        self::assertSame('Your account has been blocked', $result->getMessage());
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
