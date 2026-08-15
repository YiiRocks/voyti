<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Controller\Session;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use YiiRocks\Voyti\Auth\LoginChallengeInterface;
use YiiRocks\Voyti\Auth\PostLoginHookInterface;
use YiiRocks\Voyti\Controller\Session\SessionController;
use YiiRocks\Voyti\Event\Auth\AfterLoginEvent;
use YiiRocks\Voyti\Event\Session\SessionEvent;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Model\UserSessions;
use YiiRocks\Voyti\Service\Auth\LoginCompletionService;
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
use Yiisoft\Http\Header;
use Yiisoft\Http\Status;
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

    public function testLogin(): void
    {
        // GET shows login form
        $html = (string) $this->createController()->login(new ServerRequest('GET', '/'))->getBody();
        self::assertStringContainsString('Log In', $html);

        // POST success: redirects, logs in, records metadata
        $config = VoytiConfigFactory::create(homeRoute: 'app/dashboard');
        $user1 = $this->createUser(
            username: 'login_success',
            email: 'login_success@example.com',
            passwordHash: $this->passwordHasher->hash('secret'),
            confirmedAt: time(),
        );
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['login' => ['login' => 'login_success', 'password' => 'secret']]);
        $result = $this->createController([VoytiConfig::class => $config])->login($request);
        $this->assertSame(302, $result->getStatusCode());
        $this->assertSame('//app/dashboard', $result->getHeaderLine('Location'));
        $this->assertSame((int) $user1->getId(), (int) $this->currentUser->getId());
        $reloaded = User::findById((int) $user1->getId());
        $this->assertNotNull($reloaded?->getLastLoginAt());
        $this->assertSame('127.0.0.1', $reloaded->getLastLoginIp());
        $this->assertTrue($this->eventDispatcher->hasEvent(AfterLoginEvent::class));

        // POST with invalid credentials: shows error (fresh controller for unauthenticated state)
        $freshUser = $this->createCurrentUser();
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['login' => ['login' => 'login_bad', 'password' => 'wrong']]);
        $html = (string) $this->createController([CurrentUser::class => $freshUser])->login($request)->getBody();
        self::assertStringContainsString('Log In', $html);
        self::assertSame(2, substr_count($html, 'Invalid login or password'));
    }

    public function testLoginConsultsLoginChallenges(): void
    {
        // A challenge that returns a response short-circuits login with that response; the user is
        // not logged in and login completion is never reached. This is the seam packages such as
        // yiirocks/voyti-2fa hook into.
        $challenge = new class ($this->responseFactory()) implements LoginChallengeInterface {
            public bool $called = false;

            public function __construct(private ResponseFactoryInterface $responseFactory) {}

            public function challenge(User $user, bool $rememberMe, ServerRequestInterface $request): ?ResponseInterface
            {
                $this->called = true;

                return $this->responseFactory->createResponse(Status::FOUND)->withHeader(Header::LOCATION, '//challenge');
            }
        };
        $this->createUser(
            username: 'login_challenge',
            email: 'login_challenge@example.com',
            passwordHash: $this->passwordHasher->hash('secret'),
            confirmedAt: time(),
        );
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['login' => ['login' => 'login_challenge', 'password' => 'secret']]);
        $result = $this->createControllerWithChallenges([$challenge])->login($request);
        $this->assertSame(302, $result->getStatusCode());
        $this->assertSame('//challenge', $result->getHeaderLine('Location'));
        $this->assertTrue($challenge->called);
        $this->assertNull($this->currentUser->getId());

        // A challenge that returns null lets login proceed to completion.
        $nullChallenge = new class implements LoginChallengeInterface {
            public function challenge(User $user, bool $rememberMe, ServerRequestInterface $request): ?ResponseInterface
            {
                return null;
            }
        };
        $user = $this->createUser(
            username: 'login_proceed',
            email: 'login_proceed@example.com',
            passwordHash: $this->passwordHasher->hash('secret'),
            confirmedAt: time(),
        );
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['login' => ['login' => 'login_proceed', 'password' => 'secret']]);
        $result = $this->createControllerWithChallenges([$nullChallenge])->login($request);
        $this->assertSame(302, $result->getStatusCode());
        $this->assertSame((int) $user->getId(), (int) $this->currentUser->getId());
    }

    public function testLoginConsultsPostLoginHooks(): void
    {
        // Packages such as yiirocks/voyti-social-auth hook into login completion via this seam
        // (e.g. connecting a pending social account); core only needs to prove every registered
        // hook is invoked with the just-logged-in user, in order.
        $hook = new class implements PostLoginHookInterface {
            /** @var list<string|null> */
            public array $calledWith = [];

            public function handle(User $user): void
            {
                $this->calledWith[] = $user->getId();
            }
        };
        $user = $this->createUser(
            username: 'login_hook',
            email: 'login_hook@example.com',
            passwordHash: $this->passwordHasher->hash('secret'),
            confirmedAt: time(),
        );
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['login' => ['login' => 'login_hook', 'password' => 'secret']]);

        $container = $this->getTestContainer(array_merge($this->mockOverrides(), [
            LoginCompletionService::class => [
                'class' => LoginCompletionService::class,
                '__construct()' => ['postLoginHooks' => [$hook]],
            ],
        ]));
        $result = $container->get(SessionController::class)->login($request);

        $this->assertSame(302, $result->getStatusCode());
        $this->assertSame([(string) $user->getId()], $hook->calledWith);
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

    /**
     * @param list<LoginChallengeInterface> $challenges
     */
    private function createControllerWithChallenges(array $challenges): SessionController
    {
        $container = $this->getTestContainer(array_merge($this->mockOverrides(), [
            SessionController::class => [
                'class' => SessionController::class,
                '__construct()' => ['loginChallenges' => $challenges],
            ],
        ]));

        return $container->get(SessionController::class);
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

    private function responseFactory(): ResponseFactoryInterface
    {
        return new Psr17Factory();
    }
}
