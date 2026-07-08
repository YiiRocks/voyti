<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Controller;

use LogicException;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use YiiRocks\Voyti\Controller\SecurityController;
use YiiRocks\Voyti\Entity\User;
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

    public function testLoginGetShowsForm(): void
    {
        $controller = $this->createController();
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
