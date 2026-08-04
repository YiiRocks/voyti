<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Controller\Session;

use LogicException;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use YiiRocks\Voyti\Controller\Session\SessionController;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Model\UserSessions;
use YiiRocks\Voyti\Service\Auth\PendingSocialAccountService;
use YiiRocks\Voyti\Service\RememberMeCookieService;
use YiiRocks\Voyti\Service\TwoFactor\EmailCodeGeneratorService;
use YiiRocks\Voyti\tests\Support\DatabaseSetupTrait;
use YiiRocks\Voyti\tests\Support\FakeUrlGenerator;
use YiiRocks\Voyti\tests\Support\RedirectResponseMockTrait;
use YiiRocks\Voyti\tests\Support\TestContainerTrait;
use YiiRocks\Voyti\tests\Support\TestPasswordHasherFactory;
use YiiRocks\Voyti\tests\Support\UserFactoryTrait;
use YiiRocks\Voyti\tests\Support\ValidatorMockTrait;
use YiiRocks\Voyti\tests\Support\VoytiConfigFactory;
use YiiRocks\Voyti\tests\TestCase;
use YiiRocks\Voyti\VoytiConfig;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Security\PasswordHasher;
use Yiisoft\Session\Flash\FlashInterface;
use Yiisoft\Session\SessionInterface;
use Yiisoft\User\CurrentUser;
use Yiisoft\User\Guest\GuestIdentityInterface;
use Yiisoft\Validator\ValidatorInterface;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

#[AllowMockObjectsWithoutExpectations]
final class SessionControllerTest extends TestCase
{
    use DatabaseSetupTrait;
    use RedirectResponseMockTrait;
    use TestContainerTrait;
    use UserFactoryTrait;
    use ValidatorMockTrait;

    private CurrentUser&MockObject $currentUser;
    private FlashInterface&MockObject $flash;
    private PasswordHasher $passwordHasher;
    private PendingSocialAccountService&MockObject $pendingSocialAccountService;
    private RememberMeCookieService&MockObject $rememberMeCookieService;
    private ResponseFactoryInterface&MockObject $responseFactory;
    private EmailCodeGeneratorService&MockObject $twoFactorEmailCodeService;
    private ValidatorInterface&MockObject $validator;
    private WebViewRenderer&MockObject $viewRenderer;

    protected function setUp(): void
    {
        $this->setUpDatabase();
        $this->viewRenderer = $this->createMock(WebViewRenderer::class);
        $this->viewRenderer->method('withAddedInjections')->willReturnSelf();
        $this->currentUser = $this->createMock(CurrentUser::class);
        $this->responseFactory = $this->createMock(ResponseFactoryInterface::class);
        $this->flash = $this->createMock(FlashInterface::class);
        $this->passwordHasher = TestPasswordHasherFactory::create();
        $this->rememberMeCookieService = $this->createMock(RememberMeCookieService::class);
        $this->pendingSocialAccountService = $this->createMock(PendingSocialAccountService::class);
        $this->twoFactorEmailCodeService = $this->createMock(EmailCodeGeneratorService::class);
        $this->validator = $this->mockValidValidator();
    }

    protected function tearDown(): void
    {
        $this->tearDownDatabase();
    }

    public function testConfirmGetWithNoCredentialsShowsLoginForm(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->method('withViewPath')->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('session/login', $this->anything())
            ->willReturn($response);

        $controller = $this->createController();
        $request = new ServerRequest('GET', '/');

        $result = $controller->confirm($request);

        $this->assertSame($response, $result);
    }

    public function testConfirmPostConstructsFormRequiringTwoFactorCode(): void
    {
        $container = $this->getTestContainer($this->mockOverrides());
        $container->get(SessionInterface::class)->set('credentials', [
            'login' => 'jdoe',
            'rememberMe' => false,
        ]);

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->method('withViewPath')->willReturnSelf();
        $this->viewRenderer->method('render')->willReturn($response);

        $controller = $container->get(SessionController::class);
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['login' => ['twoFactorAuthenticationCode' => '']]);

        $result = $controller->confirm($request);

        $this->assertSame($response, $result);
    }

    public function testConfirmPostSuccessRedirectsToConfiguredRoute(): void
    {
        $config = VoytiConfigFactory::create(homeRoute: 'app/dashboard');
        $container = $this->getTestContainer(array_merge($this->mockOverrides(), [VoytiConfig::class => $config]));
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
            authTfType: 'email',
            authTfKey: '123456',
        );

        $response = $this->mockRedirectResponse($this->responseFactory, '//app/dashboard');

        $controller = $container->get(SessionController::class);
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['login' => ['twoFactorAuthenticationCode' => '123456']]);

        $result = $controller->confirm($request);

        $this->assertSame($response, $result);
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

        $this->rememberMeCookieService->expects($this->once())->method('addCookie')->willReturnArgument(1);

        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->method('createResponse')->willReturn($response);
        $response->method('withHeader')->willReturnSelf();

        $controller = $container->get(SessionController::class);
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['login' => ['twoFactorAuthenticationCode' => '123456']]);

        $result = $controller->confirm($request);

        $this->assertSame($response, $result);
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

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->method('withViewPath')->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('session/confirm', $this->callback(
                static fn(array $params): bool => $params['data']->method === 'google',
            ))
            ->willReturn($response);

        $controller = $container->get(SessionController::class);
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['login' => ['twoFactorAuthenticationCode' => 'wrong']]);

        $result = $controller->confirm($request);

        $this->assertSame($response, $result);
    }

    public function testLoginGetShowsForm(): void
    {
        $this->currentUser->method('getIdentity')->willReturn($this->createMock(GuestIdentityInterface::class));

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->expects($this->once())
            ->method('withViewPath')
            ->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('session/login', $this->arrayHasKey('form'))
            ->willReturn($response);

        $controller = $this->createController();
        $request = new ServerRequest('GET', '/');

        $result = $controller->login($request);

        $this->assertSame($response, $result);
    }

    public function testLoginPostSuccessRedirectsToConfiguredRoute(): void
    {
        $config = VoytiConfigFactory::create(homeRoute: 'app/dashboard');

        $this->currentUser->method('getIdentity')->willReturn($this->createMock(GuestIdentityInterface::class));

        $this->createUser(
            username: 'jdoe',
            email: 'jdoe@example.com',
            passwordHash: $this->passwordHasher->hash('secret'),
            confirmedAt: time(),
        );

        $response = $this->mockRedirectResponse($this->responseFactory, '//app/dashboard');

        $controller = $this->createController([VoytiConfig::class => $config]);
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['login' => ['login' => 'jdoe', 'password' => 'secret']]);

        $result = $controller->login($request);

        $this->assertSame($response, $result);
    }

    public function testLoginPostSuccessRedirectsToHomeRouteByDefault(): void
    {
        $this->currentUser->method('getIdentity')->willReturn($this->createMock(GuestIdentityInterface::class));

        $this->createUser(
            username: 'jdoe',
            email: 'jdoe@example.com',
            passwordHash: $this->passwordHasher->hash('secret'),
            confirmedAt: time(),
        );

        $response = $this->mockRedirectResponse($this->responseFactory, '//home');

        $controller = $this->createController();
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['login' => ['login' => 'jdoe', 'password' => 'secret']]);

        $result = $controller->login($request);

        $this->assertSame($response, $result);
    }

    public function testLoginPostSuccessThrowsWhenHomeRouteIsNotRegistered(): void
    {
        $config = VoytiConfigFactory::create(homeRoute: 'nonexistent');
        $container = $this->getTestContainer(array_merge($this->mockOverrides(), [VoytiConfig::class => $config]));
        /** @var FakeUrlGenerator $urlGenerator */
        $urlGenerator = $container->get(UrlGeneratorInterface::class);
        $urlGenerator->setMissingRoute('nonexistent');

        $this->currentUser->method('getIdentity')->willReturn($this->createMock(GuestIdentityInterface::class));

        $this->createUser(
            username: 'jdoe',
            email: 'jdoe@example.com',
            passwordHash: $this->passwordHasher->hash('secret'),
            confirmedAt: time(),
        );

        $controller = $container->get(SessionController::class);
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['login' => ['login' => 'jdoe', 'password' => 'secret']]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('"homeRoute" is set to "nonexistent", but no such route is registered.');

        $controller->login($request);
    }

    public function testLoginPostSuccessWithRememberMeAddsCookie(): void
    {
        $this->currentUser->method('getIdentity')->willReturn($this->createMock(GuestIdentityInterface::class));

        $this->createUser(
            username: 'jdoe',
            email: 'jdoe@example.com',
            passwordHash: $this->passwordHasher->hash('secret'),
            confirmedAt: time(),
        );
        $this->currentUser->method('withAuthTimeout')->willReturnSelf();
        $this->rememberMeCookieService->expects($this->once())->method('addCookie')->willReturnArgument(1);

        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->method('createResponse')->willReturn($response);
        $response->method('withHeader')->willReturnSelf();

        $controller = $this->createController();
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['login' => ['login' => 'jdoe', 'password' => 'secret', 'rememberMe' => true]]);

        $result = $controller->login($request);

        $this->assertSame($response, $result);
    }

    public function testLoginPostWithBlockedUserShowsError(): void
    {
        $this->currentUser->method('getIdentity')->willReturn($this->createMock(GuestIdentityInterface::class));

        $this->createUser(
            username: 'jdoe',
            email: 'jdoe@example.com',
            passwordHash: $this->passwordHasher->hash('secret'),
            confirmedAt: time(),
            blockedAt: time(),
        );

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->method('withViewPath')->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('session/login', $this->anything())
            ->willReturn($response);

        $controller = $this->createController();
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['login' => ['login' => 'jdoe', 'password' => 'secret']]);

        $result = $controller->login($request);

        $this->assertSame($response, $result);
    }

    public function testLoginPostWithInvalidCredentialsShowsError(): void
    {
        $this->currentUser->method('getIdentity')->willReturn($this->createMock(GuestIdentityInterface::class));

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->method('withViewPath')->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('session/login', $this->anything())
            ->willReturn($response);

        $controller = $this->createController();
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['login' => ['login' => 'jdoe', 'password' => 'wrong']]);

        $result = $controller->login($request);

        $this->assertSame($response, $result);
    }

    public function testLoginPostWithTwoFactorEmailMethodSendsCodeAndShowsConfirm(): void
    {
        $config = VoytiConfigFactory::create(enableTwoFactorAuthentication: true);

        $this->currentUser->method('getIdentity')->willReturn($this->createMock(GuestIdentityInterface::class));

        $user = $this->createUser(
            username: 'jdoe',
            email: 'jdoe@example.com',
            passwordHash: $this->passwordHasher->hash('secret'),
            confirmedAt: time(),
            authTfEnabled: true,
            authTfType: 'email',
        );

        $this->twoFactorEmailCodeService->expects($this->once())->method('run')->with(
            $this->callback(static fn(User $u): bool => $u->getId() === $user->getId()),
        );

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->method('withViewPath')->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('session/confirm', $this->callback(
                static fn(array $params): bool => $params['data']->method === 'email',
            ))
            ->willReturn($response);

        $controller = $this->createController([VoytiConfig::class => $config]);
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['login' => ['login' => 'jdoe', 'password' => 'secret']]);

        $result = $controller->login($request);

        $this->assertSame($response, $result);
    }

    public function testLoginPostWithTwoFactorGoogleMethodShowsConfirmWithoutSendingCode(): void
    {
        $config = VoytiConfigFactory::create(enableTwoFactorAuthentication: true);

        $this->currentUser->method('getIdentity')->willReturn($this->createMock(GuestIdentityInterface::class));

        $this->createUser(
            username: 'jdoe',
            email: 'jdoe@example.com',
            passwordHash: $this->passwordHasher->hash('secret'),
            confirmedAt: time(),
            authTfEnabled: true,
            authTfType: 'google',
        );

        $this->twoFactorEmailCodeService->expects($this->never())->method('run');

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->method('withViewPath')->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('session/confirm', $this->callback(
                static fn(array $params): bool => $params['data']->method === 'google',
            ))
            ->willReturn($response);

        $controller = $this->createController([VoytiConfig::class => $config]);
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['login' => ['login' => 'jdoe', 'password' => 'secret']]);

        $result = $controller->login($request);

        $this->assertSame($response, $result);
    }

    public function testLoginPostWithUnconfirmedEmailShowsError(): void
    {
        $this->currentUser->method('getIdentity')->willReturn($this->createMock(GuestIdentityInterface::class));

        $this->createUser(
            username: 'jdoe',
            email: 'jdoe@example.com',
            passwordHash: $this->passwordHasher->hash('secret'),
        );

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->method('withViewPath')->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('session/login', $this->anything())
            ->willReturn($response);

        $controller = $this->createController();
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['login' => ['login' => 'jdoe', 'password' => 'secret']]);

        $result = $controller->login($request);

        $this->assertSame($response, $result);
    }

    public function testLoginWhenAlreadyAuthenticatedRedirectsToHome(): void
    {
        $identity = $this->createMock(User::class);
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $response = $this->mockRedirectResponse($this->responseFactory, '//home');

        $this->viewRenderer->expects($this->never())->method('render');

        $controller = $this->createController();
        $request = new ServerRequest('GET', '/');

        $result = $controller->login($request);

        $this->assertSame($response, $result);
    }

    public function testLogoutRedirectsToHomeRouteByDefault(): void
    {
        $this->currentUser->method('logout')->willReturn(false);
        $this->currentUser->method('getIdentity')->willReturn($this->createMock(GuestIdentityInterface::class));
        $this->rememberMeCookieService->method('expireCookie')->willReturnArgument(0);

        $response = $this->mockRedirectResponse($this->responseFactory, '//home');

        $controller = $this->createController();

        $result = $controller->logout();

        $this->assertSame($response, $result);
    }

    public function testLogoutRevokesUserSessionRecord(): void
    {
        $user = $this->createRealUser();
        $sessionId = 'test-session-to-revoke';

        $userSession = new UserSessions();
        $userSession->setUserId($user->getIdOrZero());
        $userSession->setSessionId($sessionId);
        $userSession->setIp('192.168.1.1');
        $userSession->setCreatedAt(time());
        $userSession->setUpdatedAt(time());
        $userSession->save();

        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn((string) $user->getId());
        $identity->method('getIdOrZero')->willReturn($user->getIdOrZero());
        $identity->expects($this->once())->method('setAuthKey');
        $identity->expects($this->once())->method('setUpdatedAt');
        $identity->expects($this->once())->method('save');

        $this->currentUser->method('logout')->willReturn(true);
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $container = $this->getTestContainer($this->mockOverrides());
        $session = $container->get(SessionInterface::class);
        $session->open();
        $session->setId($sessionId);

        $this->rememberMeCookieService->method('expireCookie')->willReturnArgument(0);

        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->method('createResponse')->willReturn($response);
        $response->method('withHeader')->willReturnSelf();

        $controller = $container->get(SessionController::class);
        $controller->logout();

        $revoked = UserSessions::findByUserIdAndSessionId($user->getIdOrZero(), $sessionId);
        $this->assertNotNull($revoked);
        $this->assertTrue($revoked->isRevoked());
    }

    public function testLogoutWhenLoggedInRotatesAuthKeyAndSaves(): void
    {
        $identity = $this->createMock(User::class);
        $identity->expects($this->once())->method('setAuthKey');
        $identity->expects($this->once())->method('setUpdatedAt');
        $identity->expects($this->once())->method('save');

        $this->currentUser->method('logout')->willReturn(true);
        $this->currentUser->method('getIdentity')->willReturn($identity);
        $this->rememberMeCookieService->method('expireCookie')->willReturnArgument(0);

        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->method('createResponse')->willReturn($response);
        $response->method('withHeader')->willReturnSelf();

        $controller = $this->createController();

        $result = $controller->logout();

        $this->assertSame($response, $result);
    }

    private function createController(array $extraOverrides = []): SessionController
    {
        return $this->getTestContainer(
            array_merge($this->mockOverrides(), $extraOverrides),
        )->get(SessionController::class);
    }

    private function createRealUser(): User
    {
        $user = new User();
        $user->setUsername('realuser');
        $user->setEmail('realuser@example.com');
        $user->setPasswordHash('hash');
        $user->setAuthKey('key');
        $user->setCreatedAt(time());
        $user->setUpdatedAt(time());
        $user->save();

        return $user;
    }

    private function mockOverrides(): array
    {
        return [
            CurrentUser::class => $this->currentUser,
            EmailCodeGeneratorService::class => $this->twoFactorEmailCodeService,
            FlashInterface::class => $this->flash,
            PasswordHasher::class => $this->passwordHasher,
            PendingSocialAccountService::class => $this->pendingSocialAccountService,
            RememberMeCookieService::class => $this->rememberMeCookieService,
            ResponseFactoryInterface::class => $this->responseFactory,
            ValidatorInterface::class => $this->validator,
            WebViewRenderer::class => $this->viewRenderer,
        ];
    }
}
