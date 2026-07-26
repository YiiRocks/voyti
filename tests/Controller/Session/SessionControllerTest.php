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
use YiiRocks\Voyti\Model\Form\Auth\LoginForm;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Model\UserSessions;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Service\Auth\PendingSocialAccountService;
use YiiRocks\Voyti\Service\RememberMeCookieService;
use YiiRocks\Voyti\Service\TwoFactor\EmailCodeGeneratorService;
use YiiRocks\Voyti\tests\Support\ControllerHarness;
use YiiRocks\Voyti\tests\Support\DatabaseSetupTrait;
use YiiRocks\Voyti\tests\Support\HydrateObjectTrait;
use YiiRocks\Voyti\tests\Support\ModuleConfigFactory;
use YiiRocks\Voyti\tests\Support\RedirectResponseMockTrait;
use YiiRocks\Voyti\tests\Support\TestPasswordHasherFactory;
use YiiRocks\Voyti\tests\Support\UserFactoryTrait;
use YiiRocks\Voyti\tests\TestCase;
use Yiisoft\Hydrator\HydratorInterface;
use Yiisoft\Security\PasswordHasher;
use Yiisoft\Session\Flash\FlashInterface;
use Yiisoft\User\CurrentUser;
use Yiisoft\User\Guest\GuestIdentityInterface;
use Yiisoft\Validator\Result;
use Yiisoft\Validator\ValidatorInterface;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

#[AllowMockObjectsWithoutExpectations]
final class SessionControllerTest extends TestCase
{
    use DatabaseSetupTrait;
    use HydrateObjectTrait;
    use RedirectResponseMockTrait;
    use UserFactoryTrait;

    private ModuleConfig $config;
    private CurrentUser&MockObject $currentUser;
    private FlashInterface&MockObject $flash;
    private ControllerHarness $harness;
    private HydratorInterface&MockObject $hydrator;
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
        $this->config = ModuleConfigFactory::create();
        $this->harness = new ControllerHarness($this->config);
        $this->viewRenderer = $this->createMock(WebViewRenderer::class);
        $this->viewRenderer->method('withAddedInjections')->willReturnSelf();
        $this->validator = $this->createMock(ValidatorInterface::class);
        $this->currentUser = $this->createMock(CurrentUser::class);
        $this->responseFactory = $this->createMock(ResponseFactoryInterface::class);
        $this->hydrator = $this->createMock(HydratorInterface::class);
        $this->flash = $this->createMock(FlashInterface::class);
        $this->passwordHasher = TestPasswordHasherFactory::create();
        $this->rememberMeCookieService = $this->createMock(RememberMeCookieService::class);
        $this->pendingSocialAccountService = $this->createMock(PendingSocialAccountService::class);
        $this->twoFactorEmailCodeService = $this->createMock(EmailCodeGeneratorService::class);

        $this->hydrator->method('hydrate')->willReturnCallback(
            function (object $object, array $data = []): void {
                $this->hydrateObject($object, $data);
            },
        );
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
        $this->harness->getSession()->set('credentials', [
            'login' => 'jdoe',
            'pwd' => 'secret',
            'rememberMe' => false,
        ]);

        $capturedForm = null;
        $this->validator->method('validate')->willReturnCallback(
            function (object $model) use (&$capturedForm): Result {
                $capturedForm = $model;
                return new Result();
            },
        );

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->method('withViewPath')->willReturnSelf();
        $this->viewRenderer->method('render')->willReturn($response);

        $controller = $this->createController();
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['login' => ['twoFactorAuthenticationCode' => '']]);

        $result = $controller->confirm($request, ['twoFactorAuthenticationCode' => '']);

        $this->assertSame($response, $result);
        $this->assertInstanceOf(LoginForm::class, $capturedForm);
        $this->assertArrayHasKey('twoFactorAuthenticationCode', $capturedForm->getRules());
    }

    public function testConfirmPostSuccessRedirectsToConfiguredRoute(): void
    {
        $config = ModuleConfigFactory::create(homeRoute: 'app/dashboard');
        $this->harness = new ControllerHarness($config);

        $this->harness->getSession()->set('credentials', [
            'login' => 'jdoe',
            'pwd' => 'secret',
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

        $controller = $this->createController();
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['login' => ['twoFactorAuthenticationCode' => '123456']]);

        $result = $controller->confirm($request, ['twoFactorAuthenticationCode' => '123456']);

        $this->assertSame($response, $result);
    }

    public function testConfirmPostSuccessWithRememberMeAddsCookie(): void
    {
        $this->harness->getSession()->set('credentials', [
            'login' => 'jdoe',
            'pwd' => 'secret',
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

        $controller = $this->createController();
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['login' => ['twoFactorAuthenticationCode' => '123456']]);

        $result = $controller->confirm($request, ['twoFactorAuthenticationCode' => '123456']);

        $this->assertSame($response, $result);
    }

    public function testConfirmPostWithGoogleMethodAndInvalidCodeShowsError(): void
    {
        $this->harness->getSession()->set('credentials', [
            'login' => 'jdoe',
            'pwd' => 'secret',
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

        $controller = $this->createController();
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['login' => ['twoFactorAuthenticationCode' => 'wrong']]);

        $result = $controller->confirm($request, ['twoFactorAuthenticationCode' => 'wrong']);

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

    public function testLoginGetShowsFormWithoutSocialButtonsWhenAuthClientPackageIsUnavailable(): void
    {
        $this->currentUser->method('getIdentity')->willReturn($this->createMock(GuestIdentityInterface::class));

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->method('withViewPath')->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('session/login', $this->callback(
                static fn(array $params): bool => $params['data']->connect->providers === [],
            ))
            ->willReturn($response);

        $controller = $this->harness->createSessionController(
            translator: $this->getTranslator(),
            viewRenderer: $this->viewRenderer,
            validator: $this->validator,
            currentUser: $this->currentUser,
            responseFactory: $this->responseFactory,
            hydrator: $this->hydrator,
            flash: $this->flash,
            passwordHasher: $this->passwordHasher,
            rememberMeCookieService: $this->rememberMeCookieService,
            pendingSocialAccountService: $this->pendingSocialAccountService,
            twoFactorEmailCodeService: $this->twoFactorEmailCodeService,
            withSocialAuthClient: false,
        );
        $request = new ServerRequest('GET', '/');

        $result = $controller->login($request);

        $this->assertSame($response, $result);
    }

    public function testLoginPostSuccessRedirectsToConfiguredRoute(): void
    {
        $config = ModuleConfigFactory::create(homeRoute: 'app/dashboard');
        $this->harness = new ControllerHarness($config);

        $this->currentUser->method('getIdentity')->willReturn($this->createMock(GuestIdentityInterface::class));

        $this->createUser(
            username: 'jdoe',
            email: 'jdoe@example.com',
            passwordHash: $this->passwordHasher->hash('secret'),
            confirmedAt: time(),
        );
        $this->validator->method('validate')->willReturn(new Result());

        $response = $this->mockRedirectResponse($this->responseFactory, '//app/dashboard');

        $controller = $this->createController();
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['login' => ['login' => 'jdoe', 'password' => 'secret']]);

        $result = $controller->login($request, ['login' => 'jdoe', 'password' => 'secret']);

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
        $this->validator->method('validate')->willReturn(new Result());

        $response = $this->mockRedirectResponse($this->responseFactory, '//home');

        $controller = $this->createController();
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['login' => ['login' => 'jdoe', 'password' => 'secret']]);

        $result = $controller->login($request, ['login' => 'jdoe', 'password' => 'secret']);

        $this->assertSame($response, $result);
    }

    public function testLoginPostSuccessThrowsWhenHomeRouteIsNotRegistered(): void
    {
        $config = ModuleConfigFactory::create(homeRoute: 'nonexistent');
        $this->harness = new ControllerHarness($config);
        $this->harness->getUrlGenerator()->setMissingRoute('nonexistent');

        $this->currentUser->method('getIdentity')->willReturn($this->createMock(GuestIdentityInterface::class));

        $this->createUser(
            username: 'jdoe',
            email: 'jdoe@example.com',
            passwordHash: $this->passwordHasher->hash('secret'),
            confirmedAt: time(),
        );
        $this->validator->method('validate')->willReturn(new Result());

        $controller = $this->createController();
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['login' => ['login' => 'jdoe', 'password' => 'secret']]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('"homeRoute" is set to "nonexistent", but no such route is registered.');

        $controller->login($request, ['login' => 'jdoe', 'password' => 'secret']);
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
        $this->validator->method('validate')->willReturn(new Result());
        $this->currentUser->method('withAuthTimeout')->willReturnSelf();
        $this->rememberMeCookieService->expects($this->once())->method('addCookie')->willReturnArgument(1);

        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->method('createResponse')->willReturn($response);
        $response->method('withHeader')->willReturnSelf();

        $controller = $this->createController();
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['login' => ['login' => 'jdoe', 'password' => 'secret', 'rememberMe' => true]]);

        $result = $controller->login($request, ['login' => 'jdoe', 'password' => 'secret', 'rememberMe' => true]);

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
        $this->validator->method('validate')->willReturn(new Result());

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->method('withViewPath')->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('session/login', $this->anything())
            ->willReturn($response);

        $controller = $this->createController();
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['login' => ['login' => 'jdoe', 'password' => 'secret']]);

        $result = $controller->login($request, ['login' => 'jdoe', 'password' => 'secret']);

        $this->assertSame($response, $result);
    }

    public function testLoginPostWithInvalidCredentialsShowsError(): void
    {
        $this->currentUser->method('getIdentity')->willReturn($this->createMock(GuestIdentityInterface::class));

        $this->validator->method('validate')->willReturn(new Result());

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->method('withViewPath')->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('session/login', $this->anything())
            ->willReturn($response);

        $controller = $this->createController();
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['login' => ['login' => 'jdoe', 'password' => 'wrong']]);

        $result = $controller->login($request, ['login' => 'jdoe', 'password' => 'wrong']);

        $this->assertSame($response, $result);
    }

    public function testLoginPostWithTwoFactorEmailMethodSendsCodeAndShowsConfirm(): void
    {
        $config = ModuleConfigFactory::create(enableTwoFactorAuthentication: true);
        $this->harness = new ControllerHarness($config);

        $this->currentUser->method('getIdentity')->willReturn($this->createMock(GuestIdentityInterface::class));

        $user = $this->createUser(
            username: 'jdoe',
            email: 'jdoe@example.com',
            passwordHash: $this->passwordHasher->hash('secret'),
            confirmedAt: time(),
            authTfEnabled: true,
            authTfType: 'email',
        );

        $this->validator->method('validate')->willReturn(new Result());
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

        $controller = $this->createController();
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['login' => ['login' => 'jdoe', 'password' => 'secret']]);

        $result = $controller->login($request, ['login' => 'jdoe', 'password' => 'secret']);

        $this->assertSame($response, $result);
    }

    public function testLoginPostWithTwoFactorGoogleMethodShowsConfirmWithoutSendingCode(): void
    {
        $config = ModuleConfigFactory::create(enableTwoFactorAuthentication: true);
        $this->harness = new ControllerHarness($config);

        $this->currentUser->method('getIdentity')->willReturn($this->createMock(GuestIdentityInterface::class));

        $this->createUser(
            username: 'jdoe',
            email: 'jdoe@example.com',
            passwordHash: $this->passwordHasher->hash('secret'),
            confirmedAt: time(),
            authTfEnabled: true,
            authTfType: 'google',
        );

        $this->validator->method('validate')->willReturn(new Result());
        $this->twoFactorEmailCodeService->expects($this->never())->method('run');

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->method('withViewPath')->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('session/confirm', $this->callback(
                static fn(array $params): bool => $params['data']->method === 'google',
            ))
            ->willReturn($response);

        $controller = $this->createController();
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['login' => ['login' => 'jdoe', 'password' => 'secret']]);

        $result = $controller->login($request, ['login' => 'jdoe', 'password' => 'secret']);

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
        $this->validator->method('validate')->willReturn(new Result());

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->method('withViewPath')->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('session/login', $this->anything())
            ->willReturn($response);

        $controller = $this->createController();
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['login' => ['login' => 'jdoe', 'password' => 'secret']]);

        $result = $controller->login($request, ['login' => 'jdoe', 'password' => 'secret']);

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

        $this->harness->getSession()->open();
        $this->harness->getSession()->setId($sessionId);

        $this->rememberMeCookieService->method('expireCookie')->willReturnArgument(0);

        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->method('createResponse')->willReturn($response);
        $response->method('withHeader')->willReturnSelf();

        $controller = $this->createController();
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

    private function createController(): SessionController
    {
        return $this->harness->createSessionController(
            translator: $this->getTranslator(),
            viewRenderer: $this->viewRenderer,
            validator: $this->validator,
            currentUser: $this->currentUser,
            responseFactory: $this->responseFactory,
            hydrator: $this->hydrator,
            flash: $this->flash,
            passwordHasher: $this->passwordHasher,
            rememberMeCookieService: $this->rememberMeCookieService,
            pendingSocialAccountService: $this->pendingSocialAccountService,
            twoFactorEmailCodeService: $this->twoFactorEmailCodeService,
        );
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
}
