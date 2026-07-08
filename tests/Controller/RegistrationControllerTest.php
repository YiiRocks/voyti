<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Controller;

use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use YiiRocks\Voyti\Controller\RegistrationController;
use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Repository\UserRepository;
use YiiRocks\Voyti\Repository\UserTokenRepository;
use YiiRocks\Voyti\Service\Auth\PendingSocialAccountService;
use YiiRocks\Voyti\Service\ServiceResult;
use YiiRocks\Voyti\Service\User\AccountConfirmationService;
use YiiRocks\Voyti\Service\User\ConfirmationService;
use YiiRocks\Voyti\Service\User\RegisterService;
use YiiRocks\Voyti\Service\User\ResendConfirmationService;
use YiiRocks\Voyti\tests\Support\ControllerHarness;
use YiiRocks\Voyti\tests\TestCase;
use Yiisoft\Hydrator\HydratorInterface;
use Yiisoft\Session\Flash\FlashInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\Validator\Result;
use Yiisoft\Validator\ValidatorInterface;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
final class RegistrationControllerTest extends TestCase
{
    private AccountConfirmationService&MockObject $accountConfirmationService;
    private ModuleConfig $config;
    private ConfirmationService&MockObject $confirmationService;
    private FlashInterface&MockObject $flash;
    private ControllerHarness $harness;
    private HydratorInterface&MockObject $hydrator;
    private PendingSocialAccountService&MockObject $pendingSocialAccountService;
    private RegisterService&MockObject $registerService;
    private ResendConfirmationService&MockObject $resendConfirmationService;
    private ResponseFactoryInterface&MockObject $responseFactory;
    private TranslatorInterface $translator;
    private UserRepository&MockObject $userRepository;
    private UserTokenRepository&MockObject $userTokenRepository;
    private ValidatorInterface&MockObject $validator;
    private WebViewRenderer&MockObject $viewRenderer;

    protected function setUp(): void
    {
        $this->config = new ModuleConfig();
        $this->harness = new ControllerHarness($this->config);
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->userTokenRepository = $this->createMock(UserTokenRepository::class);
        $this->translator = $this->createTranslator();
        $this->viewRenderer = $this->createMock(WebViewRenderer::class);
        $this->validator = $this->createMock(ValidatorInterface::class);
        $this->responseFactory = $this->createMock(ResponseFactoryInterface::class);
        $this->hydrator = $this->createMock(HydratorInterface::class);
        $this->flash = $this->createMock(FlashInterface::class);
        $this->registerService = $this->createMock(RegisterService::class);
        $this->confirmationService = $this->createMock(ConfirmationService::class);
        $this->accountConfirmationService = $this->createMock(AccountConfirmationService::class);
        $this->resendConfirmationService = $this->createMock(ResendConfirmationService::class);
        $this->pendingSocialAccountService = $this->createMock(PendingSocialAccountService::class);
    }

    public function testConfirmAlreadyConfirmedUser(): void
    {
        $controller = $this->createController();
        $request = new ServerRequest('GET', '/');

        $user = $this->createMock(User::class);
        $user->method('isConfirmed')->willReturn(true);

        $this->userRepository->method('findById')->willReturn($user);

        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->expects($this->once())
            ->method('createResponse')
            ->with(302)
            ->willReturn($response);
        $response->expects($this->once())
            ->method('withHeader')
            ->willReturnSelf();

        $result = $controller->confirm($request, 1, 'code123');

        $this->assertSame($response, $result);
    }

    public function testConfirmSuccessful(): void
    {
        $controller = $this->createController();
        $request = new ServerRequest('GET', '/');

        $user = $this->createMock(User::class);
        $user->method('isConfirmed')->willReturn(false);
        $user->method('getId')->willReturn('1');

        $this->userRepository->method('findById')->willReturn($user);
        $this->accountConfirmationService->expects($this->once())
            ->method('run')
            ->willReturn(true);

        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->expects($this->once())
            ->method('createResponse')
            ->with(302)
            ->willReturn($response);
        $response->expects($this->once())
            ->method('withHeader')
            ->willReturnSelf();

        $result = $controller->confirm($request, 1, 'code123');

        $this->assertSame($response, $result);
    }

    public function testConfirmWithInvalidCodeShowsError(): void
    {
        $controller = $this->createController();
        $request = new ServerRequest('GET', '/');

        $user = $this->createMock(User::class);
        $user->method('isConfirmed')->willReturn(false);

        $this->userRepository->method('findById')->willReturn($user);
        $this->accountConfirmationService->expects($this->once())
            ->method('run')
            ->willReturn(false);

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->expects($this->once())
            ->method('withViewPath')
            ->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->willReturn($response);

        $result = $controller->confirm($request, 1, 'code123');

        $this->assertSame($response, $result);
    }

    public function testConfirmWithInvalidUserOrDisabledConfig(): void
    {
        $config = new ModuleConfig(enableEmailConfirmation: false);
        $this->harness = new ControllerHarness($config);
        $controller = $this->createController();
        $request = new ServerRequest('GET', '/');

        $this->userRepository->method('findById')->willReturn(null);

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->expects($this->once())
            ->method('withViewPath')
            ->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->willReturn($response);

        $result = $controller->confirm($request, 1, 'code123');

        $this->assertSame($response, $result);
    }

    public function testConnectWithInvalidCodeShowsError(): void
    {
        $controller = $this->createController();
        $request = new ServerRequest('GET', '/');

        $this->pendingSocialAccountService->expects($this->once())
            ->method('useCode')
            ->willReturn(null);

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->expects($this->once())
            ->method('withViewPath')
            ->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->willReturn($response);

        $result = $controller->connect($request, 'code123');

        $this->assertSame($response, $result);
    }

    public function testConnectWithValidCodeShowsForm(): void
    {
        $controller = $this->createController();
        $request = new ServerRequest('GET', '/');

        $account = $this->createMock(\YiiRocks\Voyti\Entity\UserSocialAccount::class);
        $this->pendingSocialAccountService->expects($this->once())
            ->method('useCode')
            ->willReturn($account);

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->expects($this->once())
            ->method('withViewPath')
            ->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('registration/connect', $this->anything())
            ->willReturn($response);

        $result = $controller->connect($request, 'code123');

        $this->assertSame($response, $result);
    }

    public function testRegisterGetShowsForm(): void
    {
        $controller = $this->createController();
        $request = new ServerRequest('GET', '/');

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->expects($this->once())
            ->method('withViewPath')
            ->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('registration/register', $this->arrayHasKey('model'))
            ->willReturn($response);

        $result = $controller->register($request);

        $this->assertSame($response, $result);
    }

    public function testRegisterPostSuccessful(): void
    {
        $controller = $this->createController();
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['register' => ['username' => 'testuser', 'email' => 'test@example.com', 'password' => 'password123', 'passwordRepeat' => 'password123']]);

        $this->validator->method('validate')->willReturn(new Result());
        $this->registerService->expects($this->once())
            ->method('run')
            ->willReturn(ServiceResult::success('voyti.registration.account_created_check_email'));

        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn('1');

        $this->userRepository->method('findByEmail')->willReturn($user);

        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->expects($this->once())
            ->method('createResponse')
            ->with(302)
            ->willReturn($response);
        $response->expects($this->once())
            ->method('withHeader')
            ->willReturnSelf();

        $result = $controller->register($request);

        $this->assertSame($response, $result);
    }

    public function testRegisterPostWithServiceFailure(): void
    {
        $controller = $this->createController();
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['register' => ['username' => 'existing', 'email' => 'existing@example.com', 'password' => 'password123', 'passwordRepeat' => 'password123']]);

        $this->validator->method('validate')->willReturn(new Result());
        $this->registerService->expects($this->once())
            ->method('run')
            ->willReturn(ServiceResult::failure('Email already exists', ['Email already exists']));

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->expects($this->once())
            ->method('withViewPath')
            ->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->willReturn($response);

        $result = $controller->register($request);

        $this->assertSame($response, $result);
    }

    public function testRegisterPostWithValidationErrors(): void
    {
        $controller = $this->createController();
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['register' => ['username' => '', 'email' => '', 'password' => '', 'passwordRepeat' => '']]);

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->expects($this->once())
            ->method('withViewPath')
            ->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->willReturn($response);

        $result = $controller->register($request);

        $this->assertSame($response, $result);
    }

    public function testRegisterWhenDisabledShowsError(): void
    {
        $config = new ModuleConfig(enableRegistration: false);
        $this->harness = new ControllerHarness($config);
        $controller = $this->createController();
        $request = new ServerRequest('GET', '/');

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->expects($this->once())
            ->method('withViewPath')
            ->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->willReturn($response);

        $result = $controller->register($request);

        $this->assertSame($response, $result);
    }

    public function testResendGetShowsForm(): void
    {
        $controller = $this->createController();
        $request = new ServerRequest('GET', '/');

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->expects($this->once())
            ->method('withViewPath')
            ->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('registration/resend', $this->anything())
            ->willReturn($response);

        $result = $controller->resend($request);

        $this->assertSame($response, $result);
    }

    public function testResendPostSuccessful(): void
    {
        $controller = $this->createController();
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['resend' => ['email' => 'test@example.com']]);

        $this->validator->method('validate')->willReturn(new Result());
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn('1');

        $this->userRepository->method('findByEmail')->willReturn($user);
        $this->resendConfirmationService->expects($this->once())
            ->method('run')
            ->willReturn(true);

        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->expects($this->once())
            ->method('createResponse')
            ->with(302)
            ->willReturn($response);
        $response->expects($this->once())
            ->method('withHeader')
            ->willReturnSelf();

        $result = $controller->resend($request);

        $this->assertSame($response, $result);
    }

    public function testResendPostUserNotFound(): void
    {
        $controller = $this->createController();
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['resend' => ['email' => 'nonexistent@example.com']]);

        $this->userRepository->method('findByEmail')->willReturn(null);

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->expects($this->once())
            ->method('withViewPath')
            ->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->willReturn($response);

        $result = $controller->resend($request);

        $this->assertSame($response, $result);
    }

    public function testResendWhenDisabledShowsError(): void
    {
        $config = new ModuleConfig(enableEmailConfirmation: false);
        $this->harness = new ControllerHarness($config);
        $controller = $this->createController();
        $request = new ServerRequest('GET', '/');

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->expects($this->once())
            ->method('withViewPath')
            ->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->willReturn($response);

        $result = $controller->resend($request);

        $this->assertSame($response, $result);
    }

    private function createController(): RegistrationController
    {
        return $this->harness->createRegistrationController(
            userRepository: $this->userRepository,
            userTokenRepository: $this->userTokenRepository,
            translator: $this->translator,
            viewRenderer: $this->viewRenderer,
            validator: $this->validator,
            responseFactory: $this->responseFactory,
            hydrator: $this->hydrator,
            flash: $this->flash,
            registerService: $this->registerService,
            confirmationService: $this->confirmationService,
            accountConfirmationService: $this->accountConfirmationService,
            resendConfirmationService: $this->resendConfirmationService,
            pendingSocialAccountService: $this->pendingSocialAccountService,
        );
    }
}
