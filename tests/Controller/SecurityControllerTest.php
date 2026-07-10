<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Controller;

use LogicException;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use YiiRocks\Voyti\Controller\SecurityController;
use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\Entity\UserSocialAccount;
use YiiRocks\Voyti\Form\Auth\LoginForm;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Repository\UserRepository;
use YiiRocks\Voyti\Service\Auth\PendingSocialAccountService;
use YiiRocks\Voyti\Service\Auth\SocialAuthProviderService;
use YiiRocks\Voyti\Service\Auth\UserSocialAccountConnectService;
use YiiRocks\Voyti\Service\Auth\UserSocialAuthenticateService;
use YiiRocks\Voyti\Service\RememberMeCookieService;
use YiiRocks\Voyti\Service\ServiceResult;
use YiiRocks\Voyti\Service\TwoFactor\EmailCodeGeneratorService;
use YiiRocks\Voyti\tests\Support\ControllerHarness;
use YiiRocks\Voyti\tests\TestCase;
use Yiisoft\Hydrator\HydratorInterface;
use Yiisoft\Security\PasswordHasher;
use Yiisoft\Session\Flash\FlashInterface;
use Yiisoft\User\CurrentUser;
use Yiisoft\User\Guest\GuestIdentityInterface;
use Yiisoft\Validator\Result;
use Yiisoft\Validator\ValidatorInterface;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
final class SecurityControllerTest extends TestCase
{
    private ModuleConfig $config;
    private CurrentUser&MockObject $currentUser;
    private FlashInterface&MockObject $flash;
    private ControllerHarness $harness;
    private HydratorInterface&MockObject $hydrator;
    private PasswordHasher $passwordHasher;
    private PendingSocialAccountService&MockObject $pendingSocialAccountService;
    private RememberMeCookieService&MockObject $rememberMeCookieService;
    private ResponseFactoryInterface&MockObject $responseFactory;
    private SocialAuthProviderService&MockObject $socialAuthProviderService;
    private UserSocialAccountConnectService&MockObject $socialNetworkAccountConnectService;
    private UserSocialAuthenticateService&MockObject $socialNetworkAuthenticateService;
    private EmailCodeGeneratorService&MockObject $twoFactorEmailCodeService;
    private UserRepository&MockObject $userRepository;
    private ValidatorInterface&MockObject $validator;
    private WebViewRenderer&MockObject $viewRenderer;

    protected function setUp(): void
    {
        $this->config = new ModuleConfig();
        $this->harness = new ControllerHarness($this->config);
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->viewRenderer = $this->createMock(WebViewRenderer::class);
        $this->validator = $this->createMock(ValidatorInterface::class);
        $this->currentUser = $this->createMock(CurrentUser::class);
        $this->responseFactory = $this->createMock(ResponseFactoryInterface::class);
        $this->hydrator = $this->createMock(HydratorInterface::class);
        $this->flash = $this->createMock(FlashInterface::class);
        $this->passwordHasher = new PasswordHasher();
        $this->rememberMeCookieService = $this->createMock(RememberMeCookieService::class);
        $this->socialAuthProviderService = $this->createMock(SocialAuthProviderService::class);
        $this->pendingSocialAccountService = $this->createMock(PendingSocialAccountService::class);
        $this->socialNetworkAuthenticateService = $this->createMock(UserSocialAuthenticateService::class);
        $this->socialNetworkAccountConnectService = $this->createMock(UserSocialAccountConnectService::class);
        $this->twoFactorEmailCodeService = $this->createMock(EmailCodeGeneratorService::class);

        $this->hydrator->method('hydrate')->willReturnCallback(
            function (object $object, array $data = []): void {
                $this->hydrateObject($object, $data);
            },
        );
    }

    public function testAuthBeginRedirectsToProvider(): void
    {
        $controller = $this->createController();
        $request = new ServerRequest('GET', '/auth/github');

        $this->socialAuthProviderService->method('hasCallbackParameters')->willReturn(false);
        $this->socialAuthProviderService->expects($this->once())->method('begin')->with('github', 'voyti/auth')->willReturn('https://github.com/authorize');

        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->expects($this->once())
            ->method('createResponse')
            ->with(302)
            ->willReturn($response);
        $response->expects($this->once())
            ->method('withHeader')
            ->with('Location', 'https://github.com/authorize')
            ->willReturnSelf();

        $result = $controller->auth($request, 'github');

        $this->assertSame($response, $result);
    }

    public function testAuthCatchesRuntimeExceptionShowsMessage(): void
    {
        $controller = $this->createController();
        $request = new ServerRequest('GET', '/auth/github');

        $this->socialAuthProviderService->method('hasCallbackParameters')->willReturn(true);
        $this->socialAuthProviderService->method('complete')->willThrowException(new RuntimeException('state mismatch'));

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->method('withViewPath')->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('shared/message', $this->callback(
                static fn (array $params): bool => $params['title'] === 'state mismatch',
            ))
            ->willReturn($response);

        $result = $controller->auth($request, 'github');

        $this->assertSame($response, $result);
    }

    public function testAuthFailureShowsMessage(): void
    {
        $controller = $this->createController();
        $request = new ServerRequest('GET', '/auth/github');

        $this->socialAuthProviderService->method('hasCallbackParameters')->willReturn(true);
        $this->socialAuthProviderService->method('complete')->willReturn(['id' => 'client123']);
        $this->socialNetworkAuthenticateService->method('run')->willReturn(ServiceResult::failure('could not authenticate'));

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->method('withViewPath')->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('shared/message', $this->callback(
                static fn (array $params): bool => $params['title'] === 'could not authenticate',
            ))
            ->willReturn($response);

        $result = $controller->auth($request, 'github');

        $this->assertSame($response, $result);
    }

    public function testAuthSuccessRedirectsToHomeRoute(): void
    {
        $controller = $this->createController();
        $request = new ServerRequest('GET', '/auth/github');

        $this->socialAuthProviderService->method('hasCallbackParameters')->willReturn(true);
        $this->socialAuthProviderService->method('complete')->willReturn(['id' => 'client123']);
        $this->socialNetworkAuthenticateService->method('run')->willReturn(ServiceResult::success());
        $this->pendingSocialAccountService->method('getPendingAccount')->willReturn(null);

        $user = $this->createMock(User::class);
        $this->currentUser->method('getIdentity')->willReturn($user);
        $this->rememberMeCookieService->method('addCookie')->willReturnArgument(1);

        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->expects($this->once())
            ->method('createResponse')
            ->with(302)
            ->willReturn($response);
        $response->expects($this->once())
            ->method('withHeader')
            ->with('Location', '//home')
            ->willReturnSelf();

        $result = $controller->auth($request, 'github');

        $this->assertSame($response, $result);
    }

    public function testAuthWithoutCurrentUserIdentityShowsAuthenticatedMessage(): void
    {
        $controller = $this->createController();
        $request = new ServerRequest('GET', '/auth/github');

        $this->socialAuthProviderService->method('hasCallbackParameters')->willReturn(true);
        $this->socialAuthProviderService->method('complete')->willReturn(['id' => 'client123']);
        $this->socialNetworkAuthenticateService->method('run')->willReturn(ServiceResult::success());
        $this->pendingSocialAccountService->method('getPendingAccount')->willReturn(null);
        $this->currentUser->method('getIdentity')->willReturn($this->createMock(GuestIdentityInterface::class));

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->method('withViewPath')->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('shared/message', $this->callback(
                static fn (array $params): bool => $params['title'] === 'Authenticated',
            ))
            ->willReturn($response);

        $result = $controller->auth($request, 'github');

        $this->assertSame($response, $result);
    }

    public function testAuthWithPendingAccountRedirectsToConnect(): void
    {
        $controller = $this->createController();
        $request = new ServerRequest('GET', '/auth/github');

        $this->socialAuthProviderService->method('hasCallbackParameters')->willReturn(true);
        $this->socialAuthProviderService->method('complete')->willReturn(['id' => 'client123']);
        $this->socialNetworkAuthenticateService->method('run')->willReturn(ServiceResult::success());

        $account = $this->createMock(UserSocialAccount::class);
        $account->method('getCode')->willReturn('pending-code');
        $this->pendingSocialAccountService->method('getPendingAccount')->willReturn($account);

        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->expects($this->once())
            ->method('createResponse')
            ->with(302)
            ->willReturn($response);
        $response->expects($this->once())
            ->method('withHeader')
            ->with('Location', '//voyti/registration-connect?code=pending-code')
            ->willReturnSelf();

        $result = $controller->auth($request, 'github');

        $this->assertSame($response, $result);
    }

    public function testConfirmGetWithNoCredentialsShowsLoginForm(): void
    {
        $controller = $this->createController();
        $request = new ServerRequest('GET', '/');

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->method('withViewPath')->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('security/login', $this->anything())
            ->willReturn($response);

        $result = $controller->confirm($request);

        $this->assertSame($response, $result);
    }

    public function testConfirmPostConstructsFormRequiringTwoFactorCode(): void
    {
        $controller = $this->createController();

        $this->harness->getSession()->set('credentials', [
            'login' => 'jdoe',
            'pwd' => 'secret',
            'rememberMe' => false,
        ]);

        $request = (new ServerRequest('POST', '/'))->withParsedBody(['login' => ['twoFactorAuthenticationCode' => '']]);

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

        $result = $controller->confirm($request);

        $this->assertSame($response, $result);
        $this->assertInstanceOf(LoginForm::class, $capturedForm);
        $this->assertArrayHasKey('twoFactorAuthenticationCode', $capturedForm->getRules());
    }

    public function testConfirmPostSuccessRedirectsToConfiguredRoute(): void
    {
        $config = new ModuleConfig(homeRoute: 'app/dashboard');
        $this->harness = new ControllerHarness($config);
        $controller = $this->createController();

        $this->harness->getSession()->set('credentials', [
            'login' => 'jdoe',
            'pwd' => 'secret',
            'rememberMe' => false,
        ]);

        $request = (new ServerRequest('POST', '/'))->withParsedBody(['login' => ['twoFactorAuthenticationCode' => '123456']]);

        $user = $this->createMock(User::class);
        $user->method('getPasswordHash')->willReturn($this->passwordHasher->hash('secret'));
        $user->method('getAuthTfType')->willReturn('email');
        $user->method('getAuthTfKey')->willReturn('123456');

        $this->userRepository->method('findByUsernameOrEmail')->willReturn($user);

        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->expects($this->once())
            ->method('createResponse')
            ->with(302)
            ->willReturn($response);
        $response->expects($this->once())
            ->method('withHeader')
            ->with('Location', '//app/dashboard')
            ->willReturnSelf();

        $result = $controller->confirm($request);

        $this->assertSame($response, $result);
    }

    public function testConfirmPostSuccessWithRememberMeAddsCookie(): void
    {
        $controller = $this->createController();

        $this->harness->getSession()->set('credentials', [
            'login' => 'jdoe',
            'pwd' => 'secret',
            'rememberMe' => true,
        ]);

        $request = (new ServerRequest('POST', '/'))->withParsedBody(['login' => ['twoFactorAuthenticationCode' => '123456']]);

        $user = $this->createMock(User::class);
        $user->method('getPasswordHash')->willReturn($this->passwordHasher->hash('secret'));
        $user->method('getAuthTfType')->willReturn('email');
        $user->method('getAuthTfKey')->willReturn('123456');

        $this->userRepository->method('findByUsernameOrEmail')->willReturn($user);
        $this->rememberMeCookieService->expects($this->once())->method('addCookie')->willReturnArgument(1);

        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->method('createResponse')->willReturn($response);
        $response->method('withHeader')->willReturnSelf();

        $result = $controller->confirm($request);

        $this->assertSame($response, $result);
    }

    public function testConfirmPostWithGoogleMethodAndInvalidCodeShowsError(): void
    {
        $controller = $this->createController();

        $this->harness->getSession()->set('credentials', [
            'login' => 'jdoe',
            'pwd' => 'secret',
            'rememberMe' => false,
        ]);

        $request = (new ServerRequest('POST', '/'))->withParsedBody(['login' => ['twoFactorAuthenticationCode' => 'wrong']]);

        $user = $this->createMock(User::class);
        $user->method('getPasswordHash')->willReturn($this->passwordHasher->hash('secret'));
        $user->method('getAuthTfType')->willReturn('google');
        $user->method('getAuthTfKey')->willReturn(null);

        $this->userRepository->method('findByUsernameOrEmail')->willReturn($user);

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->method('withViewPath')->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('security/confirm', $this->callback(
                static fn (array $params): bool => $params['method'] === 'google',
            ))
            ->willReturn($response);

        $result = $controller->confirm($request);

        $this->assertSame($response, $result);
    }

    public function testConnectBeginRedirectsToProvider(): void
    {
        $controller = $this->createController();
        $request = new ServerRequest('GET', '/connect/github');

        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn('1');
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $this->socialAuthProviderService->method('hasCallbackParameters')->willReturn(false);
        $this->socialAuthProviderService->expects($this->once())->method('begin')->with('github', 'voyti/connect')->willReturn('https://github.com/authorize');

        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->expects($this->once())
            ->method('createResponse')
            ->with(302)
            ->willReturn($response);
        $response->expects($this->once())
            ->method('withHeader')
            ->with('Location', 'https://github.com/authorize')
            ->willReturnSelf();

        $result = $controller->connect($request, 'github');

        $this->assertSame($response, $result);
    }

    public function testConnectCatchesRuntimeExceptionShowsMessage(): void
    {
        $controller = $this->createController();
        $request = new ServerRequest('GET', '/connect/github');

        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn('1');
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $this->socialAuthProviderService->method('hasCallbackParameters')->willReturn(true);
        $this->socialAuthProviderService->method('complete')->willThrowException(new RuntimeException('state mismatch'));

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->method('withViewPath')->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('shared/message', $this->callback(
                static fn (array $params): bool => $params['title'] === 'state mismatch',
            ))
            ->willReturn($response);

        $result = $controller->connect($request, 'github');

        $this->assertSame($response, $result);
    }

    public function testConnectWhenGuestShowsError(): void
    {
        $controller = $this->createController();
        $request = new ServerRequest('GET', '/connect/github');

        $this->currentUser->method('getIdentity')->willReturn($this->createMock(GuestIdentityInterface::class));

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->method('withViewPath')->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->willReturn($response);

        $result = $controller->connect($request, 'github');

        $this->assertSame($response, $result);
    }

    public function testConnectWithFailureResultShowsMessage(): void
    {
        $controller = $this->createController();
        $request = new ServerRequest('GET', '/connect/github');

        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn('1');
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $this->socialAuthProviderService->method('hasCallbackParameters')->willReturn(true);
        $this->socialAuthProviderService->method('complete')->willReturn(['id' => 'client123']);
        $this->socialNetworkAccountConnectService->method('run')->willReturn(ServiceResult::failure('already connected'));

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->method('withViewPath')->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('shared/message', $this->callback(
                static fn (array $params): bool => $params['title'] === 'already connected',
            ))
            ->willReturn($response);

        $result = $controller->connect($request, 'github');

        $this->assertSame($response, $result);
    }

    public function testConnectWithSuccessResultShowsAuthenticatedMessage(): void
    {
        $controller = $this->createController();
        $request = new ServerRequest('GET', '/connect/github');

        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn('1');
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $this->socialAuthProviderService->method('hasCallbackParameters')->willReturn(true);
        $this->socialAuthProviderService->method('complete')->willReturn(['id' => 'client123']);
        $this->socialNetworkAccountConnectService->method('run')->willReturn(ServiceResult::success());

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->method('withViewPath')->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('shared/message', $this->callback(
                static fn (array $params): bool => $params['title'] === 'Authenticated',
            ))
            ->willReturn($response);

        $result = $controller->connect($request, 'github');

        $this->assertSame($response, $result);
    }

    public function testLoginGetShowsForm(): void
    {
        $controller = $this->createController();
        $this->currentUser->method('getIdentity')->willReturn($this->createMock(GuestIdentityInterface::class));
        $request = new ServerRequest('GET', '/');

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->expects($this->once())
            ->method('withViewPath')
            ->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('security/login', $this->arrayHasKey('model'))
            ->willReturn($response);

        $result = $controller->login($request);

        $this->assertSame($response, $result);
    }

    public function testLoginPostSuccessRedirectsToConfiguredRoute(): void
    {
        $config = new ModuleConfig(homeRoute: 'app/dashboard');
        $this->harness = new ControllerHarness($config);
        $controller = $this->createController();
        $this->currentUser->method('getIdentity')->willReturn($this->createMock(GuestIdentityInterface::class));

        $request = (new ServerRequest('POST', '/'))->withParsedBody(['login' => ['login' => 'jdoe', 'password' => 'secret']]);

        $user = $this->createMock(User::class);
        $user->method('getPasswordHash')->willReturn($this->passwordHasher->hash('secret'));
        $user->method('isBlocked')->willReturn(false);
        $user->method('isConfirmed')->willReturn(true);

        $this->userRepository->method('findByUsernameOrEmail')->willReturn($user);
        $this->validator->method('validate')->willReturn(new Result());

        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->expects($this->once())
            ->method('createResponse')
            ->with(302)
            ->willReturn($response);
        $response->expects($this->once())
            ->method('withHeader')
            ->with('Location', '//app/dashboard')
            ->willReturnSelf();

        $result = $controller->login($request);

        $this->assertSame($response, $result);
    }

    public function testLoginPostSuccessRedirectsToHomeRouteByDefault(): void
    {
        $controller = $this->createController();
        $this->currentUser->method('getIdentity')->willReturn($this->createMock(GuestIdentityInterface::class));
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['login' => ['login' => 'jdoe', 'password' => 'secret']]);

        $user = $this->createMock(User::class);
        $user->method('getPasswordHash')->willReturn($this->passwordHasher->hash('secret'));
        $user->method('isBlocked')->willReturn(false);
        $user->method('isConfirmed')->willReturn(true);

        $this->userRepository->method('findByUsernameOrEmail')->willReturn($user);
        $this->validator->method('validate')->willReturn(new Result());

        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->expects($this->once())
            ->method('createResponse')
            ->with(302)
            ->willReturn($response);
        $response->expects($this->once())
            ->method('withHeader')
            ->with('Location', '//home')
            ->willReturnSelf();

        $result = $controller->login($request);

        $this->assertSame($response, $result);
    }

    public function testLoginPostSuccessThrowsWhenHomeRouteIsNotRegistered(): void
    {
        $config = new ModuleConfig(homeRoute: 'nonexistent');
        $this->harness = new ControllerHarness($config);
        $this->harness->getUrlGenerator()->setMissingRoute('nonexistent');
        $controller = $this->createController();
        $this->currentUser->method('getIdentity')->willReturn($this->createMock(GuestIdentityInterface::class));

        $request = (new ServerRequest('POST', '/'))->withParsedBody(['login' => ['login' => 'jdoe', 'password' => 'secret']]);

        $user = $this->createMock(User::class);
        $user->method('getPasswordHash')->willReturn($this->passwordHasher->hash('secret'));
        $user->method('isBlocked')->willReturn(false);
        $user->method('isConfirmed')->willReturn(true);

        $this->userRepository->method('findByUsernameOrEmail')->willReturn($user);
        $this->validator->method('validate')->willReturn(new Result());

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('"homeRoute" is set to "nonexistent", but no such route is registered.');

        $controller->login($request);
    }

    public function testLoginPostSuccessWithRememberMeAddsCookie(): void
    {
        $controller = $this->createController();
        $this->currentUser->method('getIdentity')->willReturn($this->createMock(GuestIdentityInterface::class));
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['login' => ['login' => 'jdoe', 'password' => 'secret', 'rememberMe' => true]]);

        $user = $this->createMock(User::class);
        $user->method('getPasswordHash')->willReturn($this->passwordHasher->hash('secret'));
        $user->method('isBlocked')->willReturn(false);
        $user->method('isConfirmed')->willReturn(true);

        $this->userRepository->method('findByUsernameOrEmail')->willReturn($user);
        $this->validator->method('validate')->willReturn(new Result());
        $this->currentUser->method('withAuthTimeout')->willReturnSelf();
        $this->rememberMeCookieService->expects($this->once())->method('addCookie')->willReturnArgument(1);

        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->method('createResponse')->willReturn($response);
        $response->method('withHeader')->willReturnSelf();

        $result = $controller->login($request);

        $this->assertSame($response, $result);
    }

    public function testLoginPostWithBlockedUserShowsError(): void
    {
        $controller = $this->createController();
        $this->currentUser->method('getIdentity')->willReturn($this->createMock(GuestIdentityInterface::class));
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['login' => ['login' => 'jdoe', 'password' => 'secret']]);

        $user = $this->createMock(User::class);
        $user->method('getPasswordHash')->willReturn($this->passwordHasher->hash('secret'));
        $user->method('isBlocked')->willReturn(true);

        $this->userRepository->method('findByUsernameOrEmail')->willReturn($user);
        $this->validator->method('validate')->willReturn(new Result());

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->method('withViewPath')->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('security/login', $this->anything())
            ->willReturn($response);

        $result = $controller->login($request);

        $this->assertSame($response, $result);
    }

    public function testLoginPostWithInvalidCredentialsShowsError(): void
    {
        $controller = $this->createController();
        $this->currentUser->method('getIdentity')->willReturn($this->createMock(GuestIdentityInterface::class));
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['login' => ['login' => 'jdoe', 'password' => 'wrong']]);

        $this->userRepository->method('findByUsernameOrEmail')->willReturn(null);
        $this->validator->method('validate')->willReturn(new Result());

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->method('withViewPath')->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('security/login', $this->anything())
            ->willReturn($response);

        $result = $controller->login($request);

        $this->assertSame($response, $result);
    }

    public function testLoginPostWithTwoFactorEmailMethodSendsCodeAndShowsConfirm(): void
    {
        $config = new ModuleConfig(enableTwoFactorAuthentication: true);
        $this->harness = new ControllerHarness($config);
        $controller = $this->createController();
        $this->currentUser->method('getIdentity')->willReturn($this->createMock(GuestIdentityInterface::class));
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['login' => ['login' => 'jdoe', 'password' => 'secret']]);

        $user = $this->createMock(User::class);
        $user->method('getPasswordHash')->willReturn($this->passwordHasher->hash('secret'));
        $user->method('isBlocked')->willReturn(false);
        $user->method('isConfirmed')->willReturn(true);
        $user->method('isAuthTfEnabled')->willReturn(true);
        $user->method('getAuthTfType')->willReturn('email');

        $this->userRepository->method('findByUsernameOrEmail')->willReturn($user);
        $this->validator->method('validate')->willReturn(new Result());
        $this->twoFactorEmailCodeService->expects($this->once())->method('run')->with($user);

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->method('withViewPath')->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('security/confirm', $this->callback(
                static fn (array $params): bool => $params['method'] === 'email',
            ))
            ->willReturn($response);

        $result = $controller->login($request);

        $this->assertSame($response, $result);
    }

    public function testLoginPostWithTwoFactorGoogleMethodShowsConfirmWithoutSendingCode(): void
    {
        $config = new ModuleConfig(enableTwoFactorAuthentication: true);
        $this->harness = new ControllerHarness($config);
        $controller = $this->createController();
        $this->currentUser->method('getIdentity')->willReturn($this->createMock(GuestIdentityInterface::class));
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['login' => ['login' => 'jdoe', 'password' => 'secret']]);

        $user = $this->createMock(User::class);
        $user->method('getPasswordHash')->willReturn($this->passwordHasher->hash('secret'));
        $user->method('isBlocked')->willReturn(false);
        $user->method('isConfirmed')->willReturn(true);
        $user->method('isAuthTfEnabled')->willReturn(true);
        $user->method('getAuthTfType')->willReturn('google');

        $this->userRepository->method('findByUsernameOrEmail')->willReturn($user);
        $this->validator->method('validate')->willReturn(new Result());
        $this->twoFactorEmailCodeService->expects($this->never())->method('run');

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->method('withViewPath')->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('security/confirm', $this->callback(
                static fn (array $params): bool => $params['method'] === 'google',
            ))
            ->willReturn($response);

        $result = $controller->login($request);

        $this->assertSame($response, $result);
    }

    public function testLoginPostWithUnconfirmedEmailShowsError(): void
    {
        $controller = $this->createController();
        $this->currentUser->method('getIdentity')->willReturn($this->createMock(GuestIdentityInterface::class));
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['login' => ['login' => 'jdoe', 'password' => 'secret']]);

        $user = $this->createMock(User::class);
        $user->method('getPasswordHash')->willReturn($this->passwordHasher->hash('secret'));
        $user->method('isBlocked')->willReturn(false);
        $user->method('isConfirmed')->willReturn(false);

        $this->userRepository->method('findByUsernameOrEmail')->willReturn($user);
        $this->validator->method('validate')->willReturn(new Result());

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->method('withViewPath')->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('security/login', $this->anything())
            ->willReturn($response);

        $result = $controller->login($request);

        $this->assertSame($response, $result);
    }

    public function testLoginWhenAlreadyAuthenticatedRedirectsToHome(): void
    {
        $controller = $this->createController();
        $identity = $this->createMock(User::class);
        $this->currentUser->method('getIdentity')->willReturn($identity);
        $request = new ServerRequest('GET', '/');

        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->expects($this->once())
            ->method('createResponse')
            ->with(302)
            ->willReturn($response);
        $response->expects($this->once())
            ->method('withHeader')
            ->with('Location', '//home')
            ->willReturnSelf();

        $this->userRepository->expects($this->never())->method('findByUsernameOrEmail');
        $this->viewRenderer->expects($this->never())->method('render');

        $result = $controller->login($request);

        $this->assertSame($response, $result);
    }

    public function testLogoutRedirectsToHomeRouteByDefault(): void
    {
        $controller = $this->createController();

        $this->currentUser->method('logout')->willReturn(false);
        $this->currentUser->method('getIdentity')->willReturn($this->createMock(GuestIdentityInterface::class));
        $this->rememberMeCookieService->method('expireCookie')->willReturnArgument(0);

        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->expects($this->once())
            ->method('createResponse')
            ->with(302)
            ->willReturn($response);
        $response->expects($this->once())
            ->method('withHeader')
            ->with('Location', '//home')
            ->willReturnSelf();

        $result = $controller->logout();

        $this->assertSame($response, $result);
    }

    public function testLogoutWhenLoggedInRotatesAuthKeyAndSaves(): void
    {
        $controller = $this->createController();

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

        $result = $controller->logout();

        $this->assertSame($response, $result);
    }

    private function createController(): SecurityController
    {
        return $this->harness->createSecurityController(
            userRepository: $this->userRepository,
            translator: $this->getTranslator(),
            viewRenderer: $this->viewRenderer,
            validator: $this->validator,
            currentUser: $this->currentUser,
            responseFactory: $this->responseFactory,
            hydrator: $this->hydrator,
            flash: $this->flash,
            passwordHasher: $this->passwordHasher,
            rememberMeCookieService: $this->rememberMeCookieService,
            socialAuthProviderService: $this->socialAuthProviderService,
            pendingSocialAccountService: $this->pendingSocialAccountService,
            socialNetworkAuthenticateService: $this->socialNetworkAuthenticateService,
            socialNetworkAccountConnectService: $this->socialNetworkAccountConnectService,
            twoFactorEmailCodeService: $this->twoFactorEmailCodeService,
        );
    }

    private function hydrateObject(object $object, array $data): void
    {
        foreach ($data as $key => $value) {
            if (property_exists($object, $key)) {
                $object->$key = $value;
            }
        }
    }
}
