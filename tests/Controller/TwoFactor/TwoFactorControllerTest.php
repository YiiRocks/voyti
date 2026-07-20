<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Controller\TwoFactor;

use chillerlan\Authenticator\Authenticator;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use YiiRocks\Voyti\Controller\TwoFactor\TwoFactorController;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Model\UserBackupCode;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Service\TwoFactor\BackupCodeService;
use YiiRocks\Voyti\Service\TwoFactor\EmailCodeGeneratorService;
use YiiRocks\Voyti\Service\TwoFactor\QrCodeUriGeneratorService;
use YiiRocks\Voyti\tests\Support\ControllerHarness;
use YiiRocks\Voyti\tests\Support\DatabaseSetupTrait;
use YiiRocks\Voyti\tests\Support\RedirectResponseMockTrait;
use YiiRocks\Voyti\tests\Support\TestPasswordHasherFactory;
use YiiRocks\Voyti\tests\Support\UserFactoryTrait;
use YiiRocks\Voyti\tests\TestCase;
use Yiisoft\Security\PasswordHasher;
use Yiisoft\Session\Flash\FlashInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\User\CurrentUser;
use Yiisoft\User\Guest\GuestIdentityInterface;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

#[AllowMockObjectsWithoutExpectations]
final class TwoFactorControllerTest extends TestCase
{
    use DatabaseSetupTrait;
    use RedirectResponseMockTrait;
    use UserFactoryTrait;

    private ModuleConfig $config;
    private CurrentUser&MockObject $currentUser;
    private FlashInterface&MockObject $flash;
    private ControllerHarness $harness;
    private PasswordHasher $passwordHasher;
    private ResponseFactoryInterface&MockObject $responseFactory;
    private TranslatorInterface $translator;
    private EmailCodeGeneratorService&MockObject $twoFactorEmailCodeService;
    private QrCodeUriGeneratorService&MockObject $twoFactorQrCodeService;
    private WebViewRenderer&MockObject $viewRenderer;

    protected function setUp(): void
    {
        $this->setUpDatabase();
        $this->config = new ModuleConfig(enableTwoFactorAuthentication: true);
        $this->harness = new ControllerHarness($this->config);
        $this->translator = $this->createTranslator();
        $this->viewRenderer = $this->createMock(WebViewRenderer::class);
        $this->currentUser = $this->createMock(CurrentUser::class);
        $this->responseFactory = $this->createMock(ResponseFactoryInterface::class);
        $this->flash = $this->createMock(FlashInterface::class);
        $this->passwordHasher = TestPasswordHasherFactory::create();
        $this->twoFactorQrCodeService = $this->createMock(QrCodeUriGeneratorService::class);
        $this->twoFactorEmailCodeService = $this->createMock(EmailCodeGeneratorService::class);
    }

    protected function tearDown(): void
    {
        $this->tearDownDatabase();
    }

    public function testTwoFactorDisableSendCodeSendsCodeAndRendersView(): void
    {
        $controller = $this->createController();
        $request = new ServerRequest('POST', '/');

        $user = $this->createUser(authTfEnabled: true, authTfType: 'email', passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn((string) $user->getId());
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $this->twoFactorEmailCodeService->expects($this->once())->method('run')->with(
            $this->callback(static fn(User $u): bool => $u->getId() === $user->getId()),
        );

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->method('withViewPath')->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('two-factor/index', $this->callback(
                static fn(array $params): bool => $params['data']->method === 'email'
                    && $params['data']->emailCodeSent === true
                    && $params['data']->preloadContent === true,
            ))
            ->willReturn($response);

        $result = $controller->disableSendCode($request);

        $this->assertSame($response, $result);
    }

    public function testTwoFactorDisableSendCodeWhenGoogleMethodRedirects(): void
    {
        $controller = $this->createController();
        $request = new ServerRequest('POST', '/');

        $user = $this->createUser(authTfEnabled: true, authTfType: 'google', passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn((string) $user->getId());
        $this->currentUser->method('getIdentity')->willReturn($identity);
        $this->twoFactorEmailCodeService->expects($this->never())->method('run');

        $response = $this->mockRedirectResponse($this->responseFactory);

        $result = $controller->disableSendCode($request);

        $this->assertSame($response, $result);
    }

    public function testTwoFactorDisableSendCodeWhenGuestShowsError(): void
    {
        $this->assertGuestShowsError(
            static fn(TwoFactorController $controller): ResponseInterface => $controller->disableSendCode(new ServerRequest('POST', '/')),
        );
    }

    public function testTwoFactorDisableSendCodeWhenNotEnabledRedirects(): void
    {
        $controller = $this->createController();
        $request = new ServerRequest('POST', '/');

        $user = $this->createUser(authTfEnabled: false, passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn((string) $user->getId());
        $this->currentUser->method('getIdentity')->willReturn($identity);
        $this->twoFactorEmailCodeService->expects($this->never())->method('run');

        $response = $this->mockRedirectResponse($this->responseFactory);

        $result = $controller->disableSendCode($request);

        $this->assertSame($response, $result);
    }

    public function testTwoFactorDisableWhenGuestShowsError(): void
    {
        $this->assertGuestShowsError(
            static fn(TwoFactorController $controller): ResponseInterface => $controller->disable(new ServerRequest('POST', '/')),
        );
    }

    public function testTwoFactorDisableWithInvalidEmailCodeShowsFormWithCodeSent(): void
    {
        $controller = $this->createController();
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['code' => 'wrong']);

        $user = $this->createUser(authTfType: 'email', authTfKey: '123456', passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn((string) $user->getId());
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->method('withViewPath')->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('two-factor/index', $this->callback(
                static fn(array $params): bool => $params['data']->method === 'email'
                    && $params['data']->emailCodeSent === true
                    && $params['data']->preloadContent === true
                    && $params['data']->errors !== [],
            ))
            ->willReturn($response);

        $result = $controller->disable($request);

        $this->assertSame($response, $result);
        $updated = User::findById((int) $user->getId());
        $this->assertNotNull($updated);
        $this->assertFalse($updated->isAuthTfEnabled());
    }

    public function testTwoFactorDisableWithInvalidGoogleCodeShowsFormWithoutCodeSent(): void
    {
        $controller = $this->createController();
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['code' => 'wrong']);

        $user = $this->createUser(authTfType: 'google', authTfKey: null, passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn((string) $user->getId());
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->method('withViewPath')->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('two-factor/index', $this->callback(
                static fn(array $params): bool => $params['data']->method === 'google'
                    && $params['data']->emailCodeSent === false
                    && $params['data']->preloadContent === true
                    && $params['data']->errors !== [],
            ))
            ->willReturn($response);

        $result = $controller->disable($request);

        $this->assertSame($response, $result);
        $updated = User::findById((int) $user->getId());
        $this->assertNotNull($updated);
        $this->assertFalse($updated->isAuthTfEnabled());
    }

    public function testTwoFactorDisableWithValidBackupCodeDisablesAndRedirects(): void
    {
        $backupCodeService = new BackupCodeService($this->passwordHasher);

        $user = $this->createUser(authTfEnabled: true, authTfType: 'email', authTfKey: '123456', passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $codes = $backupCodeService->generate($user);

        $controller = $this->createController(backupCodeService: $backupCodeService);
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['code' => $codes[0]]);

        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn((string) $user->getId());
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $response = $this->mockRedirectResponse($this->responseFactory);

        $result = $controller->disable($request);

        $this->assertSame($response, $result);
        $updated = User::findById((int) $user->getId());
        $this->assertNotNull($updated);
        $this->assertFalse($updated->isAuthTfEnabled());
        $this->assertFalse($backupCodeService->hasUnused($updated));
        $this->assertCount(0, UserBackupCode::query()->where(['user_id' => $updated->getIdOrZero()])->all());
    }

    public function testTwoFactorDisableWithValidEmailCodeDisablesAndRedirects(): void
    {
        $controller = $this->createController();
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['code' => '123456']);

        $user = $this->createUser(authTfEnabled: true, authTfType: 'email', authTfKey: '123456', passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn((string) $user->getId());
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $response = $this->mockRedirectResponse($this->responseFactory);

        $result = $controller->disable($request);

        $this->assertSame($response, $result);
        $updated = User::findById((int) $user->getId());
        $this->assertNotNull($updated);
        $this->assertFalse($updated->isAuthTfEnabled());
        $this->assertNull($updated->getAuthTfKey());
        $this->assertNull($updated->getAuthTfType());
    }

    public function testTwoFactorDisableWithValidGoogleCodeDisablesAndRedirects(): void
    {
        $secret = (new Authenticator())->createSecret();
        $authenticator = new Authenticator();
        $authenticator->setSecret($secret);
        $code = $authenticator->code();

        $controller = $this->createController();
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['code' => $code]);

        $identity = $this->createMock(User::class);
        $user = $this->createUser(authTfEnabled: true, authTfType: 'google', authTfKey: $secret, passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $identity->method('getId')->willReturn((string) $user->getId());
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $response = $this->mockRedirectResponse($this->responseFactory);

        $result = $controller->disable($request);

        $this->assertSame($response, $result);
        $updated = User::findById((int) $user->getId());
        $this->assertNotNull($updated);
        $this->assertFalse($updated->isAuthTfEnabled());
        $this->assertNull($updated->getAuthTfKey());
        $this->assertNull($updated->getAuthTfType());
    }

    public function testTwoFactorEmailRendersFragmentWithFragmentHeader(): void
    {
        $controller = $this->createController();
        $request = (new ServerRequest('GET', '/'))->withHeader('X-Requested-With', 'XMLHttpRequest');

        $user = $this->createUser(authTfEnabled: false, passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn((string) $user->getId());
        $this->currentUser->method('getIdentity')->willReturn($identity);
        $this->twoFactorEmailCodeService->expects($this->never())->method('run');

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->expects($this->once())
            ->method('withViewPath')
            ->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('renderPartial')
            ->with('two-factor/_email', $this->callback(
                static fn(array $params): bool => $params['data']->emailCodeSent === false,
            ))
            ->willReturn($response);

        $result = $controller->email($request);

        $this->assertSame($response, $result);
    }

    public function testTwoFactorEmailRendersShellWithoutFragmentHeader(): void
    {
        $controller = $this->createController();
        $request = new ServerRequest('GET', '/');

        $user = $this->createUser(authTfEnabled: false, passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn((string) $user->getId());
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->expects($this->once())
            ->method('withViewPath')
            ->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('two-factor/index', $this->callback(
                static fn(array $params): bool => $params['data']->method === 'email'
                    && $params['data']->emailCodeSent === false
                    && $params['data']->preloadContent === true,
            ))
            ->willReturn($response);

        $result = $controller->email($request);

        $this->assertSame($response, $result);
    }

    public function testTwoFactorEmailWhenAlreadyEnabledRedirects(): void
    {
        $controller = $this->createController();
        $request = new ServerRequest('GET', '/');

        $user = $this->createUser(authTfEnabled: true, passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn((string) $user->getId());
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $response = $this->mockRedirectResponse($this->responseFactory);

        $result = $controller->email($request);

        $this->assertSame($response, $result);
    }

    public function testTwoFactorEmailWhenGuestShowsError(): void
    {
        $this->assertGuestShowsError(
            static fn(TwoFactorController $controller): ResponseInterface => $controller->email(new ServerRequest('GET', '/')),
        );
    }

    public function testTwoFactorEnableWhenAlreadyEnabledRedirects(): void
    {
        $controller = $this->createController();
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['method' => 'google', 'code' => '123456']);

        $user = $this->createUser(authTfEnabled: true, passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn((string) $user->getId());
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $response = $this->mockRedirectResponse($this->responseFactory);

        $result = $controller->enable($request);

        $this->assertSame($response, $result);
        $updated = User::findById((int) $user->getId());
        $this->assertNotNull($updated);
        $this->assertTrue($updated->isAuthTfEnabled());
    }

    public function testTwoFactorEnableWhenGuestShowsError(): void
    {
        $this->assertGuestShowsError(
            static fn(TwoFactorController $controller): ResponseInterface => $controller->enable(
                (new ServerRequest('POST', '/'))->withParsedBody(['method' => 'google', 'code' => '123456']),
            ),
        );
    }

    public function testTwoFactorEnableWithEmailCode(): void
    {
        $controller = $this->createController();
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['method' => 'email', 'code' => '123456']);

        $user = $this->createUser(authTfKey: '123456', passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn((string) $user->getId());
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->method('withViewPath')->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('two-factor/backup-codes', $this->callback(
                static fn(array $params): bool => count($params['data']->codes) === 10,
            ))
            ->willReturn($response);

        $result = $controller->enable($request);

        $this->assertSame($response, $result);
        $updated = User::findById((int) $user->getId());
        $this->assertNotNull($updated);
        $this->assertTrue($updated->isAuthTfEnabled());
        $this->assertSame('email', $updated->getAuthTfType());
    }

    public function testTwoFactorEnableWithInvalidEmailCodeShowsFormWithCodeSent(): void
    {
        $controller = $this->createController();
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['method' => 'email', 'code' => 'wrong']);

        $user = $this->createUser(authTfKey: '123456', passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn((string) $user->getId());
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->method('withViewPath')->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('two-factor/index', $this->callback(
                static fn(array $params): bool => $params['data']->method === 'email'
                    && $params['data']->emailCodeSent === true
                    && $params['data']->preloadContent === true,
            ))
            ->willReturn($response);

        $result = $controller->enable($request);

        $this->assertSame($response, $result);
        $updated = User::findById((int) $user->getId());
        $this->assertNotNull($updated);
        $this->assertFalse($updated->isAuthTfEnabled());
    }

    public function testTwoFactorEnableWithInvalidGoogleCodeShowsFormWithoutCodeSent(): void
    {
        $controller = $this->createController();
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['method' => 'google', 'code' => 'wrong']);

        $user = $this->createUser(authTfType: 'google', authTfKey: null, passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn((string) $user->getId());
        $this->currentUser->method('getIdentity')->willReturn($identity);
        $this->twoFactorQrCodeService->method('generateQrCodeSvg')->willReturn('<svg></svg>');

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->method('withViewPath')->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('two-factor/index', $this->callback(
                static fn(array $params): bool => $params['data']->method === 'google'
                    && $params['data']->emailCodeSent === false
                    && $params['data']->preloadContent === true,
            ))
            ->willReturn($response);

        $result = $controller->enable($request);

        $this->assertSame($response, $result);
        $updated = User::findById((int) $user->getId());
        $this->assertNotNull($updated);
        $this->assertFalse($updated->isAuthTfEnabled());
    }

    public function testTwoFactorEnableWithValidGoogleCodeEnablesAndRedirects(): void
    {
        $secret = (new Authenticator())->createSecret();
        $authenticator = new Authenticator();
        $authenticator->setSecret($secret);
        $code = $authenticator->code();

        $controller = $this->createController();
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['method' => 'google', 'code' => $code]);

        $user = $this->createUser(authTfType: 'google', authTfKey: $secret, passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn((string) $user->getId());
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->method('withViewPath')->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('two-factor/backup-codes', $this->callback(
                static fn(array $params): bool => count($params['data']->codes) === 10,
            ))
            ->willReturn($response);

        $result = $controller->enable($request);

        $this->assertSame($response, $result);
        $updated = User::findById((int) $user->getId());
        $this->assertNotNull($updated);
        $this->assertTrue($updated->isAuthTfEnabled());
        $this->assertSame('google', $updated->getAuthTfType());
    }

    public function testTwoFactorGoogleRendersFragmentWithFragmentHeader(): void
    {
        $controller = $this->createController();
        $request = (new ServerRequest('GET', '/'))->withHeader('X-Requested-With', 'XMLHttpRequest');

        $user = $this->createUser(authTfEnabled: false, authTfType: null, authTfKey: 'secret', passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn((string) $user->getId());
        $this->currentUser->method('getIdentity')->willReturn($identity);
        $this->twoFactorQrCodeService->method('generateQrCodeSvg')->willReturn('<svg></svg>');

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->expects($this->once())
            ->method('withViewPath')
            ->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('renderPartial')
            ->with('two-factor/_google', $this->callback(
                static fn(array $params): bool => $params['data']->qrCodeUri === '<svg></svg>' && $params['data']->secret === null,
            ))
            ->willReturn($response);

        $result = $controller->google($request);

        $this->assertSame($response, $result);
    }

    public function testTwoFactorGoogleRendersShellWithoutFragmentHeader(): void
    {
        $controller = $this->createController();
        $request = new ServerRequest('GET', '/');

        $user = $this->createUser(authTfEnabled: false, authTfType: null, authTfKey: null, passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn((string) $user->getId());
        $this->currentUser->method('getIdentity')->willReturn($identity);
        $this->twoFactorQrCodeService->method('generateQrCodeSvg')->willReturn('<svg></svg>');

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->expects($this->once())
            ->method('withViewPath')
            ->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('two-factor/index', $this->callback(
                static fn(array $params): bool => $params['data']->method === 'google'
                    && $params['data']->emailCodeSent === false
                    && $params['data']->preloadContent === true,
            ))
            ->willReturn($response);

        $result = $controller->google($request);

        $this->assertSame($response, $result);
    }

    public function testTwoFactorGoogleWhenAlreadyEnabledRedirects(): void
    {
        $controller = $this->createController();
        $request = new ServerRequest('GET', '/');

        $user = $this->createUser(authTfEnabled: true, passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn((string) $user->getId());
        $this->currentUser->method('getIdentity')->willReturn($identity);
        $this->twoFactorQrCodeService->expects($this->never())->method('generateQrCodeSvg');

        $response = $this->mockRedirectResponse($this->responseFactory);

        $result = $controller->google($request);

        $this->assertSame($response, $result);
    }

    public function testTwoFactorGoogleWhenGuestShowsError(): void
    {
        $this->assertGuestShowsError(
            static fn(TwoFactorController $controller): ResponseInterface => $controller->google(new ServerRequest('GET', '/')),
        );
    }

    public function testTwoFactorIndexReportsHasBackupCodesWhenCodesExist(): void
    {
        $backupCodeService = new BackupCodeService($this->passwordHasher);

        $user = $this->createUser(authTfEnabled: true, authTfType: 'google', authTfKey: '123456', passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $backupCodeService->generate($user);

        $controller = $this->createController($backupCodeService);
        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn((string) $user->getId());
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->method('withViewPath')->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('two-factor/index', $this->callback(
                static fn(array $params): bool => $params['data']->hasBackupCodes === true,
            ))
            ->willReturn($response);

        $result = $controller->index(new ServerRequest('GET', '/'));

        $this->assertSame($response, $result);
    }

    public function testTwoFactorIndexReportsNoBackupCodesWhenNoneRemain(): void
    {
        $user = $this->createUser(authTfEnabled: true, authTfType: 'google', authTfKey: '123456', passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());

        $controller = $this->createController();
        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn((string) $user->getId());
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->method('withViewPath')->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('two-factor/index', $this->callback(
                static fn(array $params): bool => $params['data']->hasBackupCodes === false,
            ))
            ->willReturn($response);

        $result = $controller->index(new ServerRequest('GET', '/'));

        $this->assertSame($response, $result);
    }

    public function testTwoFactorIndexWhenUserNotFoundShowsError(): void
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

        $result = $controller->index($request);

        $this->assertSame($response, $result);
    }

    public function testTwoFactorRegenerateBackupCodesWhenGuestShowsError(): void
    {
        $this->assertGuestShowsError(
            static fn(TwoFactorController $controller): ResponseInterface => $controller->regenerateBackupCodes(new ServerRequest('POST', '/')),
        );
    }

    public function testTwoFactorRegenerateBackupCodesWhenNotEnabledRedirects(): void
    {
        $controller = $this->createController();
        $request = new ServerRequest('POST', '/');

        $user = $this->createUser(authTfEnabled: false, passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn((string) $user->getId());
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $response = $this->mockRedirectResponse($this->responseFactory);

        $result = $controller->regenerateBackupCodes($request);

        $this->assertSame($response, $result);
    }

    public function testTwoFactorRegenerateBackupCodesWithInvalidCodeShowsForm(): void
    {
        $controller = $this->createController();
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['code' => 'wrong']);

        $user = $this->createUser(authTfEnabled: true, authTfType: 'email', authTfKey: '123456', passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn((string) $user->getId());
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->method('withViewPath')->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('two-factor/index', $this->callback(
                static fn(array $params): bool => $params['data']->method === 'email',
            ))
            ->willReturn($response);

        $result = $controller->regenerateBackupCodes($request);

        $this->assertSame($response, $result);
    }

    public function testTwoFactorRegenerateBackupCodesWithValidCodeShowsNewCodes(): void
    {
        $user = $this->createUser(authTfEnabled: true, authTfType: 'email', authTfKey: '123456', passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $controller = $this->createController();
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['code' => '123456']);

        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn((string) $user->getId());
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->method('withViewPath')->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('two-factor/backup-codes', $this->callback(
                static fn(array $params): bool => count($params['data']->codes) === 10,
            ))
            ->willReturn($response);

        $result = $controller->regenerateBackupCodes($request);

        $this->assertSame($response, $result);
    }

    public function testTwoFactorRegenerateBackupCodesWithValidGoogleCodeShowsNewCodes(): void
    {
        $secret = (new Authenticator())->createSecret();
        $authenticator = new Authenticator();
        $authenticator->setSecret($secret);
        $code = $authenticator->code();

        $user = $this->createUser(authTfEnabled: true, authTfType: 'google', authTfKey: $secret, passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $controller = $this->createController();
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['code' => $code]);

        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn((string) $user->getId());
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->method('withViewPath')->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('two-factor/backup-codes', $this->callback(
                static fn(array $params): bool => count($params['data']->codes) === 10,
            ))
            ->willReturn($response);

        $result = $controller->regenerateBackupCodes($request);

        $this->assertSame($response, $result);
    }

    public function testTwoFactorRenewDoesNotResetTypeWhenAlreadyGoogle(): void
    {
        $controller = $this->createController();
        $request = new ServerRequest('POST', '/');

        $user = $this->createUser(authTfEnabled: false, authTfType: 'google', authTfKey: 'secret', passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn((string) $user->getId());
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $this->twoFactorQrCodeService->method('isAvailable')->willReturn(true);
        $this->twoFactorQrCodeService->method('generateQrCodeSvg')->willReturn('<svg></svg>');

        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->method('createResponse')->willReturn($response);
        $response->method('withHeader')->willReturnSelf();
        $response->method('getBody')->willReturn($this->createMock(StreamInterface::class));

        $result = $controller->renew($request);

        $this->assertSame($response, $result);
        $updated = User::findById((int) $user->getId());
        $this->assertNotNull($updated);
        $this->assertSame('google', $updated->getAuthTfType());
    }

    public function testTwoFactorRenewGeneratesNewSecret(): void
    {
        $controller = $this->createController();
        $request = new ServerRequest('POST', '/');

        $user = $this->createUser(authTfEnabled: false, authTfType: 'email', authTfKey: 'new-secret', passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn((string) $user->getId());
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $this->twoFactorQrCodeService->method('isAvailable')->willReturn(true);
        $this->twoFactorQrCodeService->expects($this->once())
            ->method('regenerateQrCodeSvg')
            ->with($this->callback(static fn(User $u): bool => $u->getId() === $user->getId()))
            ->willReturn('<svg>new</svg>');

        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->expects($this->once())
            ->method('createResponse')
            ->with(200)
            ->willReturn($response);
        $response->expects($this->once())->method('withHeader')->willReturnSelf();

        $body = $this->createMock(StreamInterface::class);
        $body->expects($this->once())
            ->method('write')
            ->with($this->callback(
                static fn(string $json): bool => json_decode($json, true) === ['qrCodeUri' => '<svg>new</svg>', 'secret' => 'new-secret'],
            ));
        $response->method('getBody')->willReturn($body);

        $result = $controller->renew($request);

        $this->assertSame($response, $result);
    }

    public function testTwoFactorRenewWhenAlreadyEnabledReturnsError(): void
    {
        $controller = $this->createController();
        $request = new ServerRequest('POST', '/');

        $user = $this->createUser(authTfEnabled: true, passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn((string) $user->getId());
        $this->currentUser->method('getIdentity')->willReturn($identity);
        $this->twoFactorQrCodeService->expects($this->never())->method('regenerateQrCodeSvg');

        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->expects($this->once())
            ->method('createResponse')
            ->with(403)
            ->willReturn($response);
        $response->method('withHeader')->willReturnSelf();
        $response->method('getBody')->willReturn($this->createMock(StreamInterface::class));

        $result = $controller->renew($request);

        $this->assertSame($response, $result);
    }

    public function testTwoFactorRenewWhenGuestReturnsError(): void
    {
        $controller = $this->createController();
        $request = new ServerRequest('POST', '/');

        $this->currentUser->method('getIdentity')->willReturn($this->createMock(GuestIdentityInterface::class));

        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->expects($this->once())
            ->method('createResponse')
            ->with(401)
            ->willReturn($response);
        $response->method('withHeader')->willReturnSelf();
        $response->method('getBody')->willReturn($this->createMock(StreamInterface::class));

        $result = $controller->renew($request);

        $this->assertSame($response, $result);
    }

    public function testTwoFactorRenewWhenLibraryMissingReturnsError(): void
    {
        $controller = $this->createController();
        $request = new ServerRequest('POST', '/');

        $user = $this->createUser(authTfEnabled: false, passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn((string) $user->getId());
        $this->currentUser->method('getIdentity')->willReturn($identity);
        $this->twoFactorQrCodeService->method('isAvailable')->willReturn(false);
        $this->twoFactorQrCodeService->expects($this->never())->method('regenerateQrCodeSvg');

        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->expects($this->once())
            ->method('createResponse')
            ->with(503)
            ->willReturn($response);
        $response->method('withHeader')->willReturnSelf();
        $response->method('getBody')->willReturn($this->createMock(StreamInterface::class));

        $result = $controller->renew($request);

        $this->assertSame($response, $result);
    }

    public function testTwoFactorRenewWhenUserNotFoundReturnsError(): void
    {
        $controller = $this->createController();
        $request = new ServerRequest('POST', '/');

        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn('999999');
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->expects($this->once())
            ->method('createResponse')
            ->with(404)
            ->willReturn($response);
        $response->method('withHeader')->willReturnSelf();
        $response->method('getBody')->willReturn($this->createMock(StreamInterface::class));

        $result = $controller->renew($request);

        $this->assertSame($response, $result);
    }

    public function testTwoFactorSendEmailCodeDoesNotResetTypeWhenAlreadyEmail(): void
    {
        $controller = $this->createController();
        $request = new ServerRequest('POST', '/');

        $user = $this->createUser(authTfEnabled: false, authTfType: 'email', passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn((string) $user->getId());
        $this->currentUser->method('getIdentity')->willReturn($identity);
        $this->twoFactorEmailCodeService->expects($this->once())->method('run')->with(
            $this->callback(static fn(User $u): bool => $u->getId() === $user->getId()),
        );

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->method('withViewPath')->willReturnSelf();
        $this->viewRenderer->method('render')->willReturn($response);

        $result = $controller->sendEmailCode($request);

        $this->assertSame($response, $result);
        $updated = User::findById((int) $user->getId());
        $this->assertNotNull($updated);
        $this->assertSame('email', $updated->getAuthTfType());
    }

    public function testTwoFactorSendEmailCodeSendsCodeAndRendersView(): void
    {
        $controller = $this->createController();
        $request = new ServerRequest('POST', '/');

        $user = $this->createUser(authTfEnabled: false, authTfType: null, passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn((string) $user->getId());
        $this->currentUser->method('getIdentity')->willReturn($identity);
        $this->twoFactorEmailCodeService->expects($this->once())->method('run')->with(
            $this->callback(static fn(User $u): bool => $u->getId() === $user->getId()),
        );

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->method('withViewPath')->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('two-factor/index', $this->callback(
                static fn(array $params): bool => $params['data']->method === 'email'
                    && $params['data']->emailCodeSent === true
                    && $params['data']->preloadContent === true,
            ))
            ->willReturn($response);

        $result = $controller->sendEmailCode($request);

        $this->assertSame($response, $result);
        $updated = User::findById((int) $user->getId());
        $this->assertNotNull($updated);
        $this->assertSame('email', $updated->getAuthTfType());
    }

    public function testTwoFactorSendEmailCodeWhenAlreadyEnabledRedirects(): void
    {
        $controller = $this->createController();
        $request = new ServerRequest('POST', '/');

        $user = $this->createUser(authTfEnabled: true, passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn((string) $user->getId());
        $this->currentUser->method('getIdentity')->willReturn($identity);
        $this->twoFactorEmailCodeService->expects($this->never())->method('run');

        $response = $this->mockRedirectResponse($this->responseFactory);

        $result = $controller->sendEmailCode($request);

        $this->assertSame($response, $result);
    }

    public function testTwoFactorSendEmailCodeWhenGuestShowsError(): void
    {
        $this->assertGuestShowsError(
            static fn(TwoFactorController $controller): ResponseInterface => $controller->sendEmailCode(new ServerRequest('POST', '/')),
        );
    }

    public function testTwoFactorWhenAlreadyEnabledShowsSettings(): void
    {
        $controller = $this->createController();
        $request = new ServerRequest('GET', '/');

        $user = $this->createUser(authTfEnabled: true, authTfType: 'google', passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn((string) $user->getId());
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->expects($this->once())
            ->method('withViewPath')
            ->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('two-factor/index', $this->callback(
                static fn(array $params): bool => $params['data']->emailCodeSent === false
                    && $params['data']->preloadContent === true,
            ))
            ->willReturn($response);

        $result = $controller->index($request);

        $this->assertSame($response, $result);
    }

    public function testTwoFactorWhenGuestShowsError(): void
    {
        $this->assertGuestShowsError(
            static fn(TwoFactorController $controller): ResponseInterface => $controller->index(new ServerRequest('GET', '/')),
        );
    }

    public function testTwoFactorWhenNotEnabledRendersShellWithoutPreloadingContent(): void
    {
        $controller = $this->createController();
        $request = new ServerRequest('GET', '/');

        $user = $this->createUser(authTfEnabled: false, passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn((string) $user->getId());
        $this->currentUser->method('getIdentity')->willReturn($identity);
        $this->twoFactorQrCodeService->expects($this->never())->method('generateQrCodeSvg');

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->expects($this->once())
            ->method('withViewPath')
            ->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('two-factor/index', $this->callback(
                static fn(array $params): bool => $params['data']->method === 'google'
                    && $params['data']->emailCodeSent === false
                    && $params['data']->preloadContent === false,
            ))
            ->willReturn($response);

        $result = $controller->index($request);

        $this->assertSame($response, $result);
    }

    private function assertGuestShowsError(callable $invoke): void
    {
        $controller = $this->createController();

        $this->currentUser->method('getIdentity')->willReturn($this->createMock(GuestIdentityInterface::class));

        $response = $this->mockRedirectResponse($this->responseFactory, '//voyti/session-login');

        $result = $invoke($controller);

        $this->assertSame($response, $result);
    }

    private function createController(?BackupCodeService $backupCodeService = null): TwoFactorController
    {
        return $this->harness->createTwoFactorController(
            translator: $this->translator,
            viewRenderer: $this->viewRenderer,
            currentUser: $this->currentUser,
            responseFactory: $this->responseFactory,
            flash: $this->flash,
            twoFactorQrCodeService: $this->twoFactorQrCodeService,
            twoFactorEmailCodeService: $this->twoFactorEmailCodeService,
            backupCodeService: $backupCodeService,
        );
    }

}
