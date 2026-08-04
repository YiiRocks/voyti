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
use YiiRocks\Voyti\Service\Password\RecoveryService;
use YiiRocks\Voyti\Service\Password\ResetService;
use YiiRocks\Voyti\Service\ServiceResult;
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
final class PasswordResetControllerTest extends TestCase
{
    use DatabaseSetupTrait;
    use RedirectResponseMockTrait;
    use TestContainerTrait;
    use UserFactoryTrait;
    use ValidatorMockTrait;

    private FlashInterface&MockObject $flash;
    private RecoveryService&MockObject $recoveryService;
    private ResetService&MockObject $resetService;
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
        $this->recoveryService = $this->createMock(RecoveryService::class);
        $this->resetService = $this->createMock(ResetService::class);
        $this->validator = $this->mockValidValidator();
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

        $this->recoveryService->expects($this->once())
            ->method('run')
            ->willReturn(ServiceResult::success('voyti.recovery.message_sent'));

        $response = $this->mockRedirectResponse($this->responseFactory);

        $result = $controller->request($request);

        $this->assertSame($response, $result);
    }

    public function testRequestWhenDisabledShowsError(): void
    {
        $config = VoytiConfigFactory::create(allowPasswordRecovery: false);
        $controller = $this->createController([VoytiConfig::class => $config]);
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

        $this->resetService->expects($this->once())
            ->method('run')
            ->with(
                'newpass123',
                $this->callback(static fn(User $u): bool => $u->getId() === $user->getId()),
                $this->callback(static fn(UserToken $t): bool => $t->getCode() === hash('sha256', 'valid')),
            )
            ->willReturn(true);

        $response = $this->mockRedirectResponse($this->responseFactory);

        $result = $controller->confirm($request, (int) $user->getId(), 'valid');

        $this->assertSame($response, $result);
    }

    public function testResetPostWithInvalidDataShowsErrors(): void
    {
        $user = $this->createUser(username: 'recoveryuser', email: 'recoveryuser@example.com');
        $this->createRecoveryToken((int) $user->getId(), 'valid', time());

        // Needs the real Validator - asserts on real rule-generated messages, not just that validation failed.
        $controller = $this->createControllerWithRealValidation();
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['recovery' => ['password' => '', 'passwordRepeat' => '']]);

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

        $result2 = $controller->confirm($request, (int) $user->getId(), 'valid');

        $this->assertSame($response, $result2);
        $this->assertFalse($captured['form']->isValid());
        $this->assertSame(
            ['New password cannot be blank.', 'New password must contain at least 6 characters.'],
            $captured['form']->getValidationResult()?->getPropertyErrorMessages('password'),
        );
    }

    public function testResetPostWithPreviouslyUsedPasswordShowsError(): void
    {
        $user = $this->createUser(username: 'recoveryuser', email: 'recoveryuser@example.com');
        $this->createRecoveryToken((int) $user->getId(), 'valid', time());

        $controller = $this->createController();
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['recovery' => ['password' => 'newpass123', 'passwordRepeat' => 'newpass123']]);

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

        $result = $controller->confirm($request, (int) $user->getId(), 'valid');

        $this->assertSame($response, $result);
        $this->assertFalse($captured['form']->isValid());
        $this->assertSame(
            ['This password has been used recently. Please choose a different one.'],
            $captured['form']->getValidationResult()?->getPropertyErrorMessages('password'),
        );
    }

    public function testResetWithDisabledConfigShowsMessage(): void
    {
        $config = VoytiConfigFactory::create(allowPasswordRecovery: false, allowAdminPasswordRecovery: false);
        $controller = $this->createController([VoytiConfig::class => $config]);
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

    private function baseOverrides(): array
    {
        return [
            FlashInterface::class => $this->flash,
            RecoveryService::class => $this->recoveryService,
            ResetService::class => $this->resetService,
            ResponseFactoryInterface::class => $this->responseFactory,
            WebViewRenderer::class => $this->viewRenderer,
        ];
    }

    private function createController(array $overrides = []): PasswordResetController
    {
        return $this->getTestContainer([
            ...$this->baseOverrides(),
            ValidatorInterface::class => $this->validator,
            ...$overrides,
        ])->get(PasswordResetController::class);
    }

    /**
     * Uses the real ValidatorInterface instead of the fast valid-by-default mock, for tests that assert on real
     * rule-generated validation messages.
     */
    private function createControllerWithRealValidation(): PasswordResetController
    {
        return $this->getTestContainer($this->baseOverrides())->get(PasswordResetController::class);
    }

    private function createRecoveryToken(int $userId, string $code, int $createdAt): UserToken
    {
        $userToken = new UserToken();
        $userToken->setUserId($userId);
        $userToken->setType(UserToken::TYPE_RECOVERY);
        $userToken->setCode(hash('sha256', $code));
        $userToken->setCreatedAt($createdAt);
        $userToken->save();

        return $userToken;
    }
}
