<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Controller\PasswordReset;

use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use YiiRocks\Voyti\Controller\PasswordReset\PasswordResetController;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Model\UserToken;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Service\Password\RecoveryService;
use YiiRocks\Voyti\Service\Password\ResetService;
use YiiRocks\Voyti\Service\ServiceResult;
use YiiRocks\Voyti\tests\Support\ControllerHarness;
use YiiRocks\Voyti\tests\Support\DatabaseSetupTrait;
use YiiRocks\Voyti\tests\Support\HydrateObjectTrait;
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
final class PasswordResetControllerTest extends TestCase
{
    use DatabaseSetupTrait;
    use HydrateObjectTrait;
    use RedirectResponseMockTrait;
    use UserFactoryTrait;

    private ModuleConfig $config;
    private FlashInterface&MockObject $flash;
    private ControllerHarness $harness;
    private HydratorInterface&MockObject $hydrator;
    private RecoveryService&MockObject $recoveryService;
    private ResetService&MockObject $resetService;
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
        $this->recoveryService = $this->createMock(RecoveryService::class);
        $this->resetService = $this->createMock(ResetService::class);

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

    public function testRequestGetShowsForm(): void
    {
        $controller = $this->createController();
        $request = new ServerRequest('GET', '/');

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->expects($this->once())
            ->method('withViewPath')
            ->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('password-reset/request', $this->anything())
            ->willReturn($response);

        $result = $controller->request($request);

        $this->assertSame($response, $result);
    }

    public function testRequestPostSuccessful(): void
    {
        $controller = $this->createController();
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['recovery' => ['email' => 'test@example.com']]);

        $this->validator->method('validate')->willReturn(new Result());
        $this->recoveryService->expects($this->once())
            ->method('run')
            ->willReturn(ServiceResult::success('voyti.recovery.message_sent'));

        $response = $this->mockRedirectResponse($this->responseFactory);

        $result = $controller->request($request, ['email' => 'test@example.com']);

        $this->assertSame($response, $result);
    }

    public function testRequestWhenDisabledShowsError(): void
    {
        $config = new ModuleConfig(allowPasswordRecovery: false);
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

        $result = $controller->request($request);

        $this->assertSame($response, $result);
    }

    public function testResetGetWithValidTokenShowsForm(): void
    {
        $user = $this->createUser(username: 'recoveryuser', email: 'recoveryuser@example.com');
        $this->createRecoveryToken((int) $user->getId(), 'valid', time());

        $controller = $this->createController();
        $request = new ServerRequest('GET', '/');

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->expects($this->once())
            ->method('withViewPath')
            ->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('password-reset/confirm', $this->anything())
            ->willReturn($response);

        $result = $controller->confirm($request, (int) $user->getId(), 'valid');

        $this->assertSame($response, $result);
    }

    public function testResetPostSuccessful(): void
    {
        $user = $this->createUser(username: 'recoveryuser', email: 'recoveryuser@example.com');
        $this->createRecoveryToken((int) $user->getId(), 'valid', time());

        $controller = $this->createController();
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['recovery' => ['password' => 'newpass123', 'passwordRepeat' => 'newpass123']]);

        $this->validator->method('validate')->willReturn(new Result());
        $this->resetService->expects($this->once())
            ->method('run')
            ->with(
                'newpass123',
                $this->callback(static fn(User $u): bool => $u->getId() === $user->getId()),
                $this->callback(static fn(UserToken $t): bool => $t->getCode() === 'valid'),
            )
            ->willReturn(true);

        $response = $this->mockRedirectResponse($this->responseFactory);

        $result = $controller->confirm($request, (int) $user->getId(), 'valid', ['password' => 'newpass123', 'passwordRepeat' => 'newpass123']);

        $this->assertSame($response, $result);
    }

    public function testResetPostWithInvalidDataShowsErrors(): void
    {
        $user = $this->createUser(username: 'recoveryuser', email: 'recoveryuser@example.com');
        $this->createRecoveryToken((int) $user->getId(), 'valid', time());

        $controller = $this->createController();
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['recovery' => ['password' => '', 'passwordRepeat' => '']]);

        $result = new Result();
        $result->addError('Password is required.', valuePath: ['password']);
        $this->validator->method('validate')->willReturn($result);
        $this->resetService->expects($this->never())->method('run');

        $captured = [];
        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->method('withViewPath')->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('password-reset/confirm', $this->callback(function (array $params) use (&$captured): bool {
                $captured = $params;
                return true;
            }))
            ->willReturn($response);

        $result2 = $controller->confirm($request, (int) $user->getId(), 'valid', ['password' => '', 'passwordRepeat' => '']);

        $this->assertSame($response, $result2);
        $this->assertFalse($captured['form']->isValid());
        $this->assertSame(
            ['Password is required.'],
            $captured['form']->getValidationResult()->getPropertyErrorMessages('password'),
        );
    }

    public function testResetPostWithPreviouslyUsedPasswordShowsError(): void
    {
        $user = $this->createUser(username: 'recoveryuser', email: 'recoveryuser@example.com');
        $this->createRecoveryToken((int) $user->getId(), 'valid', time());

        $controller = $this->createController();
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['recovery' => ['password' => 'newpass123', 'passwordRepeat' => 'newpass123']]);

        $this->validator->method('validate')->willReturn(new Result());
        $this->resetService->expects($this->once())->method('run')->willReturn(false);

        $captured = [];
        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->method('withViewPath')->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('password-reset/confirm', $this->callback(function (array $params) use (&$captured): bool {
                $captured = $params;
                return true;
            }))
            ->willReturn($response);

        $result = $controller->confirm($request, (int) $user->getId(), 'valid', ['password' => 'newpass123', 'passwordRepeat' => 'newpass123']);

        $this->assertSame($response, $result);
        $this->assertFalse($captured['form']->isValid());
        $this->assertSame(
            ['This password has been used recently. Please choose a different one.'],
            $captured['form']->getValidationResult()->getPropertyErrorMessages('password'),
        );
    }

    public function testResetWithDisabledConfigShowsMessage(): void
    {
        $config = new ModuleConfig(allowPasswordRecovery: false, allowAdminPasswordRecovery: false);
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

        $result = $controller->confirm($request, 1, 'code123');

        $this->assertSame($response, $result);
    }

    public function testResetWithExpiredTokenShowsMessage(): void
    {
        $user = $this->createUser(username: 'recoveryuser', email: 'recoveryuser@example.com');
        $this->createRecoveryToken((int) $user->getId(), 'expired', time() - 1_000_000);

        $controller = $this->createController();
        $request = new ServerRequest('GET', '/');

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->expects($this->once())
            ->method('withViewPath')
            ->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->willReturn($response);

        $result = $controller->confirm($request, (int) $user->getId(), 'expired');

        $this->assertSame($response, $result);
    }

    public function testResetWithInvalidTokenShowsMessage(): void
    {
        $controller = $this->createController();
        $request = new ServerRequest('GET', '/');

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->expects($this->once())
            ->method('withViewPath')
            ->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->willReturn($response);

        $result = $controller->confirm($request, 1, 'invalid');

        $this->assertSame($response, $result);
    }

    private function createController(): PasswordResetController
    {
        return $this->harness->createPasswordResetController(
            translator: $this->translator,
            viewRenderer: $this->viewRenderer,
            validator: $this->validator,
            responseFactory: $this->responseFactory,
            hydrator: $this->hydrator,
            flash: $this->flash,
            recoveryService: $this->recoveryService,
            resetService: $this->resetService,
        );
    }

    private function createRecoveryToken(int $userId, string $code, int $createdAt): UserToken
    {
        $userToken = new UserToken();
        $userToken->setUserId($userId);
        $userToken->setType(UserToken::TYPE_RECOVERY);
        $userToken->setCode($code);
        $userToken->setCreatedAt($createdAt);
        $userToken->save();

        return $userToken;
    }
}
