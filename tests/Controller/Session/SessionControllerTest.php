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

    public function testConfirmGetWithNoCredentialsShowsLoginForm(): void
    {
        $html = (string) $this->createController()->confirm(new ServerRequest('GET', '/'))->getBody();

        self::assertStringContainsString('Log In', $html);
    }

    public function testConfirmPostConstructsFormRequiringTwoFactorCode(): void
    {
        $container = $this->getTestContainer($this->mockOverrides());
        $container->get(SessionInterface::class)->set('credentials', [
            'login' => 'jdoe',
            'rememberMe' => false,
        ]);

        $request = (new ServerRequest('POST', '/'))->withParsedBody(['login' => ['twoFactorAuthenticationCode' => '']]);

        $html = (string) $container->get(SessionController::class)->confirm($request)->getBody();

        self::assertStringContainsString('Two-Factor Authentication', $html);
    }

    public function testConfirmPostSuccessRedirectsToConfiguredRoute(): void
    {
        $config = VoytiConfigFactory::create(homeRoute: 'app/dashboard');
        $container = $this->getTestContainer(array_merge($this->mockOverrides(), [VoytiConfig::class => $config]));
        $container->get(SessionInterface::class)->set('credentials', [
            'login' => 'jdoe',
            'rememberMe' => false,
        ]);

        $user = $this->createUser(
            username: 'jdoe',
            email: 'jdoe@example.com',
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
        // A completed 2FA login logs the user in, clears the stashed credentials, records login
        // metadata, dispatches AfterLoginEvent and connects the pending social account.
        $this->assertSame((int) $user->getId(), (int) $this->currentUser->getId());
        $this->assertFalse($this->session->has('credentials'));
        $this->assertNotNull(User::findById((int) $user->getId())?->getLastLoginAt());
        $this->assertTrue($this->eventDispatcher->hasEvent(AfterLoginEvent::class));
        $this->assertSame((int) $user->getId(), UserSocialAccount::findByProviderAndClientId('github', 'client-pendcode')?->getUserId());
    }

    public function testConfirmPostSuccessWithRememberMeAddsCookie(): void
    {
        $container = $this->getTestContainer($this->mockOverrides());
        $container->get(SessionInterface::class)->set('credentials', [
            'login' => 'jdoe',
            'rememberMe' => true,
        ]);

        $this->createUser(
            username: 'jdoe',
            email: 'jdoe@example.com',
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
        // The real RememberMeCookieService sets the auto-login cookie carrying the session id.
        $cookie = $result->getHeaderLine('Set-Cookie');
        $this->assertStringContainsString('autoLogin', $cookie);
        $this->assertStringContainsString('sessprobe', $cookie);
    }

    public function testConfirmPostWithGoogleMethodAndInvalidCodeShowsError(): void
    {
        $container = $this->getTestContainer($this->mockOverrides());
        $container->get(SessionInterface::class)->set('credentials', [
            'login' => 'jdoe',
            'rememberMe' => false,
        ]);

        $this->createUser(
            username: 'jdoe',
            email: 'jdoe@example.com',
            passwordHash: $this->passwordHasher->hash('secret'),
            confirmedAt: time(),
            authTfEnabled: true,
            authTfType: 'google',
        );

        $request = (new ServerRequest('POST', '/'))->withParsedBody(['login' => ['twoFactorAuthenticationCode' => 'wrong']]);

        $html = (string) $container->get(SessionController::class)->confirm($request)->getBody();

        // The Google-method confirm view renders without the email-only "enter the code" hint.
        self::assertStringContainsString('Two-Factor Authentication', $html);
        self::assertStringNotContainsString('Enter the verification code sent to your email', $html);
        // The translated CodeValidator error, attached to the code field, renders in both the summary
        // and under the field (requires the translator wired into the validator + the field binding).
        self::assertSame(2, substr_count($html, 'Two factor authentication is not configured.'));
    }

    public function testLoginGetShowsForm(): void
    {
        $html = (string) $this->createController()->login(new ServerRequest('GET', '/'))->getBody();

        self::assertStringContainsString('Log In', $html);
    }

    public function testLoginPostSuccessRedirectsToConfiguredRoute(): void
    {
        $config = VoytiConfigFactory::create(homeRoute: 'app/dashboard');

        $user = $this->createUser(
            username: 'jdoe',
            email: 'jdoe@example.com',
            passwordHash: $this->passwordHasher->hash('secret'),
            confirmedAt: time(),
        );
        $pending = $this->createPendingSocialAccount('pendcode');
        $this->session->set('social_network_account_code', 'pendcode');

        $request = (new ServerRequest('POST', '/'))->withParsedBody(['login' => ['login' => 'jdoe', 'password' => 'secret']]);

        $result = $this->createController([VoytiConfig::class => $config])->login($request);

        $this->assertSame(302, $result->getStatusCode());
        $this->assertSame('//app/dashboard', $result->getHeaderLine('Location'));
        // A direct (no-2FA) login logs the user in, records login metadata, dispatches AfterLoginEvent
        // and connects the pending social account.
        $this->assertSame((int) $user->getId(), (int) $this->currentUser->getId());
        $this->assertNotNull(User::findById((int) $user->getId())?->getLastLoginAt());
        $this->assertTrue($this->eventDispatcher->hasEvent(AfterLoginEvent::class));
        $this->assertSame((int) $user->getId(), UserSocialAccount::findByProviderAndClientId('github', 'client-pendcode')?->getUserId());
    }

    public function testLoginPostSuccessWithRememberMeAddsCookie(): void
    {
        $this->createUser(
            username: 'jdoe',
            email: 'jdoe@example.com',
            passwordHash: $this->passwordHasher->hash('secret'),
            confirmedAt: time(),
        );

        $request = (new ServerRequest('POST', '/'))->withParsedBody(['login' => ['login' => 'jdoe', 'password' => 'secret', 'rememberMe' => true]]);

        $this->session->open();
        $this->session->setId('sessprobe');
        $result = $this->createController()->login($request);

        $this->assertSame(302, $result->getStatusCode());
        // The real RememberMeCookieService sets the auto-login cookie carrying the session id.
        $cookie = $result->getHeaderLine('Set-Cookie');
        $this->assertStringContainsString('autoLogin', $cookie);
        $this->assertStringContainsString('sessprobe', $cookie);
    }

    public function testLoginPostWithBlockedUserShowsError(): void
    {
        $this->createUser(
            username: 'jdoe',
            email: 'jdoe@example.com',
            passwordHash: $this->passwordHasher->hash('secret'),
            confirmedAt: time(),
            blockedAt: time(),
        );

        $request = (new ServerRequest('POST', '/'))->withParsedBody(['login' => ['login' => 'jdoe', 'password' => 'secret']]);

        $html = (string) $this->createController()->login($request)->getBody();

        self::assertStringContainsString('Log In', $html);
        self::assertSame(2, substr_count($html, 'Your account has been blocked'));
    }

    public function testLoginPostWithInvalidCredentialsShowsError(): void
    {
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['login' => ['login' => 'jdoe', 'password' => 'wrong']]);

        $html = (string) $this->createController()->login($request)->getBody();

        self::assertStringContainsString('Log In', $html);
        // The error, attached to the "login" field, renders in both the summary and under the field.
        self::assertSame(2, substr_count($html, 'Invalid login or password'));
    }

    public function testLoginPostWithTwoFactorEmailMethodSendsCodeAndShowsConfirm(): void
    {
        $config = VoytiConfigFactory::create(enableTwoFactorAuthentication: true);

        $this->createUser(
            username: 'jdoe',
            email: 'jdoe@example.com',
            passwordHash: $this->passwordHasher->hash('secret'),
            confirmedAt: time(),
            authTfEnabled: true,
            authTfType: 'email',
        );

        $request = (new ServerRequest('POST', '/'))->withParsedBody(['login' => ['login' => 'jdoe', 'password' => 'secret']]);

        $html = (string) $this->createController([VoytiConfig::class => $config])->login($request)->getBody();

        self::assertStringContainsString('Enter the verification code sent to your email', $html);
        $this->assertMailSent();
        // The credentials are stashed in the session for the pending 2FA confirmation step.
        $this->assertSame(['login' => 'jdoe', 'rememberMe' => false], $this->session->get('credentials'));
    }

    public function testLoginPostWithUnconfirmedEmailShowsError(): void
    {
        $this->createUser(
            username: 'jdoe',
            email: 'jdoe@example.com',
            passwordHash: $this->passwordHasher->hash('secret'),
        );

        $request = (new ServerRequest('POST', '/'))->withParsedBody(['login' => ['login' => 'jdoe', 'password' => 'secret']]);

        $html = (string) $this->createController()->login($request)->getBody();

        self::assertStringContainsString('Log In', $html);
        self::assertSame(2, substr_count($html, 'You need to confirm your email address'));
    }

    public function testLoginWhenAlreadyAuthenticatedRedirectsToHome(): void
    {
        $this->currentUser->login(new User());

        $result = $this->createController()->login(new ServerRequest('GET', '/'));

        $this->assertSame(302, $result->getStatusCode());
        $this->assertSame('//home', $result->getHeaderLine('Location'));
    }

    public function testLoginWithTwoFactorDisabledIgnoresUserTwoFactorAndLogsInDirectly(): void
    {
        // 2FA is disabled in config even though the account has it enabled: login must proceed
        // directly rather than routing through the confirm step.
        $this->createUser(
            username: 'jdoe',
            email: 'jdoe@example.com',
            passwordHash: $this->passwordHasher->hash('secret'),
            confirmedAt: time(),
            authTfEnabled: true,
            authTfType: 'email',
        );

        $config = VoytiConfigFactory::create(enableTwoFactorAuthentication: false);
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['login' => ['login' => 'jdoe', 'password' => 'secret']]);

        $result = $this->createController([VoytiConfig::class => $config])->login($request);

        $this->assertSame(302, $result->getStatusCode());
        $this->assertNoMailSent();
    }

    public function testLogoutRedirectsToHomeRouteByDefault(): void
    {
        $result = $this->createController()->logout();

        $this->assertSame(302, $result->getStatusCode());
        $this->assertSame('//home', $result->getHeaderLine('Location'));
        // The real RememberMeCookieService expires the auto-login cookie on the response.
        $this->assertStringContainsString('autoLogin', $result->getHeaderLine('Set-Cookie'));
    }

    public function testLogoutRevokesUserSessionRecord(): void
    {
        $user = $this->createUser('realuser', 'realuser@example.com');
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
        // Revoking the current device's session record dispatches a SessionEvent.
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
