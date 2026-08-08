<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Controller\Session;

use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use YiiRocks\Voyti\Controller\Session\SessionController;
use YiiRocks\Voyti\Event\Auth\AfterLoginEvent;
use YiiRocks\Voyti\Event\Session\SessionEvent;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Model\UserSessions;
use YiiRocks\Voyti\Model\UserSocialAccount;
use YiiRocks\Voyti\tests\Support\CurrentUserTrait;
use YiiRocks\Voyti\tests\Support\DatabaseTestCase;
use YiiRocks\Voyti\tests\Support\EventCaptureDispatcher;
use YiiRocks\Voyti\tests\Support\FakeSession;
use YiiRocks\Voyti\tests\Support\MailAssertionsTrait;
use YiiRocks\Voyti\tests\Support\TestContainerTrait;
use YiiRocks\Voyti\tests\Support\TestPasswordHasherFactory;
use YiiRocks\Voyti\tests\Support\UserFactoryTrait;
use YiiRocks\Voyti\tests\Support\ValidatorMockTrait;
use YiiRocks\Voyti\tests\Support\VoytiConfigFactory;
use YiiRocks\Voyti\VoytiConfig;
use Yiisoft\Security\PasswordHasher;
use Yiisoft\Session\Flash\FlashInterface;
use Yiisoft\Session\SessionInterface;
use Yiisoft\User\CurrentUser;
use Yiisoft\Validator\ValidatorInterface;

#[AllowMockObjectsWithoutExpectations]
final class SessionControllerTest extends DatabaseTestCase
{
    use CurrentUserTrait;
    use MailAssertionsTrait;
    use TestContainerTrait;
    use UserFactoryTrait;
    use ValidatorMockTrait;

    private ContainerInterface $container;
    private CurrentUser $currentUser;
    private EventCaptureDispatcher $eventDispatcher;
    private FlashInterface&MockObject $flash;
    private PasswordHasher $passwordHasher;
    private FakeSession $session;
    private ValidatorInterface&MockObject $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->currentUser = $this->createCurrentUser();
        $this->flash = $this->createMock(FlashInterface::class);
        $this->passwordHasher = TestPasswordHasherFactory::create();
        $this->validator = $this->mockValidValidator();
        $this->eventDispatcher = new EventCaptureDispatcher();
        $this->session = new FakeSession();
    }

    public function testConfirm(): void
    {
        // GET with no credentials: shows login form
        $html = (string) $this->createController()->confirm(new ServerRequest('GET', '/'))->getBody();
        self::assertStringContainsString('Log In', $html);

        // POST constructs form requiring 2FA code
        $container = $this->getTestContainer($this->mockOverrides());
        $container->get(SessionInterface::class)->set('credentials', [
            'login' => 'jdoe1',
            'rememberMe' => false,
        ]);
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['login' => ['twoFactorAuthenticationCode' => '']]);
        $html = (string) $container->get(SessionController::class)->confirm($request)->getBody();
        self::assertStringContainsString('Two-Factor Authentication', $html);

        // POST success: redirects and logs in, clears credentials, connects pending social account
        $config = VoytiConfigFactory::create(homeRoute: 'app/dashboard');
        $container = $this->getTestContainer(array_merge($this->mockOverrides(), [VoytiConfig::class => $config]));
        $container->get(SessionInterface::class)->set('credentials', [
            'login' => 'confirm_email',
            'rememberMe' => false,
        ]);
        $user1 = $this->createUser(
            username: 'confirm_email',
            email: 'confirm_email@example.com',
            passwordHash: $this->passwordHasher->hash('secret'),
            confirmedAt: time(),
            authTfEnabled: true,
            authTfType: 'email',
            authTfKey: '123456',
        );
        $pending = $this->createPendingSocialAccount('pendcode');
        $this->session->set('social_network_account_code', 'pendcode');
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['login' => ['twoFactorAuthenticationCode' => '123456']]);
        $result = $container->get(SessionController::class)->confirm($request);
        $this->assertSame(302, $result->getStatusCode());
        $this->assertSame('//app/dashboard', $result->getHeaderLine('Location'));
        $this->assertSame((int) $user1->getId(), (int) $this->currentUser->getId());
        $this->assertFalse($this->session->has('credentials'));
        $this->assertNotNull(User::findById((int) $user1->getId())?->getLastLoginAt());
        $this->assertTrue($this->eventDispatcher->hasEvent(AfterLoginEvent::class));
        $this->assertSame((int) $user1->getId(), UserSocialAccount::findByProviderAndClientId('github', 'client-pendcode')?->getUserId());

        // POST success with remember me: adds cookie
        $container = $this->getTestContainer($this->mockOverrides());
        $container->get(SessionInterface::class)->set('credentials', [
            'login' => 'confirm_remember',
            'rememberMe' => true,
        ]);
        $this->createUser(
            username: 'confirm_remember',
            email: 'confirm_remember@example.com',
            passwordHash: $this->passwordHasher->hash('secret'),
            confirmedAt: time(),
            authTfEnabled: true,
            authTfType: 'email',
            authTfKey: '123456',
        );
        $this->session->open();
        $this->session->setId('sessprobe');
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['login' => ['twoFactorAuthenticationCode' => '123456']]);
        $result = $container->get(SessionController::class)->confirm($request);
        $this->assertSame(302, $result->getStatusCode());
        $cookie = $result->getHeaderLine('Set-Cookie');
        $this->assertStringContainsString('autoLogin', $cookie);
        $this->assertStringContainsString('sessprobe', $cookie);

        // POST with Google method and invalid code: shows error without email hint
        $container = $this->getTestContainer($this->mockOverrides());
        $container->get(SessionInterface::class)->set('credentials', [
            'login' => 'confirm_google',
            'rememberMe' => false,
        ]);
        $this->createUser(
            username: 'confirm_google',
            email: 'confirm_google@example.com',
            passwordHash: $this->passwordHasher->hash('secret'),
            confirmedAt: time(),
            authTfEnabled: true,
            authTfType: 'google',
        );
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['login' => ['twoFactorAuthenticationCode' => 'wrong']]);
        $html = (string) $container->get(SessionController::class)->confirm($request)->getBody();
        self::assertStringContainsString('Two-Factor Authentication', $html);
        self::assertStringNotContainsString('Enter the verification code sent to your email', $html);
        self::assertSame(2, substr_count($html, 'Two factor authentication is not configured.'));
    }

    public function testConfirmWithRememberMe(): void
    {
        // POST success with remember me: adds cookie
        $container = $this->getTestContainer($this->mockOverrides());
        $sessionForConfirm = $container->get(SessionInterface::class);
        $sessionForConfirm->set('credentials', [
            'login' => 'confirm_remember_test',
            'rememberMe' => true,
        ]);
        $this->createUser(
            username: 'confirm_remember_test',
            email: 'confirm_remember_test@example.com',
            passwordHash: $this->passwordHasher->hash('secret'),
            confirmedAt: time(),
            authTfEnabled: true,
            authTfType: 'email',
            authTfKey: '123456',
        );
        $sessionForConfirm->open();
        $sessionForConfirm->setId('confirm-sessprobe');
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['login' => ['twoFactorAuthenticationCode' => '123456']]);
        $result = $container->get(SessionController::class)->confirm($request);
        $this->assertSame(302, $result->getStatusCode());
        $cookie = $result->getHeaderLine('Set-Cookie');
        $this->assertStringContainsString('autoLogin', $cookie);
        $this->assertStringContainsString('confirm-sessprobe', $cookie);
    }

    public function testLogin(): void
    {
        // GET shows login form
        $html = (string) $this->createController()->login(new ServerRequest('GET', '/'))->getBody();
        self::assertStringContainsString('Log In', $html);

        // POST success: redirects, logs in, records metadata, connects pending social account
        $config = VoytiConfigFactory::create(homeRoute: 'app/dashboard');
        $user1 = $this->createUser(
            username: 'login_success',
            email: 'login_success@example.com',
            passwordHash: $this->passwordHasher->hash('secret'),
            confirmedAt: time(),
        );
        $pending = $this->createPendingSocialAccount('pendcode');
        $this->session->set('social_network_account_code', 'pendcode');
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['login' => ['login' => 'login_success', 'password' => 'secret']]);
        $result = $this->createController([VoytiConfig::class => $config])->login($request);
        $this->assertSame(302, $result->getStatusCode());
        $this->assertSame('//app/dashboard', $result->getHeaderLine('Location'));
        $this->assertSame((int) $user1->getId(), (int) $this->currentUser->getId());
        $this->assertNotNull(User::findById((int) $user1->getId())?->getLastLoginAt());
        $this->assertTrue($this->eventDispatcher->hasEvent(AfterLoginEvent::class));
        $this->assertSame((int) $user1->getId(), UserSocialAccount::findByProviderAndClientId('github', 'client-pendcode')?->getUserId());

        // POST with invalid credentials: shows error (fresh controller for unauthenticated state)
        $freshUser = $this->createCurrentUser();
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['login' => ['login' => 'login_bad', 'password' => 'wrong']]);
        $html = (string) $this->createController([CurrentUser::class => $freshUser])->login($request)->getBody();
        self::assertStringContainsString('Log In', $html);
        self::assertSame(2, substr_count($html, 'Invalid login or password'));
    }

    public function testLoginWhenAlreadyAuthenticated(): void
    {
        // Already authenticated: redirects to home
        $freshCurrentUser = $this->createCurrentUser();
        $freshCurrentUser->login(new User());
        $result = $this->createController([CurrentUser::class => $freshCurrentUser])->login(new ServerRequest('GET', '/'));
        $this->assertSame(302, $result->getStatusCode());
        $this->assertSame('//home', $result->getHeaderLine('Location'));
    }

    public function testLoginWithErrors(): void
    {
        // Blocked user: shows error
        $user1 = $this->createUser(
            username: 'login_blocked',
            email: 'login_blocked@example.com',
            passwordHash: $this->passwordHasher->hash('secret'),
            confirmedAt: time(),
            blockedAt: time(),
        );
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['login' => ['login' => 'login_blocked', 'password' => 'secret']]);
        $html = (string) $this->createController()->login($request)->getBody();
        self::assertStringContainsString('Log In', $html);
        self::assertSame(2, substr_count($html, 'Your account has been blocked'));

        // Unconfirmed email: shows error
        $user2 = $this->createUser(
            username: 'login_unconfirmed',
            email: 'login_unconfirmed@example.com',
            passwordHash: $this->passwordHasher->hash('secret'),
        );
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['login' => ['login' => 'login_unconfirmed', 'password' => 'secret']]);
        $html = (string) $this->createController()->login($request)->getBody();
        self::assertStringContainsString('Log In', $html);
        self::assertSame(2, substr_count($html, 'You need to confirm your email address'));
    }

    public function testLoginWithRememberMe(): void
    {
        // POST success with remember me: adds cookie
        $this->createUser(
            username: 'remember_login',
            email: 'remember_login@example.com',
            passwordHash: $this->passwordHasher->hash('secret'),
            confirmedAt: time(),
        );
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['login' => ['login' => 'remember_login', 'password' => 'secret', 'rememberMe' => true]]);
        $this->session->open();
        $this->session->setId('sessprobe1');
        $result = $this->createController()->login($request);
        $this->assertSame(302, $result->getStatusCode());
        $cookie = $result->getHeaderLine('Set-Cookie');
        $this->assertStringContainsString('autoLogin', $cookie);
        $this->assertStringContainsString('sessprobe1', $cookie);
    }

    public function testLoginWithTwoFactor(): void
    {
        // 2FA enabled: sends code and shows confirm, stashes credentials
        $config2fa = VoytiConfigFactory::create(enableTwoFactorAuthentication: true);
        $user1 = $this->createUser(
            username: 'login_2fa',
            email: 'login_2fa@example.com',
            passwordHash: $this->passwordHasher->hash('secret'),
            confirmedAt: time(),
            authTfEnabled: true,
            authTfType: 'email',
        );
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['login' => ['login' => 'login_2fa', 'password' => 'secret']]);
        $html = (string) $this->createController([VoytiConfig::class => $config2fa])->login($request)->getBody();
        self::assertStringContainsString('Enter the verification code sent to your email', $html);
        $this->assertMailSent();
        $this->assertSame(['login' => 'login_2fa', 'rememberMe' => false], $this->session->get('credentials'));

        // 2FA disabled in config but enabled on user: bypasses 2FA and logs in directly
        $user2 = $this->createUser(
            username: 'login_2fa_disabled',
            email: 'login_2fa_disabled@example.com',
            passwordHash: $this->passwordHasher->hash('secret'),
            confirmedAt: time(),
            authTfEnabled: true,
            authTfType: 'email',
        );
        $config2faOff = VoytiConfigFactory::create(enableTwoFactorAuthentication: false);
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['login' => ['login' => 'login_2fa_disabled', 'password' => 'secret']]);
        $result = $this->createController([VoytiConfig::class => $config2faOff])->login($request);
        $this->assertSame(302, $result->getStatusCode());
        $this->assertNoMailSent();
    }

    public function testLogout(): void
    {
        // Default: redirects to home and expires auto-login cookie
        $result = $this->createController()->logout();
        $this->assertSame(302, $result->getStatusCode());
        $this->assertSame('//home', $result->getHeaderLine('Location'));
        $this->assertStringContainsString('autoLogin', $result->getHeaderLine('Set-Cookie'));

        // Revokes user session record, changes auth key, dispatches SessionEvent
        $user = $this->createUser('logout_revoke', 'logout_revoke@example.com');
        $sessionId = 'test-session-to-revoke';
        $userSession = new UserSessions();
        $userSession->setUserId($user->getIdOrZero());
        $userSession->setSessionId($sessionId);
        $userSession->setIp('192.168.1.1');
        $userSession->setCreatedAt(time());
        $userSession->setUpdatedAt(time());
        $userSession->save();
        $originalAuthKey = $user->getAuthKey();
        $this->currentUser->login($user);
        $container = $this->getTestContainer($this->mockOverrides());
        $session = $container->get(SessionInterface::class);
        $session->open();
        $session->setId($sessionId);
        $container->get(SessionController::class)->logout();
        $revoked = UserSessions::findByUserIdAndSessionId($user->getIdOrZero(), $sessionId);
        $this->assertNotNull($revoked);
        $this->assertTrue($revoked->isRevoked());
        $this->assertNotSame($originalAuthKey, User::findById((int) $user->getId())?->getAuthKey());
        $event = $this->eventDispatcher->getEvent(SessionEvent::class);
        $this->assertInstanceOf(SessionEvent::class, $event);
        $this->assertSame(SessionEvent::SESSION_TERMINATED, $event->getData()['type'] ?? null);
        $this->assertSame($sessionId, $event->getSessionId());
    }

    private function createController(array $extraOverrides = []): SessionController
    {
        $this->container = $this->getTestContainer(
            array_merge($this->mockOverrides(), $extraOverrides),
        );

        return $this->container->get(SessionController::class);
    }

    private function createPendingSocialAccount(string $code): UserSocialAccount
    {
        $account = new UserSocialAccount();
        $account->setProvider('github');
        $account->setClientId('client-' . $code);
        $account->setCode($code);
        $account->setCreatedAt(time());
        $account->save();

        return $account;
    }

    private function mockOverrides(): array
    {
        return [
            CurrentUser::class => $this->currentUser,
            EventDispatcherInterface::class => $this->eventDispatcher,
            FlashInterface::class => $this->flash,
            PasswordHasher::class => $this->passwordHasher,
            SessionInterface::class => $this->session,
            ValidatorInterface::class => $this->validator,
        ];
    }
}
