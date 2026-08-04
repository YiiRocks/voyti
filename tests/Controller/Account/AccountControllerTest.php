<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Controller\Account;

use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use YiiRocks\Voyti\Controller\Account\AccountController;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Service\EmailChangeService;
use YiiRocks\Voyti\tests\Support\DatabaseSetupTrait;
use YiiRocks\Voyti\tests\Support\RedirectResponseMockTrait;
use YiiRocks\Voyti\tests\Support\TestContainerTrait;
use YiiRocks\Voyti\tests\Support\TestPasswordHasherFactory;
use YiiRocks\Voyti\tests\Support\UserFactoryTrait;
use YiiRocks\Voyti\tests\Support\ValidatorMockTrait;
use YiiRocks\Voyti\tests\Support\VoytiConfigFactory;
use YiiRocks\Voyti\tests\TestCase;
use YiiRocks\Voyti\VoytiConfig;
use Yiisoft\Security\PasswordHasher;
use Yiisoft\Session\Flash\FlashInterface;
use Yiisoft\User\CurrentUser;
use Yiisoft\Validator\ValidatorInterface;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

#[AllowMockObjectsWithoutExpectations]
final class AccountControllerTest extends TestCase
{
    use DatabaseSetupTrait;
    use RedirectResponseMockTrait;
    use TestContainerTrait;
    use UserFactoryTrait;
    use ValidatorMockTrait;

    private CurrentUser&MockObject $currentUser;
    private EmailChangeService&MockObject $emailChangeService;
    private FlashInterface&MockObject $flash;
    private PasswordHasher $passwordHasher;
    private ResponseFactoryInterface&MockObject $responseFactory;
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
        $this->emailChangeService = $this->createMock(EmailChangeService::class);
        $this->validator = $this->mockValidValidator();
    }

    protected function tearDown(): void
    {
        $this->tearDownDatabase();
    }

    /**
     * @return iterable<string, array{string, bool, string}>
     */
    public static function confirmProvider(): iterable
    {
        yield 'invalid code shows failure message' => ['bad-code', false, 'Failed to change email'];
        yield 'valid code shows success message' => ['good-code', true, 'Your email has been changed'];
    }

    public function testAccountGetShowsForm(): void
    {
        $controller = $this->createController();
        $request = new ServerRequest('GET', '/');

        $user = $this->createUser(passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $this->currentUser->method('getIdentity')->willReturn($user);

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->expects($this->once())
            ->method('withViewPath')
            ->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('account/update', $this->anything())
            ->willReturn($response);

        $result = $controller->update($request);

        $this->assertSame($response, $result);
    }

    public function testAccountPostUpdatesAndRedirects(): void
    {
        $controller = $this->createController();
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['settings' => ['username' => 'testuser', 'email' => 'test@example.com', 'password' => '', 'passwordRepeat' => '']]);

        $user = $this->createUser(passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $this->currentUser->method('getIdentity')->willReturn($user);

        $response = $this->mockRedirectResponse($this->responseFactory);

        $result = $controller->update($request);

        $this->assertSame($response, $result);
        $updated = User::findById((int) $user->getId());
        $this->assertNotNull($updated);
        $this->assertSame('testuser', $updated->getUsername());
    }

    public function testAccountPostWithNewEmailInvokesChangeStrategy(): void
    {
        $controller = $this->createController();
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['settings' => ['username' => 'testuser', 'email' => 'new@example.com', 'password' => '', 'passwordRepeat' => '']]);

        $user = $this->createUser(email: 'old@example.com', passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $this->currentUser->method('getIdentity')->willReturn($user);

        $this->emailChangeService->expects($this->once())
            ->method('initiate')
            ->willReturn(true);

        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->method('createResponse')->willReturn($response);
        $response->method('withHeader')->willReturnSelf();

        $result = $controller->update($request);

        $this->assertSame($response, $result);
    }

    public function testAccountPostWithPasswordChange(): void
    {
        $controller = $this->createController();
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['settings' => ['username' => 'testuser', 'email' => 'test@example.com', 'password' => 'newpassword', 'passwordRepeat' => 'newpassword']]);

        $user = $this->createUser(passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $originalHash = $user->getPasswordHash();
        $this->currentUser->method('getIdentity')->willReturn($user);

        $response = $this->mockRedirectResponse($this->responseFactory);

        $result = $controller->update($request);

        $this->assertSame($response, $result);
        $updated = User::findById((int) $user->getId());
        $this->assertNotNull($updated);
        $this->assertNotSame($originalHash, $updated->getPasswordHash());
        $this->assertNotNull($updated->getPasswordChangedAt());
    }

    public function testAccountPostWithPreviouslyUsedPasswordShowsError(): void
    {
        $controller = $this->createController([
            VoytiConfig::class => VoytiConfigFactory::create(maxPasswordAge: 90),
        ]);
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['settings' => ['username' => 'testuser', 'email' => 'test@example.com', 'password' => 'secret', 'passwordRepeat' => 'secret']]);

        $user = $this->createUser(passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $this->currentUser->method('getIdentity')->willReturn($user);

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->method('withViewPath')->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('account/update', $this->anything())
            ->willReturn($response);

        $result = $controller->update($request);

        $this->assertSame($response, $result);
        $updated = User::findById((int) $user->getId());
        $this->assertNotNull($updated);
        $this->assertSame('testuser', $updated->getUsername());
    }

    #[DataProvider('confirmProvider')]
    public function testConfirmWithCodeShowsMessage(string $code, bool $serviceResult, string $expectedTitle): void
    {
        $controller = $this->createController();

        $user = $this->createUser(passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $this->currentUser->method('getIdentity')->willReturn($user);

        $this->emailChangeService->expects($this->once())->method('run')->with(
            $code,
            $this->callback(static fn(User $u): bool => $u->getId() === $user->getId()),
        )->willReturn($serviceResult);

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->method('withViewPath')->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('shared/message', $this->callback(
                static fn(array $params): bool => $params['data']->title === $expectedTitle,
            ))
            ->willReturn($response);

        $result = $controller->confirm($code);

        $this->assertSame($response, $result);
    }

    private function createController(array $overrides = []): AccountController
    {
        return $this->getTestContainer(array_merge([
            CurrentUser::class => $this->currentUser,
            EmailChangeService::class => $this->emailChangeService,
            FlashInterface::class => $this->flash,
            ResponseFactoryInterface::class => $this->responseFactory,
            ValidatorInterface::class => $this->validator,
            WebViewRenderer::class => $this->viewRenderer,
        ], $overrides))->get(AccountController::class);
    }
}
