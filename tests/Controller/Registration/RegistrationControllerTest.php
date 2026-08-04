<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Controller\Registration;

use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use YiiRocks\Voyti\Controller\Registration\RegistrationController;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Model\UserSocialAccount;
use YiiRocks\Voyti\Service\Auth\PendingSocialAccountService;
use YiiRocks\Voyti\Service\ServiceResult;
use YiiRocks\Voyti\Service\User\ConfirmationService;
use YiiRocks\Voyti\Service\User\RegisterService;
use YiiRocks\Voyti\tests\Support\DatabaseSetupTrait;
use YiiRocks\Voyti\tests\Support\RedirectResponseMockTrait;
use YiiRocks\Voyti\tests\Support\TestContainerTrait;
use YiiRocks\Voyti\tests\Support\UserFactoryTrait;
use YiiRocks\Voyti\tests\Support\ValidatorMockTrait;
use YiiRocks\Voyti\tests\Support\VoytiConfigFactory;
use YiiRocks\Voyti\tests\TestCase;
use YiiRocks\Voyti\VoytiConfig;
use Yiisoft\Session\Flash\FlashInterface;
use Yiisoft\Validator\ValidatorInterface;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

#[AllowMockObjectsWithoutExpectations]
final class RegistrationControllerTest extends TestCase
{
    use DatabaseSetupTrait;
    use RedirectResponseMockTrait;
    use TestContainerTrait;
    use UserFactoryTrait;
    use ValidatorMockTrait;

    private ConfirmationService&MockObject $confirmationService;
    private FlashInterface&MockObject $flash;
    private PendingSocialAccountService&MockObject $pendingSocialAccountService;
    private RegisterService&MockObject $registerService;
    private ResponseFactoryInterface&MockObject $responseFactory;
    private ValidatorInterface&MockObject $validator;
    private WebViewRenderer&MockObject $viewRenderer;

    protected function setUp(): void
    {
        $this->setUpDatabase();
        $this->viewRenderer = $this->createMock(WebViewRenderer::class);
        $this->viewRenderer->method('withAddedInjections')->willReturnSelf();
        $this->responseFactory = $this->createMock(ResponseFactoryInterface::class);
        $this->flash = $this->createMock(FlashInterface::class);
        $this->registerService = $this->createMock(RegisterService::class);
        $this->confirmationService = $this->createMock(ConfirmationService::class);
        $this->pendingSocialAccountService = $this->createMock(PendingSocialAccountService::class);
        $this->validator = $this->mockValidValidator();
    }

    protected function tearDown(): void
    {
        $this->tearDownDatabase();
    }

    public function testConfirmAlreadyConfirmedUser(): void
    {
        $user = $this->createUser('confirmeduser', 'confirmed@example.com');
        $user->setConfirmedAt(time());
        $user->save();

        $response = $this->mockRedirectResponse($this->responseFactory);

        $controller = $this->createController();
        $request = new ServerRequest('GET', '/');

        $result = $controller->confirm((int) $user->getId(), 'code123');

        $this->assertSame($response, $result);
    }

    public function testConfirmSuccessful(): void
    {
        $user = $this->createUser('unconfirmeduser', 'unconfirmed@example.com');

        $this->confirmationService->expects($this->once())
            ->method('confirmWithCode')
            ->willReturn(true);

        $response = $this->mockRedirectResponse($this->responseFactory);

        $controller = $this->createController();

        $result = $controller->confirm((int) $user->getId(), 'code123');

        $this->assertSame($response, $result);
    }

    public function testConfirmWithInvalidCodeShowsError(): void
    {
        $user = $this->createUser('unconfirmeduser2', 'unconfirmed2@example.com');

        $this->confirmationService->expects($this->once())
            ->method('confirmWithCode')
            ->willReturn(false);

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->expects($this->once())
            ->method('withViewPath')
            ->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->willReturn($response);

        $controller = $this->createController();

        $result = $controller->confirm((int) $user->getId(), 'code123');

        $this->assertSame($response, $result);
    }

    public function testConfirmWithInvalidUserOrDisabledConfig(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->expects($this->once())
            ->method('withViewPath')
            ->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->willReturn($response);

        $controller = $this->createController(VoytiConfigFactory::create(enableEmailConfirmation: false));

        $result = $controller->confirm(999999, 'code123');

        $this->assertSame($response, $result);
    }

    public function testConnectWithInvalidCodeShowsError(): void
    {
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

        $controller = $this->createController();

        $result = $controller->connect('code123');

        $this->assertSame($response, $result);
    }

    public function testConnectWithValidCodeShowsForm(): void
    {
        $account = $this->createMock(UserSocialAccount::class);
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

        $controller = $this->createController();

        $result = $controller->connect('code123');

        $this->assertSame($response, $result);
    }

    public function testRegisterGetShowsForm(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->expects($this->once())
            ->method('withViewPath')
            ->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('registration/register', $this->arrayHasKey('form'))
            ->willReturn($response);

        $controller = $this->createController();
        $request = new ServerRequest('GET', '/');

        $result = $controller->register($request);

        $this->assertSame($response, $result);
    }

    public function testRegisterPostSuccessful(): void
    {
        $user = $this->createUser('testuser', 'test@example.com');

        $this->registerService->expects($this->once())
            ->method('run')
            ->willReturn(ServiceResult::success('voyti.registration.account_created_check_email'));
        $this->pendingSocialAccountService->expects($this->once())
            ->method('connect')
            ->with($this->callback(static fn(User $u): bool => $u->getId() === $user->getId()));

        $response = $this->mockRedirectResponse($this->responseFactory);

        $controller = $this->createController();
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['register' => ['username' => 'testuser', 'email' => 'test@example.com', 'password' => 'password123', 'passwordRepeat' => 'password123']]);

        $result = $controller->register($request);

        $this->assertSame($response, $result);
    }

    public function testRegisterPostWithServiceFailure(): void
    {
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

        $controller = $this->createController();
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['register' => ['username' => 'existing', 'email' => 'existing@example.com', 'password' => 'password123', 'passwordRepeat' => 'password123']]);

        $result = $controller->register($request);

        $this->assertSame($response, $result);
    }

    public function testRegisterPostWithValidationErrors(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->expects($this->once())
            ->method('withViewPath')
            ->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->willReturn($response);

        // Needs the real Validator - this test's whole point is that empty required fields get rejected.
        $controller = $this->createControllerWithRealValidation();
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['register' => ['username' => '', 'email' => '', 'password' => '', 'passwordRepeat' => '']]);

        $result = $controller->register($request);

        $this->assertSame($response, $result);
    }

    public function testRegisterWhenDisabledShowsError(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->expects($this->once())
            ->method('withViewPath')
            ->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->willReturn($response);

        $controller = $this->createController(VoytiConfigFactory::create(enableRegistration: false));
        $request = new ServerRequest('GET', '/');

        $result = $controller->register($request);

        $this->assertSame($response, $result);
    }

    public function testResendGetShowsForm(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->expects($this->once())
            ->method('withViewPath')
            ->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('registration/resend', $this->anything())
            ->willReturn($response);

        $controller = $this->createController();
        $request = new ServerRequest('GET', '/');

        $result = $controller->resend($request);

        $this->assertSame($response, $result);
    }

    public function testResendPostSuccessful(): void
    {
        $this->createUser('resenduser', 'test@example.com');

        $this->confirmationService->expects($this->once())
            ->method('resend')
            ->willReturn(true);

        $response = $this->mockRedirectResponse($this->responseFactory);

        $controller = $this->createController();
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['resend' => ['email' => 'test@example.com']]);

        $result = $controller->resend($request);

        $this->assertSame($response, $result);
    }

    public function testResendPostUserNotFound(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->expects($this->once())
            ->method('withViewPath')
            ->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->willReturn($response);

        $controller = $this->createController();
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['resend' => ['email' => 'nonexistent@example.com']]);

        $result = $controller->resend($request);

        $this->assertSame($response, $result);
    }

    public function testResendWhenDisabledShowsError(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->expects($this->once())
            ->method('withViewPath')
            ->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->willReturn($response);

        $controller = $this->createController(VoytiConfigFactory::create(enableEmailConfirmation: false));
        $request = new ServerRequest('GET', '/');

        $result = $controller->resend($request);

        $this->assertSame($response, $result);
    }

    private function baseOverrides(?VoytiConfig $config = null): array
    {
        $overrides = [
            FlashInterface::class => $this->flash,
            PendingSocialAccountService::class => $this->pendingSocialAccountService,
            ConfirmationService::class => $this->confirmationService,
            RegisterService::class => $this->registerService,
            ResponseFactoryInterface::class => $this->responseFactory,
            WebViewRenderer::class => $this->viewRenderer,
        ];

        if ($config !== null) {
            $overrides[VoytiConfig::class] = $config;
        }

        return $overrides;
    }

    private function createController(?VoytiConfig $config = null): RegistrationController
    {
        return $this->getTestContainer([
            ...$this->baseOverrides($config),
            ValidatorInterface::class => $this->validator,
        ])->get(RegistrationController::class);
    }

    /**
     * Uses the real ValidatorInterface instead of the fast valid-by-default mock, for tests whose point is that a
     * real validation rule rejects the input.
     */
    private function createControllerWithRealValidation(?VoytiConfig $config = null): RegistrationController
    {
        return $this->getTestContainer($this->baseOverrides($config))->get(RegistrationController::class);
    }
}
