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
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Service\EmailChangeService;
use YiiRocks\Voyti\tests\Support\ControllerHarness;
use YiiRocks\Voyti\tests\Support\DatabaseSetupTrait;
use YiiRocks\Voyti\tests\Support\RedirectResponseMockTrait;
use YiiRocks\Voyti\tests\Support\UserFactoryTrait;
use YiiRocks\Voyti\tests\TestCase;
use Yiisoft\Hydrator\HydratorInterface;
use Yiisoft\Security\PasswordHasher;
use Yiisoft\Session\Flash\FlashInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\User\CurrentUser;
use Yiisoft\User\Guest\GuestIdentityInterface;
use Yiisoft\Validator\Result;
use Yiisoft\Validator\ValidatorInterface;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

#[AllowMockObjectsWithoutExpectations]
final class AccountControllerTest extends TestCase
{
    use DatabaseSetupTrait;
    use RedirectResponseMockTrait;
    use UserFactoryTrait;

    private ModuleConfig $config;
    private CurrentUser&MockObject $currentUser;
    private EmailChangeService&MockObject $emailChangeService;
    private FlashInterface&MockObject $flash;
    private ControllerHarness $harness;
    private HydratorInterface&MockObject $hydrator;
    private PasswordHasher $passwordHasher;
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
        $this->currentUser = $this->createMock(CurrentUser::class);
        $this->responseFactory = $this->createMock(ResponseFactoryInterface::class);
        $this->hydrator = $this->createMock(HydratorInterface::class);
        $this->flash = $this->createMock(FlashInterface::class);
        $this->passwordHasher = new PasswordHasher();
        $this->emailChangeService = $this->createMock(EmailChangeService::class);
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
        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn((string) $user->getId());
        $this->currentUser->method('getIdentity')->willReturn($identity);

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

        $this->validator->method('validate')->willReturn(new Result());
        $user = $this->createUser(passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn((string) $user->getId());
        $this->currentUser->method('getIdentity')->willReturn($identity);

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

        $this->hydrator->method('hydrate')->willReturnCallback(
            function (object $object, array $data = []): void {
                if (property_exists($object, 'username') && isset($data['username'])) {
                    $object->username = $data['username'];
                }
                if (property_exists($object, 'email') && isset($data['email'])) {
                    $object->email = $data['email'];
                }
            },
        );
        $this->validator->method('validate')->willReturn(new Result());
        $user = $this->createUser(email: 'old@example.com', passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn((string) $user->getId());
        $this->currentUser->method('getIdentity')->willReturn($identity);

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

        $this->hydrator->method('hydrate')->willReturnCallback(
            function (object $object, array $data = []): void {
                if (property_exists($object, 'password') && isset($data['password'])) {
                    $object->password = $data['password'];
                }
                if (property_exists($object, 'passwordRepeat') && isset($data['passwordRepeat'])) {
                    $object->passwordRepeat = $data['passwordRepeat'];
                }
                if (property_exists($object, 'username') && isset($data['username'])) {
                    $object->username = $data['username'];
                }
                if (property_exists($object, 'email') && isset($data['email'])) {
                    $object->email = $data['email'];
                }
            },
        );
        $this->validator->method('validate')->willReturn(new Result());
        $user = $this->createUser(passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $originalHash = $user->getPasswordHash();
        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn((string) $user->getId());
        $this->currentUser->method('getIdentity')->willReturn($identity);

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
        $this->config = new ModuleConfig(enablePasswordExpiration: true);
        $this->harness = new ControllerHarness($this->config);
        $controller = $this->createController();
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['settings' => ['username' => 'testuser', 'email' => 'test@example.com', 'password' => 'secret', 'passwordRepeat' => 'secret']]);

        $this->hydrator->method('hydrate')->willReturnCallback(
            function (object $object, array $data = []): void {
                if (property_exists($object, 'password') && isset($data['password'])) {
                    $object->password = $data['password'];
                }
                if (property_exists($object, 'passwordRepeat') && isset($data['passwordRepeat'])) {
                    $object->passwordRepeat = $data['passwordRepeat'];
                }
                if (property_exists($object, 'username') && isset($data['username'])) {
                    $object->username = $data['username'];
                }
                if (property_exists($object, 'email') && isset($data['email'])) {
                    $object->email = $data['email'];
                }
            },
        );
        $this->validator->method('validate')->willReturn(new Result());
        $user = $this->createUser(passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn((string) $user->getId());
        $this->currentUser->method('getIdentity')->willReturn($identity);

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

    public function testAccountWhenGuestRedirectsToLogin(): void
    {
        $controller = $this->createController();
        $request = new ServerRequest('GET', '/');

        $this->currentUser->method('getIdentity')->willReturn($this->createMock(GuestIdentityInterface::class));

        $response = $this->mockRedirectResponse($this->responseFactory, '//voyti/session-login');

        $result = $controller->update($request);

        $this->assertSame($response, $result);
    }

    public function testAccountWhenUserNotFoundShowsError(): void
    {
        $controller = $this->createController();
        $request = new ServerRequest('GET', '/');

        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn('999999');
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->expects($this->once())
            ->method('withViewPath')
            ->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->willReturn($response);

        $result = $controller->update($request);

        $this->assertSame($response, $result);
    }

    public function testConfirmWhenGuestRedirectsToLogin(): void
    {
        $controller = $this->createController();
        $request = new ServerRequest('GET', '/');

        $this->currentUser->method('getIdentity')->willReturn($this->createMock(GuestIdentityInterface::class));

        $response = $this->mockRedirectResponse($this->responseFactory, '//voyti/session-login');

        $result = $controller->confirm($request, 'good-code');

        $this->assertSame($response, $result);
    }

    #[DataProvider('confirmProvider')]
    public function testConfirmWithCodeShowsMessage(string $code, bool $serviceResult, string $expectedTitle): void
    {
        $controller = $this->createController();
        $request = new ServerRequest('GET', '/');

        $user = $this->createUser(passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn((string) $user->getId());
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $this->emailChangeService->expects($this->once())->method('run')->with(
            $code,
            $this->callback(static fn(User $u): bool => $u->getId() === $user->getId()),
        )->willReturn($serviceResult);

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->method('withViewPath')->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('shared/message', $this->callback(
                static fn(array $params): bool => $params['title'] === $expectedTitle,
            ))
            ->willReturn($response);

        $result = $controller->confirm($request, $code);

        $this->assertSame($response, $result);
    }

    private function createController(): AccountController
    {
        return $this->harness->createAccountController(
            translator: $this->translator,
            viewRenderer: $this->viewRenderer,
            validator: $this->validator,
            currentUser: $this->currentUser,
            responseFactory: $this->responseFactory,
            hydrator: $this->hydrator,
            flash: $this->flash,
            passwordHasher: $this->passwordHasher,
            emailChangeService: $this->emailChangeService,
        );
    }

}
