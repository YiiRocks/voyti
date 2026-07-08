<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Controller;

use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use YiiRocks\Voyti\Controller\RecoveryController;
use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\Entity\UserToken;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Repository\UserRepository;
use YiiRocks\Voyti\Repository\UserTokenRepository;
use YiiRocks\Voyti\Service\Password\RecoveryService;
use YiiRocks\Voyti\Service\Password\ResetService;
use YiiRocks\Voyti\Service\ServiceResult;
use YiiRocks\Voyti\tests\Support\ControllerHarness;
use YiiRocks\Voyti\tests\TestCase;
use Yiisoft\Hydrator\HydratorInterface;
use Yiisoft\Session\Flash\FlashInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\Validator\Result;
use Yiisoft\Validator\ValidatorInterface;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
final class RecoveryControllerTest extends TestCase
{
    private ModuleConfig $config;
    private FlashInterface&MockObject $flash;
    private ControllerHarness $harness;
    private HydratorInterface&MockObject $hydrator;
    private RecoveryService&MockObject $recoveryService;
    private ResetService&MockObject $resetService;
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
        $this->recoveryService = $this->createMock(RecoveryService::class);
        $this->resetService = $this->createMock(ResetService::class);

        $this->hydrator->method('hydrate')->willReturnCallback(
            function (object $object, array $data = []): void {
                $this->hydrateObject($object, $data);
            },
        );
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
            ->with('recovery/request', $this->anything())
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

        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->expects($this->once())
            ->method('createResponse')
            ->with(302)
            ->willReturn($response);
        $response->expects($this->once())
            ->method('withHeader')
            ->willReturnSelf();

        $result = $controller->request($request);

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
        $controller = $this->createController();
        $request = new ServerRequest('GET', '/');

        $userToken = $this->createMock(UserToken::class);
        $userToken->method('getIsExpired')->willReturn(false);
        $user = $this->createMock(User::class);
        $userToken->method('getUser')->willReturn($user);

        $this->userTokenRepository->method('findByUserIdTypeAndCode')->willReturn($userToken);

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->expects($this->once())
            ->method('withViewPath')
            ->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('recovery/reset', $this->anything())
            ->willReturn($response);

        $result = $controller->reset($request, 1, 'valid');

        $this->assertSame($response, $result);
    }

    public function testResetPostSuccessful(): void
    {
        $controller = $this->createController();
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['recovery' => ['password' => 'newpass123', 'passwordRepeat' => 'newpass123']]);

        $this->validator->method('validate')->willReturn(new Result());
        $userToken = $this->createMock(UserToken::class);
        $userToken->method('getIsExpired')->willReturn(false);
        $user = $this->createMock(User::class);
        $userToken->method('getUser')->willReturn($user);

        $this->userTokenRepository->method('findByUserIdTypeAndCode')->willReturn($userToken);
        $this->resetService->expects($this->once())
            ->method('run')
            ->with('newpass123', $user, $userToken);

        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->expects($this->once())
            ->method('createResponse')
            ->with(302)
            ->willReturn($response);
        $response->expects($this->once())
            ->method('withHeader')
            ->willReturnSelf();

        $result = $controller->reset($request, 1, 'valid');

        $this->assertSame($response, $result);
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

        $result = $controller->reset($request, 1, 'code123');

        $this->assertSame($response, $result);
    }

    public function testResetWithExpiredTokenShowsMessage(): void
    {
        $controller = $this->createController();
        $request = new ServerRequest('GET', '/');

        $userToken = $this->createMock(UserToken::class);
        $userToken->method('getIsExpired')->willReturn(true);
        $userToken->method('getUser')->willReturn(null);

        $this->userTokenRepository->method('findByUserIdTypeAndCode')->willReturn($userToken);

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->expects($this->once())
            ->method('withViewPath')
            ->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->willReturn($response);

        $result = $controller->reset($request, 1, 'expired');

        $this->assertSame($response, $result);
    }

    public function testResetWithInvalidTokenShowsMessage(): void
    {
        $controller = $this->createController();
        $request = new ServerRequest('GET', '/');

        $this->userTokenRepository->method('findByUserIdTypeAndCode')->willReturn(null);

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->expects($this->once())
            ->method('withViewPath')
            ->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->willReturn($response);

        $result = $controller->reset($request, 1, 'invalid');

        $this->assertSame($response, $result);
    }

    private function createController(): RecoveryController
    {
        return $this->harness->createRecoveryController(
            userRepository: $this->userRepository,
            userTokenRepository: $this->userTokenRepository,
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

    private function hydrateObject(object $object, array $data): void
    {
        foreach ($data as $key => $value) {
            if (property_exists($object, $key)) {
                $object->$key = $value;
            }
        }
    }
}
