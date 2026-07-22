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
        $user = $this->createUser(authTfEnabled: true, authTfType: 'email', passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $this->currentUser->method('getIdentity')->willReturn($user);

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

        $controller = $this->createController();
        $result = $controller->disableSendCode();

        $this->assertSame($response, $result);
    }

    public function testTwoFactorDisableSendCodeWhenGoogleMethodRedirects(): void
    {
        $user = $this->createUser(authTfEnabled: true, authTfType: 'google', passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $this->currentUser->method('getIdentity')->willReturn($user);
        $this->twoFactorEmailCodeService->expects($this->never())->method('run');

        $response = $this->mockRedirectResponse($this->responseFactory);

        $controller = $this->createController();
        $result = $controller->disableSendCode();

        $this->assertSame($response, $result);
    }

    public function testTwoFactorDisableSendCodeWhenNotEnabledRedirects(): void
    {
        $user = $this->createUser(authTfEnabled: false, passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $this->currentUser->method('getIdentity')->willReturn($user);
        $this->twoFactorEmailCodeService->expects($this->never())->method('run');

        $response = $this->mockRedirectResponse($this->responseFactory);

        $controller = $this->createController();
        $result = $controller->disableSendCode();

        $this->assertSame($response, $result);
    }

    public function testTwoFactorDisableWithInvalidEmailCodeShowsFormWithCodeSent(): void
    {
        $user = $this->createUser(authTfType: 'email', authTfKey: '123456', passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $this->currentUser->method('getIdentity')->willReturn($user);

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

        $controller = $this->createController();
        $result = $controller->disable(code: 'wrong');

        $this->assertSame($response, $result);
        $updated = User::findById((int) $user->getId());
        $this->assertNotNull($updated);
        $this->assertFalse($updated->isAuthTfEnabled());
    }

    public function testTwoFactorDisableWithInvalidGoogleCodeShowsFormWithoutCodeSent(): void
    {
        $user = $this->createUser(authTfType: 'google', authTfKey: null, passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $this->currentUser->method('getIdentity')->willReturn($user);

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

        $controller = $this->createController();
        $result = $controller->disable(code: 'wrong');

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

        $this->currentUser->method('getIdentity')->willReturn($user);

        $response = $this->mockRedirectResponse($this->responseFactory);

        $controller = $this->createController(backupCodeService: $backupCodeService);
        $result = $controller->disable(code: $codes[0]);

        $this->assertSame($response, $result);
        $updated = User::findById((int) $user->getId());
        $this->assertNotNull($updated);
        $this->assertFalse($updated->isAuthTfEnabled());
        $this->assertFalse($backupCodeService->hasUnused($updated));
        $this->assertCount(0, UserBackupCode::query()->where(['user_id' => $updated->getIdOrZero()])->all());
    }

    public function testTwoFactorDisableWithValidEmailCodeDisablesAndRedirects(): void
    {
        $user = $this->createUser(authTfEnabled: true, authTfType: 'email', authTfKey: '123456', passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $this->currentUser->method('getIdentity')->willReturn($user);

        $response = $this->mockRedirectResponse($this->responseFactory);

        $controller = $this->createController();
        $result = $controller->disable(code: '123456');

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

        $user = $this->createUser(authTfEnabled: true, authTfType: 'google', authTfKey: $secret, passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $this->currentUser->method('getIdentity')->willReturn($user);

        $response = $this->mockRedirectResponse($this->responseFactory);

        $controller = $this->createController();
        $result = $controller->disable(code: $code);

        $this->assertSame($response, $result);
        $updated = User::findById((int) $user->getId());
        $this->assertNotNull($updated);
        $this->assertFalse($updated->isAuthTfEnabled());
        $this->assertNull($updated->getAuthTfKey());
        $this->assertNull($updated->getAuthTfType());
    }

    public function testTwoFactorEmailRendersFragmentWithFragmentHeader(): void
    {
        $user = $this->createUser(authTfEnabled: false, passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $this->currentUser->method('getIdentity')->willReturn($user);
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

        $request = (new ServerRequest('GET', '/'))->withHeader('X-Requested-With', 'XMLHttpRequest');
        $controller = $this->createController();
        $result = $controller->email($request);

        $this->assertSame($response, $result);
    }

    public function testTwoFactorEmailRendersShellWithoutFragmentHeader(): void
    {
        $user = $this->createUser(authTfEnabled: false, passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $this->currentUser->method('getIdentity')->willReturn($user);

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

        $request = new ServerRequest('GET', '/');
        $controller = $this->createController();
        $result = $controller->email($request);

        $this->assertSame($response, $result);
    }

    public function testTwoFactorEmailWhenAlreadyEnabledRedirects(): void
    {
        $user = $this->createUser(authTfEnabled: true, passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $this->currentUser->method('getIdentity')->willReturn($user);

        $response = $this->mockRedirectResponse($this->responseFactory);

        $request = new ServerRequest('GET', '/');
        $controller = $this->createController();
        $result = $controller->email($request);

        $this->assertSame($response, $result);
    }

    public function testTwoFactorEnableWhenAlreadyEnabledRedirects(): void
    {
        $user = $this->createUser(authTfEnabled: true, passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $this->currentUser->method('getIdentity')->willReturn($user);

        $response = $this->mockRedirectResponse($this->responseFactory);

        $controller = $this->createController();
        $result = $controller->enable(method: 'google', code: '123456');

        $this->assertSame($response, $result);
        $updated = User::findById((int) $user->getId());
        $this->assertNotNull($updated);
        $this->assertTrue($updated->isAuthTfEnabled());
    }

    public function testTwoFactorEnableWithEmailCode(): void
    {
        $user = $this->createUser(authTfKey: '123456', passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $this->currentUser->method('getIdentity')->willReturn($user);

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->method('withViewPath')->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('two-factor/backup-codes', $this->callback(
                static fn(array $params): bool => count($params['data']->codes) === 10,
            ))
            ->willReturn($response);

        $controller = $this->createController();
        $result = $controller->enable(method: 'email', code: '123456');

        $this->assertSame($response, $result);
        $updated = User::findById((int) $user->getId());
        $this->assertNotNull($updated);
        $this->assertTrue($updated->isAuthTfEnabled());
        $this->assertSame('email', $updated->getAuthTfType());
    }

    public function testTwoFactorEnableWithInvalidEmailCodeShowsFormWithCodeSent(): void
    {
        $user = $this->createUser(authTfKey: '123456', passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $this->currentUser->method('getIdentity')->willReturn($user);

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

        $controller = $this->createController();
        $result = $controller->enable(method: 'email', code: 'wrong');

        $this->assertSame($response, $result);
        $updated = User::findById((int) $user->getId());
        $this->assertNotNull($updated);
        $this->assertFalse($updated->isAuthTfEnabled());
    }

    public function testTwoFactorEnableWithInvalidGoogleCodeShowsFormWithoutCodeSent(): void
    {
        $user = $this->createUser(authTfType: 'google', authTfKey: null, passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $this->currentUser->method('getIdentity')->willReturn($user);
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

        $controller = $this->createController();
        $result = $controller->enable(method: 'google', code: 'wrong');

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

        $user = $this->createUser(authTfType: 'google', authTfKey: $secret, passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $this->currentUser->method('getIdentity')->willReturn($user);

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->method('withViewPath')->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('two-factor/backup-codes', $this->callback(
                static fn(array $params): bool => count($params['data']->codes) === 10,
            ))
            ->willReturn($response);

        $controller = $this->createController();
        $result = $controller->enable(method: 'google', code: $code);

        $this->assertSame($response, $result);
        $updated = User::findById((int) $user->getId());
        $this->assertNotNull($updated);
        $this->assertTrue($updated->isAuthTfEnabled());
        $this->assertSame('google', $updated->getAuthTfType());
    }

    public function testTwoFactorGoogleRendersFragmentWithFragmentHeader(): void
    {
        $user = $this->createUser(authTfEnabled: false, authTfType: null, authTfKey: 'secret', passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $this->currentUser->method('getIdentity')->willReturn($user);
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

        $request = (new ServerRequest('GET', '/'))->withHeader('X-Requested-With', 'XMLHttpRequest');
        $controller = $this->createController();
        $result = $controller->google($request);

        $this->assertSame($response, $result);
    }

    public function testTwoFactorGoogleRendersShellWithoutFragmentHeader(): void
    {
        $user = $this->createUser(authTfEnabled: false, authTfType: null, authTfKey: null, passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $this->currentUser->method('getIdentity')->willReturn($user);
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

        $request = new ServerRequest('GET', '/');
        $controller = $this->createController();
        $result = $controller->google($request);

        $this->assertSame($response, $result);
    }

    public function testTwoFactorGoogleWhenAlreadyEnabledRedirects(): void
    {
        $user = $this->createUser(authTfEnabled: true, passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $this->currentUser->method('getIdentity')->willReturn($user);
        $this->twoFactorQrCodeService->expects($this->never())->method('generateQrCodeSvg');

        $response = $this->mockRedirectResponse($this->responseFactory);

        $request = new ServerRequest('GET', '/');
        $controller = $this->createController();
        $result = $controller->google($request);

        $this->assertSame($response, $result);
    }

    public function testTwoFactorIndexReportsHasBackupCodesWhenCodesExist(): void
    {
        $backupCodeService = new BackupCodeService($this->passwordHasher);

        $user = $this->createUser(authTfEnabled: true, authTfType: 'google', authTfKey: '123456', passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $backupCodeService->generate($user);

        $this->currentUser->method('getIdentity')->willReturn($user);

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->method('withViewPath')->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('two-factor/index', $this->callback(
                static fn(array $params): bool => $params['data']->hasBackupCodes === true,
            ))
            ->willReturn($response);

        $controller = $this->createController($backupCodeService);
        $result = $controller->index();

        $this->assertSame($response, $result);
    }

    public function testTwoFactorIndexReportsNoBackupCodesWhenNoneRemain(): void
    {
        $user = $this->createUser(authTfEnabled: true, authTfType: 'google', authTfKey: '123456', passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());

        $this->currentUser->method('getIdentity')->willReturn($user);

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->method('withViewPath')->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('two-factor/index', $this->callback(
                static fn(array $params): bool => $params['data']->hasBackupCodes === false,
            ))
            ->willReturn($response);

        $controller = $this->createController();
        $result = $controller->index();

        $this->assertSame($response, $result);
    }

    public function testTwoFactorRegenerateBackupCodesWhenNotEnabledRedirects(): void
    {
        $user = $this->createUser(authTfEnabled: false, passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $this->currentUser->method('getIdentity')->willReturn($user);

        $response = $this->mockRedirectResponse($this->responseFactory);

        $controller = $this->createController();
        $result = $controller->regenerateBackupCodes();

        $this->assertSame($response, $result);
    }

    public function testTwoFactorRegenerateBackupCodesWithInvalidCodeShowsForm(): void
    {
        $user = $this->createUser(authTfEnabled: true, authTfType: 'email', authTfKey: '123456', passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $this->currentUser->method('getIdentity')->willReturn($user);

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->method('withViewPath')->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('two-factor/index', $this->callback(
                static fn(array $params): bool => $params['data']->method === 'email',
            ))
            ->willReturn($response);

        $controller = $this->createController();
        $result = $controller->regenerateBackupCodes(code: 'wrong');

        $this->assertSame($response, $result);
    }

    public function testTwoFactorRegenerateBackupCodesWithValidCodeShowsNewCodes(): void
    {
        $user = $this->createUser(authTfEnabled: true, authTfType: 'email', authTfKey: '123456', passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());

        $this->currentUser->method('getIdentity')->willReturn($user);

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->method('withViewPath')->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('two-factor/backup-codes', $this->callback(
                static fn(array $params): bool => count($params['data']->codes) === 10,
            ))
            ->willReturn($response);

        $controller = $this->createController();
        $result = $controller->regenerateBackupCodes(code: '123456');

        $this->assertSame($response, $result);
    }

    public function testTwoFactorRegenerateBackupCodesWithValidGoogleCodeShowsNewCodes(): void
    {
        $secret = (new Authenticator())->createSecret();
        $authenticator = new Authenticator();
        $authenticator->setSecret($secret);
        $code = $authenticator->code();

        $user = $this->createUser(authTfEnabled: true, authTfType: 'google', authTfKey: $secret, passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());

        $this->currentUser->method('getIdentity')->willReturn($user);

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->method('withViewPath')->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('two-factor/backup-codes', $this->callback(
                static fn(array $params): bool => count($params['data']->codes) === 10,
            ))
            ->willReturn($response);

        $controller = $this->createController();
        $result = $controller->regenerateBackupCodes(code: $code);

        $this->assertSame($response, $result);
    }

    public function testTwoFactorRenewDoesNotResetTypeWhenAlreadyGoogle(): void
    {
        $user = $this->createUser(authTfEnabled: false, authTfType: 'google', authTfKey: 'secret', passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $this->currentUser->method('getIdentity')->willReturn($user);

        $this->twoFactorQrCodeService->method('isAvailable')->willReturn(true);
        $this->twoFactorQrCodeService->method('generateQrCodeSvg')->willReturn('<svg></svg>');

        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->method('createResponse')->willReturn($response);
        $response->method('withHeader')->willReturnSelf();
        $response->method('getBody')->willReturn($this->createMock(StreamInterface::class));

        $controller = $this->createController();
        $result = $controller->renew();

        $this->assertSame($response, $result);
        $updated = User::findById((int) $user->getId());
        $this->assertNotNull($updated);
        $this->assertSame('google', $updated->getAuthTfType());
    }

    public function testTwoFactorRenewGeneratesNewSecret(): void
    {
        $user = $this->createUser(authTfEnabled: false, authTfType: 'email', authTfKey: 'new-secret', passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $this->currentUser->method('getIdentity')->willReturn($user);

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

        $controller = $this->createController();
        $result = $controller->renew();

        $this->assertSame($response, $result);
    }

    public function testTwoFactorRenewWhenAlreadyEnabledReturnsError(): void
    {
        $user = $this->createUser(authTfEnabled: true, passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $this->currentUser->method('getIdentity')->willReturn($user);
        $this->twoFactorQrCodeService->expects($this->never())->method('regenerateQrCodeSvg');

        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->expects($this->once())
            ->method('createResponse')
            ->with(403)
            ->willReturn($response);
        $response->method('withHeader')->willReturnSelf();
        $response->method('getBody')->willReturn($this->createMock(StreamInterface::class));

        $controller = $this->createController();
        $result = $controller->renew();

        $this->assertSame($response, $result);
    }

    public function testTwoFactorRenewWhenLibraryMissingReturnsError(): void
    {
        $user = $this->createUser(authTfEnabled: false, passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $this->currentUser->method('getIdentity')->willReturn($user);
        $this->twoFactorQrCodeService->method('isAvailable')->willReturn(false);
        $this->twoFactorQrCodeService->expects($this->never())->method('regenerateQrCodeSvg');

        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->expects($this->once())
            ->method('createResponse')
            ->with(503)
            ->willReturn($response);
        $response->method('withHeader')->willReturnSelf();
        $response->method('getBody')->willReturn($this->createMock(StreamInterface::class));

        $controller = $this->createController();
        $result = $controller->renew();

        $this->assertSame($response, $result);
    }

    public function testTwoFactorSendEmailCodeDoesNotResetTypeWhenAlreadyEmail(): void
    {
        $user = $this->createUser(authTfEnabled: false, authTfType: 'email', passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $this->currentUser->method('getIdentity')->willReturn($user);
        $this->twoFactorEmailCodeService->expects($this->once())->method('run')->with(
            $this->callback(static fn(User $u): bool => $u->getId() === $user->getId()),
        );

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->method('withViewPath')->willReturnSelf();
        $this->viewRenderer->method('render')->willReturn($response);

        $controller = $this->createController();
        $result = $controller->sendEmailCode();

        $this->assertSame($response, $result);
        $updated = User::findById((int) $user->getId());
        $this->assertNotNull($updated);
        $this->assertSame('email', $updated->getAuthTfType());
    }

    public function testTwoFactorSendEmailCodeSendsCodeAndRendersView(): void
    {
        $user = $this->createUser(authTfEnabled: false, authTfType: null, passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $this->currentUser->method('getIdentity')->willReturn($user);
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

        $controller = $this->createController();
        $result = $controller->sendEmailCode();

        $this->assertSame($response, $result);
        $updated = User::findById((int) $user->getId());
        $this->assertNotNull($updated);
        $this->assertSame('email', $updated->getAuthTfType());
    }

    public function testTwoFactorSendEmailCodeWhenAlreadyEnabledRedirects(): void
    {
        $user = $this->createUser(authTfEnabled: true, passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $this->currentUser->method('getIdentity')->willReturn($user);
        $this->twoFactorEmailCodeService->expects($this->never())->method('run');

        $response = $this->mockRedirectResponse($this->responseFactory);

        $controller = $this->createController();
        $result = $controller->sendEmailCode();

        $this->assertSame($response, $result);
    }

    public function testTwoFactorWhenAlreadyEnabledShowsSettings(): void
    {
        $user = $this->createUser(authTfEnabled: true, authTfType: 'google', passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $this->currentUser->method('getIdentity')->willReturn($user);

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

        $controller = $this->createController();
        $result = $controller->index();

        $this->assertSame($response, $result);
    }

    public function testTwoFactorWhenNotEnabledRendersShellWithoutPreloadingContent(): void
    {
        $user = $this->createUser(authTfEnabled: false, passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $this->currentUser->method('getIdentity')->willReturn($user);
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

        $controller = $this->createController();
        $result = $controller->index();

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
