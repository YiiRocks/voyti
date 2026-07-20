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
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Service\Auth\PendingSocialAccountService;
use YiiRocks\Voyti\Service\ServiceResult;
use YiiRocks\Voyti\Service\User\ConfirmationService;
use YiiRocks\Voyti\Service\User\RegisterService;
use YiiRocks\Voyti\tests\Support\ControllerHarness;
use YiiRocks\Voyti\tests\Support\DatabaseSetupTrait;
use YiiRocks\Voyti\tests\Support\RedirectResponseMockTrait;
use YiiRocks\Voyti\tests\Support\UserFactoryTrait;
use YiiRocks\Voyti\tests\TestCase;
use Yiisoft\Hydrator\HydratorInterface;
use Yiisoft\Session\Flash\FlashInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\Validator\Result;
use Yiisoft\Validator\ValidatorInterface;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

#[AllowMockObjectsWithoutExpectations]
final class RegistrationControllerTest extends TestCase
{
    use DatabaseSetupTrait;
    use RedirectResponseMockTrait;
    use UserFactoryTrait;

    private ModuleConfig $config;
    private ConfirmationService&MockObject $confirmationService;
    private FlashInterface&MockObject $flash;
    private ControllerHarness $harness;
    private HydratorInterface&MockObject $hydrator;
    private PendingSocialAccountService&MockObject $pendingSocialAccountService;
    private RegisterService&MockObject $registerService;
    private ResponseFactoryInterface&MockObject $responseFactory;
    private TranslatorInterface $translator;
    private ValidatorInterface&MockObject $validator;
    private WebViewRenderer&MockObject $viewRenderer;

    protected function setUp(): void
    {
        $this->setUpDatabase();
        $this->config = new ModuleConfig();
        $this->harness = new ControllerHarness($this->config);
        $this->translator = $this->createTranslator();
        $this->viewRenderer = $this->createMock(WebViewRenderer::class);
        $this->validator = $this->createMock(ValidatorInterface::class);
        $this->responseFactory = $this->createMock(ResponseFactoryInterface::class);
        $this->hydrator = $this->createMock(HydratorInterface::class);
        $this->flash = $this->createMock(FlashInterface::class);
        $this->registerService = $this->createMock(RegisterService::class);
        $this->confirmationService = $this->createMock(ConfirmationService::class);
        $this->pendingSocialAccountService = $this->createMock(PendingSocialAccountService::class);

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

    public function testConfirmAlreadyConfirmedUser(): void
    {
        $user = $this->createUser('confirmeduser', 'confirmed@example.com');
        $user->setConfirmedAt(time());
        $user->save();

        $controller = $this->createController();
        $request = new ServerRequest('GET', '/');

        $response = $this->mockRedirectResponse($this->responseFactory);

        $result = $controller->confirm($request, (int) $user->getId(), 'code123');

        $this->assertSame($response, $result);
    }

    public function testConfirmSuccessful(): void
    {
        $user = $this->createUser('unconfirmeduser', 'unconfirmed@example.com');

        $controller = $this->createController();
        $request = new ServerRequest('GET', '/');

        $this->confirmationService->expects($this->once())
            ->method('confirmWithCode')
            ->willReturn(true);

        $response = $this->mockRedirectResponse($this->responseFactory);

        $result = $controller->confirm($request, (int) $user->getId(), 'code123');

        $this->assertSame($response, $result);
    }

    public function testConfirmWithInvalidCodeShowsError(): void
    {
        $user = $this->createUser('unconfirmeduser2', 'unconfirmed2@example.com');

        $controller = $this->createController();
        $request = new ServerRequest('GET', '/');

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

        $result = $controller->confirm($request, (int) $user->getId(), 'code123');

        $this->assertSame($response, $result);
    }

    public function testConfirmWithInvalidUserOrDisabledConfig(): void
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

        $result = $controller->confirm($request, 999999, 'code123');

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
            ->with('registration/register', $this->arrayHasKey('form'))
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

        $user = $this->createUser('testuser', 'test@example.com');
        $this->pendingSocialAccountService->expects($this->once())
            ->method('connect')
            ->with($this->callback(static fn(User $u): bool => $u->getId() === $user->getId()));

        $response = $this->mockRedirectResponse($this->responseFactory);

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
        $this->createUser('resenduser', 'test@example.com');

        $controller = $this->createController();
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['resend' => ['email' => 'test@example.com']]);

        $this->validator->method('validate')->willReturn(new Result());
        $this->confirmationService->expects($this->once())
            ->method('resend')
            ->willReturn(true);

        $response = $this->mockRedirectResponse($this->responseFactory);

        $result = $controller->resend($request);

        $this->assertSame($response, $result);
    }

    public function testResendPostUserNotFound(): void
    {
        $controller = $this->createController();
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['resend' => ['email' => 'nonexistent@example.com']]);

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
            translator: $this->translator,
            viewRenderer: $this->viewRenderer,
            validator: $this->validator,
            responseFactory: $this->responseFactory,
            hydrator: $this->hydrator,
            flash: $this->flash,
            registerService: $this->registerService,
            confirmationService: $this->confirmationService,
            pendingSocialAccountService: $this->pendingSocialAccountService,
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
