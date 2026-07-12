<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Controller\TwoFactor;

use chillerlan\Authenticator\Authenticator;
use Nyholm\Psr7\ServerRequest;
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
use YiiRocks\Voyti\tests\TestCase;
use Yiisoft\Security\PasswordHasher;
use Yiisoft\Session\Flash\FlashInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\User\CurrentUser;
use Yiisoft\User\Guest\GuestIdentityInterface;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
final class TwoFactorControllerTest extends TestCase
{
    use DatabaseSetupTrait;

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
        $this->config = new ModuleConfig();
        $this->harness = new ControllerHarness($this->config);
        $this->translator = $this->createTranslator();
        $this->viewRenderer = $this->createMock(WebViewRenderer::class);
        $this->currentUser = $this->createMock(CurrentUser::class);
        $this->responseFactory = $this->createMock(ResponseFactoryInterface::class);
        $this->flash = $this->createMock(FlashInterface::class);
        $this->passwordHasher = new PasswordHasher();
        $this->twoFactorQrCodeService = $this->createMock(QrCodeUriGeneratorService::class);
        $this->twoFactorEmailCodeService = $this->createMock(EmailCodeGeneratorService::class);
    }

    protected function tearDown(): void
    {
        $this->tearDownDatabase();
    }

    public function testTwoFactorDisableSendCodeSendsCodeAndRendersView(): void
    {
        $config = new ModuleConfig(enableTwoFactorAuthentication: true);
        $this->harness = new ControllerHarness($config);
        $controller = $this->createController();
        $request = new ServerRequest('POST', '/');

        $user = $this->createUser(authTfEnabled: true, authTfType: 'email');
        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn((string) $user->getId());
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $this->twoFactorEmailCodeService->expects($this->once())->method('run')->with(
            $this->callback(static fn (User $u): bool => $u->getId() === $user->getId()),
        );

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->method('withViewPath')->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('two-factor/index', $this->callback(
                static fn (array $params): bool => $params['method'] === 'email'
                    && $params['emailCodeSent'] === true
                    && $params['preloadContent'] === true,
            ))
            ->willReturn($response);

        $result = $controller->disableSendCode($request);

        $this->assertSame($response, $result);
    }

    public function testTwoFactorDisableSendCodeWhenGoogleMethodRedirects(): void
    {
        $config = new ModuleConfig(enableTwoFactorAuthentication: true);
        $this->harness = new ControllerHarness($config);
        $controller = $this->createController();
        $request = new ServerRequest('POST', '/');

        $user = $this->createUser(authTfEnabled: true, authTfType: 'google');
        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn((string) $user->getId());
        $this->currentUser->method('getIdentity')->willReturn($identity);
        $this->twoFactorEmailCodeService->expects($this->never())->method('run');

        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->expects($this->once())
            ->method('createResponse')
            ->with(302)
            ->willReturn($response);
        $response->expects($this->once())->method('withHeader')->willReturnSelf();

        $result = $controller->disableSendCode($request);

        $this->assertSame($response, $result);
    }

    public function testTwoFactorDisableSendCodeWhenGuestShowsError(): void
    {
        $config = new ModuleConfig(enableTwoFactorAuthentication: true);
        $this->harness = new ControllerHarness($config);
        $controller = $this->createController();
        $request = new ServerRequest('POST', '/');

        $this->currentUser->method('getIdentity')->willReturn($this->createMock(GuestIdentityInterface::class));

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->method('withViewPath')->willReturnSelf();
        $this->viewRenderer->method('render')->willReturn($response);

        $result = $controller->disableSendCode($request);

        $this->assertSame($response, $result);
    }

    public function testTwoFactorDisableSendCodeWhenNotEnabledRedirects(): void
    {
        $config = new ModuleConfig(enableTwoFactorAuthentication: true);
        $this->harness = new ControllerHarness($config);
        $controller = $this->createController();
        $request = new ServerRequest('POST', '/');

        $user = $this->createUser(authTfEnabled: false);
        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn((string) $user->getId());
        $this->currentUser->method('getIdentity')->willReturn($identity);
        $this->twoFactorEmailCodeService->expects($this->never())->method('run');

        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->expects($this->once())
            ->method('createResponse')
            ->with(302)
            ->willReturn($response);
        $response->expects($this->once())->method('withHeader')->willReturnSelf();

        $result = $controller->disableSendCode($request);

        $this->assertSame($response, $result);
    }

    public function testTwoFactorDisableWhenGuestShowsError(): void
    {
        $config = new ModuleConfig(enableTwoFactorAuthentication: true);
        $this->harness = new ControllerHarness($config);
        $controller = $this->createController();
        $request = new ServerRequest('POST', '/');

        $this->currentUser->method('getIdentity')->willReturn($this->createMock(GuestIdentityInterface::class));

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->method('withViewPath')->willReturnSelf();
        $this->viewRenderer->method('render')->willReturn($response);

        $result = $controller->disable($request);

        $this->assertSame($response, $result);
    }

    public function testTwoFactorDisableWithInvalidEmailCodeShowsFormWithCodeSent(): void
    {
        $config = new ModuleConfig(enableTwoFactorAuthentication: true);
        $this->harness = new ControllerHarness($config);
        $controller = $this->createController();
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['code' => 'wrong']);

        $user = $this->createUser(authTfType: 'email', authTfKey: '123456');
        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn((string) $user->getId());
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->method('withViewPath')->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('two-factor/index', $this->callback(
                static fn (array $params): bool => $params['method'] === 'email'
                    && $params['emailCodeSent'] === true
                    && $params['preloadContent'] === true
                    && $params['errors'] !== [],
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
        $config = new ModuleConfig(enableTwoFactorAuthentication: true);
        $this->harness = new ControllerHarness($config);
        $controller = $this->createController();
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['code' => 'wrong']);

        $user = $this->createUser(authTfType: 'google', authTfKey: null);
        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn((string) $user->getId());
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->method('withViewPath')->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('two-factor/index', $this->callback(
                static fn (array $params): bool => $params['method'] === 'google'
                    && $params['emailCodeSent'] === false
                    && $params['preloadContent'] === true
                    && $params['errors'] !== [],
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
        $config = new ModuleConfig(enableTwoFactorAuthentication: true);
        $this->harness = new ControllerHarness($config);
        $backupCodeService = new BackupCodeService($this->passwordHasher);

        $user = $this->createUser(authTfEnabled: true, authTfType: 'email', authTfKey: '123456');
        $codes = $backupCodeService->generate($user);

        $controller = $this->createController(backupCodeService: $backupCodeService);
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['code' => $codes[0]]);

        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn((string) $user->getId());
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->expects($this->once())
            ->method('createResponse')
            ->with(302)
            ->willReturn($response);
        $response->expects($this->once())
            ->method('withHeader')
            ->willReturnSelf();

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
        $config = new ModuleConfig(enableTwoFactorAuthentication: true);
        $this->harness = new ControllerHarness($config);
        $controller = $this->createController();
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['code' => '123456']);

        $user = $this->createUser(authTfEnabled: true, authTfType: 'email', authTfKey: '123456');
        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn((string) $user->getId());
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->expects($this->once())
            ->method('createResponse')
            ->with(302)
            ->willReturn($response);
        $response->expects($this->once())
            ->method('withHeader')
            ->willReturnSelf();

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
        if (!class_exists(Authenticator::class)) {
            $this->markTestSkipped('chillerlan/php-authenticator not installed.');
        }

        $secret = (new Authenticator())->createSecret();
        $authenticator = new Authenticator();
        $authenticator->setSecret($secret);
        $code = $authenticator->code();

        $config = new ModuleConfig(enableTwoFactorAuthentication: true);
        $this->harness = new ControllerHarness($config);
        $controller = $this->createController();
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['code' => $code]);

        $identity = $this->createMock(User::class);
        $user = $this->createUser(authTfEnabled: true, authTfType: 'google', authTfKey: $secret);
        $identity->method('getId')->willReturn((string) $user->getId());
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->expects($this->once())
            ->method('createResponse')
            ->with(302)
            ->willReturn($response);
        $response->expects($this->once())
            ->method('withHeader')
            ->willReturnSelf();

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
        $config = new ModuleConfig(enableTwoFactorAuthentication: true);
        $this->harness = new ControllerHarness($config);
        $controller = $this->createController();
        $request = (new ServerRequest('GET', '/'))->withHeader('X-Requested-With', 'XMLHttpRequest');

        $user = $this->createUser(authTfEnabled: false);
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
                static fn (array $params): bool => $params['emailCodeSent'] === false,
            ))
            ->willReturn($response);

        $result = $controller->email($request);

        $this->assertSame($response, $result);
    }

    public function testTwoFactorEmailRendersShellWithoutFragmentHeader(): void
    {
        $config = new ModuleConfig(enableTwoFactorAuthentication: true);
        $this->harness = new ControllerHarness($config);
        $controller = $this->createController();
        $request = new ServerRequest('GET', '/');

        $user = $this->createUser(authTfEnabled: false);
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
                static fn (array $params): bool => $params['method'] === 'email'
                    && $params['emailCodeSent'] === false
                    && $params['preloadContent'] === true,
            ))
            ->willReturn($response);

        $result = $controller->email($request);

        $this->assertSame($response, $result);
    }

    public function testTwoFactorEmailWhenAlreadyEnabledRedirects(): void
    {
        $config = new ModuleConfig(enableTwoFactorAuthentication: true);
        $this->harness = new ControllerHarness($config);
        $controller = $this->createController();
        $request = new ServerRequest('GET', '/');

        $user = $this->createUser(authTfEnabled: true);
        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn((string) $user->getId());
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->expects($this->once())
            ->method('createResponse')
            ->with(302)
            ->willReturn($response);
        $response->expects($this->once())
            ->method('withHeader')
            ->willReturnSelf();

        $result = $controller->email($request);

        $this->assertSame($response, $result);
    }

    public function testTwoFactorEmailWhenGuestShowsError(): void
    {
        $config = new ModuleConfig(enableTwoFactorAuthentication: true);
        $this->harness = new ControllerHarness($config);
        $controller = $this->createController();
        $request = new ServerRequest('GET', '/');

        $this->currentUser->method('getIdentity')->willReturn($this->createMock(GuestIdentityInterface::class));

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->method('withViewPath')->willReturnSelf();
        $this->viewRenderer->method('render')->willReturn($response);

        $result = $controller->email($request);

        $this->assertSame($response, $result);
    }

    public function testTwoFactorEnableWhenAlreadyEnabledRedirects(): void
    {
        $config = new ModuleConfig(enableTwoFactorAuthentication: true);
        $this->harness = new ControllerHarness($config);
        $controller = $this->createController();
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['method' => 'google', 'code' => '123456']);

        $user = $this->createUser(authTfEnabled: true);
        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn((string) $user->getId());
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->expects($this->once())
            ->method('createResponse')
            ->with(302)
            ->willReturn($response);
        $response->expects($this->once())->method('withHeader')->willReturnSelf();

        $result = $controller->enable($request);

        $this->assertSame($response, $result);
        $updated = User::findById((int) $user->getId());
        $this->assertNotNull($updated);
        $this->assertTrue($updated->isAuthTfEnabled());
    }

    public function testTwoFactorEnableWhenGuestShowsError(): void
    {
        $config = new ModuleConfig(enableTwoFactorAuthentication: true);
        $this->harness = new ControllerHarness($config);
        $controller = $this->createController();
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['method' => 'google', 'code' => '123456']);

        $this->currentUser->method('getIdentity')->willReturn($this->createMock(GuestIdentityInterface::class));

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->method('withViewPath')->willReturnSelf();
        $this->viewRenderer->method('render')->willReturn($response);

        $result = $controller->enable($request);

        $this->assertSame($response, $result);
    }

    public function testTwoFactorEnableWithEmailCode(): void
    {
        $config = new ModuleConfig(enableTwoFactorAuthentication: true);
        $this->harness = new ControllerHarness($config);
        $controller = $this->createController();
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['method' => 'email', 'code' => '123456']);

        $user = $this->createUser(authTfKey: '123456');
        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn((string) $user->getId());
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->method('withViewPath')->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('two-factor/backup-codes', $this->callback(
                static fn (array $params): bool => count($params['codes']) === 10,
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
        $config = new ModuleConfig(enableTwoFactorAuthentication: true);
        $this->harness = new ControllerHarness($config);
        $controller = $this->createController();
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['method' => 'email', 'code' => 'wrong']);

        $user = $this->createUser(authTfKey: '123456');
        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn((string) $user->getId());
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->method('withViewPath')->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('two-factor/index', $this->callback(
                static fn (array $params): bool => $params['method'] === 'email'
                    && $params['emailCodeSent'] === true
                    && $params['preloadContent'] === true,
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
        $config = new ModuleConfig(enableTwoFactorAuthentication: true);
        $this->harness = new ControllerHarness($config);
        $controller = $this->createController();
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['method' => 'google', 'code' => 'wrong']);

        $user = $this->createUser(authTfType: 'google', authTfKey: null);
        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn((string) $user->getId());
        $this->currentUser->method('getIdentity')->willReturn($identity);
        $this->twoFactorQrCodeService->method('generateQrCodeSvg')->willReturn('<svg></svg>');

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->method('withViewPath')->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('two-factor/index', $this->callback(
                static fn (array $params): bool => $params['method'] === 'google'
                    && $params['emailCodeSent'] === false
                    && $params['preloadContent'] === true,
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
        if (!class_exists(Authenticator::class)) {
            $this->markTestSkipped('chillerlan/php-authenticator not installed.');
        }

        $secret = (new Authenticator())->createSecret();
        $authenticator = new Authenticator();
        $authenticator->setSecret($secret);
        $code = $authenticator->code();

        $config = new ModuleConfig(enableTwoFactorAuthentication: true);
        $this->harness = new ControllerHarness($config);
        $controller = $this->createController();
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['method' => 'google', 'code' => $code]);

        $user = $this->createUser(authTfType: 'google', authTfKey: $secret);
        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn((string) $user->getId());
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->method('withViewPath')->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('two-factor/backup-codes', $this->callback(
                static fn (array $params): bool => count($params['codes']) === 10,
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
        $config = new ModuleConfig(enableTwoFactorAuthentication: true);
        $this->harness = new ControllerHarness($config);
        $controller = $this->createController();
        $request = (new ServerRequest('GET', '/'))->withHeader('X-Requested-With', 'XMLHttpRequest');

        $user = $this->createUser(authTfEnabled: false, authTfType: null, authTfKey: 'secret');
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
                static fn (array $params): bool => $params['qrCodeUri'] === '<svg></svg>' && $params['secret'] === null,
            ))
            ->willReturn($response);

        $result = $controller->google($request);

        $this->assertSame($response, $result);
    }

    public function testTwoFactorGoogleRendersShellWithoutFragmentHeader(): void
    {
        $config = new ModuleConfig(enableTwoFactorAuthentication: true);
        $this->harness = new ControllerHarness($config);
        $controller = $this->createController();
        $request = new ServerRequest('GET', '/');

        $user = $this->createUser(authTfEnabled: false, authTfType: null, authTfKey: null);
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
                static fn (array $params): bool => $params['method'] === 'google'
                    && $params['emailCodeSent'] === false
                    && $params['preloadContent'] === true,
            ))
            ->willReturn($response);

        $result = $controller->google($request);

        $this->assertSame($response, $result);
    }

    public function testTwoFactorGoogleWhenAlreadyEnabledRedirects(): void
    {
        $config = new ModuleConfig(enableTwoFactorAuthentication: true);
        $this->harness = new ControllerHarness($config);
        $controller = $this->createController();
        $request = new ServerRequest('GET', '/');

        $user = $this->createUser(authTfEnabled: true);
        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn((string) $user->getId());
        $this->currentUser->method('getIdentity')->willReturn($identity);
        $this->twoFactorQrCodeService->expects($this->never())->method('generateQrCodeSvg');

        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->expects($this->once())
            ->method('createResponse')
            ->with(302)
            ->willReturn($response);
        $response->expects($this->once())
            ->method('withHeader')
            ->willReturnSelf();

        $result = $controller->google($request);

        $this->assertSame($response, $result);
    }

    public function testTwoFactorGoogleWhenGuestShowsError(): void
    {
        $config = new ModuleConfig(enableTwoFactorAuthentication: true);
        $this->harness = new ControllerHarness($config);
        $controller = $this->createController();
        $request = new ServerRequest('GET', '/');

        $this->currentUser->method('getIdentity')->willReturn($this->createMock(GuestIdentityInterface::class));

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->method('withViewPath')->willReturnSelf();
        $this->viewRenderer->method('render')->willReturn($response);

        $result = $controller->google($request);

        $this->assertSame($response, $result);
    }

    public function testTwoFactorIndexReportsHasBackupCodesWhenCodesExist(): void
    {
        $config = new ModuleConfig(enableTwoFactorAuthentication: true);
        $this->harness = new ControllerHarness($config);
        $backupCodeService = new BackupCodeService($this->passwordHasher);

        $user = $this->createUser(authTfEnabled: true, authTfType: 'google', authTfKey: '123456');
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
                static fn (array $params): bool => $params['hasBackupCodes'] === true,
            ))
            ->willReturn($response);

        $result = $controller->index(new ServerRequest('GET', '/'));

        $this->assertSame($response, $result);
    }

    public function testTwoFactorIndexReportsNoBackupCodesWhenNoneRemain(): void
    {
        $config = new ModuleConfig(enableTwoFactorAuthentication: true);
        $this->harness = new ControllerHarness($config);

        $user = $this->createUser(authTfEnabled: true, authTfType: 'google', authTfKey: '123456');

        $controller = $this->createController();
        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn((string) $user->getId());
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->method('withViewPath')->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('two-factor/index', $this->callback(
                static fn (array $params): bool => $params['hasBackupCodes'] === false,
            ))
            ->willReturn($response);

        $result = $controller->index(new ServerRequest('GET', '/'));

        $this->assertSame($response, $result);
    }

    public function testTwoFactorIndexWhenUserNotFoundShowsError(): void
    {
        $config = new ModuleConfig(enableTwoFactorAuthentication: true);
        $this->harness = new ControllerHarness($config);
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
        $config = new ModuleConfig(enableTwoFactorAuthentication: true);
        $this->harness = new ControllerHarness($config);
        $controller = $this->createController();
        $request = new ServerRequest('POST', '/');

        $this->currentUser->method('getIdentity')->willReturn($this->createMock(GuestIdentityInterface::class));

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->method('withViewPath')->willReturnSelf();
        $this->viewRenderer->method('render')->willReturn($response);

        $result = $controller->regenerateBackupCodes($request);

        $this->assertSame($response, $result);
    }

    public function testTwoFactorRegenerateBackupCodesWhenNotEnabledRedirects(): void
    {
        $config = new ModuleConfig(enableTwoFactorAuthentication: true);
        $this->harness = new ControllerHarness($config);
        $controller = $this->createController();
        $request = new ServerRequest('POST', '/');

        $user = $this->createUser(authTfEnabled: false);
        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn((string) $user->getId());
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->expects($this->once())
            ->method('createResponse')
            ->with(302)
            ->willReturn($response);
        $response->expects($this->once())->method('withHeader')->willReturnSelf();

        $result = $controller->regenerateBackupCodes($request);

        $this->assertSame($response, $result);
    }

    public function testTwoFactorRegenerateBackupCodesWithInvalidCodeShowsForm(): void
    {
        $config = new ModuleConfig(enableTwoFactorAuthentication: true);
        $this->harness = new ControllerHarness($config);
        $controller = $this->createController();
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['code' => 'wrong']);

        $user = $this->createUser(authTfEnabled: true, authTfType: 'email', authTfKey: '123456');
        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn((string) $user->getId());
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->method('withViewPath')->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('two-factor/index', $this->callback(
                static fn (array $params): bool => $params['method'] === 'email',
            ))
            ->willReturn($response);

        $result = $controller->regenerateBackupCodes($request);

        $this->assertSame($response, $result);
    }

    public function testTwoFactorRegenerateBackupCodesWithValidCodeShowsNewCodes(): void
    {
        $config = new ModuleConfig(enableTwoFactorAuthentication: true);
        $this->harness = new ControllerHarness($config);

        $user = $this->createUser(authTfEnabled: true, authTfType: 'email', authTfKey: '123456');
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
                static fn (array $params): bool => count($params['codes']) === 10,
            ))
            ->willReturn($response);

        $result = $controller->regenerateBackupCodes($request);

        $this->assertSame($response, $result);
    }

    public function testTwoFactorRegenerateBackupCodesWithValidGoogleCodeShowsNewCodes(): void
    {
        if (!class_exists(Authenticator::class)) {
            $this->markTestSkipped('chillerlan/php-authenticator not installed.');
        }

        $secret = (new Authenticator())->createSecret();
        $authenticator = new Authenticator();
        $authenticator->setSecret($secret);
        $code = $authenticator->code();

        $config = new ModuleConfig(enableTwoFactorAuthentication: true);
        $this->harness = new ControllerHarness($config);

        $user = $this->createUser(authTfEnabled: true, authTfType: 'google', authTfKey: $secret);
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
                static fn (array $params): bool => count($params['codes']) === 10,
            ))
            ->willReturn($response);

        $result = $controller->regenerateBackupCodes($request);

        $this->assertSame($response, $result);
    }

    public function testTwoFactorRenewDoesNotResetTypeWhenAlreadyGoogle(): void
    {
        $config = new ModuleConfig(enableTwoFactorAuthentication: true);
        $this->harness = new ControllerHarness($config);
        $controller = $this->createController();
        $request = new ServerRequest('POST', '/');

        $user = $this->createUser(authTfEnabled: false, authTfType: 'google', authTfKey: 'secret');
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
        $config = new ModuleConfig(enableTwoFactorAuthentication: true);
        $this->harness = new ControllerHarness($config);
        $controller = $this->createController();
        $request = new ServerRequest('POST', '/');

        $user = $this->createUser(authTfEnabled: false, authTfType: 'email', authTfKey: 'new-secret');
        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn((string) $user->getId());
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $this->twoFactorQrCodeService->method('isAvailable')->willReturn(true);
        $this->twoFactorQrCodeService->expects($this->once())
            ->method('generateQrCodeSvg')
            ->with(
                $this->callback(static fn (User $u): bool => $u->getId() === $user->getId()),
                forceNewSecret: true,
            )
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
                static fn (string $json): bool => json_decode($json, true) === ['qrCodeUri' => '<svg>new</svg>', 'secret' => 'new-secret'],
            ));
        $response->method('getBody')->willReturn($body);

        $result = $controller->renew($request);

        $this->assertSame($response, $result);
    }

    public function testTwoFactorRenewWhenAlreadyEnabledReturnsError(): void
    {
        $config = new ModuleConfig(enableTwoFactorAuthentication: true);
        $this->harness = new ControllerHarness($config);
        $controller = $this->createController();
        $request = new ServerRequest('POST', '/');

        $user = $this->createUser(authTfEnabled: true);
        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn((string) $user->getId());
        $this->currentUser->method('getIdentity')->willReturn($identity);
        $this->twoFactorQrCodeService->expects($this->never())->method('generateQrCodeSvg');

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
        $config = new ModuleConfig(enableTwoFactorAuthentication: true);
        $this->harness = new ControllerHarness($config);
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
        $config = new ModuleConfig(enableTwoFactorAuthentication: true);
        $this->harness = new ControllerHarness($config);
        $controller = $this->createController();
        $request = new ServerRequest('POST', '/');

        $user = $this->createUser(authTfEnabled: false);
        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn((string) $user->getId());
        $this->currentUser->method('getIdentity')->willReturn($identity);
        $this->twoFactorQrCodeService->method('isAvailable')->willReturn(false);
        $this->twoFactorQrCodeService->expects($this->never())->method('generateQrCodeSvg');

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
        $config = new ModuleConfig(enableTwoFactorAuthentication: true);
        $this->harness = new ControllerHarness($config);
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
        $config = new ModuleConfig(enableTwoFactorAuthentication: true);
        $this->harness = new ControllerHarness($config);
        $controller = $this->createController();
        $request = new ServerRequest('POST', '/');

        $user = $this->createUser(authTfEnabled: false, authTfType: 'email');
        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn((string) $user->getId());
        $this->currentUser->method('getIdentity')->willReturn($identity);
        $this->twoFactorEmailCodeService->expects($this->once())->method('run')->with(
            $this->callback(static fn (User $u): bool => $u->getId() === $user->getId()),
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
        $config = new ModuleConfig(enableTwoFactorAuthentication: true);
        $this->harness = new ControllerHarness($config);
        $controller = $this->createController();
        $request = new ServerRequest('POST', '/');

        $user = $this->createUser(authTfEnabled: false, authTfType: null);
        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn((string) $user->getId());
        $this->currentUser->method('getIdentity')->willReturn($identity);
        $this->twoFactorEmailCodeService->expects($this->once())->method('run')->with(
            $this->callback(static fn (User $u): bool => $u->getId() === $user->getId()),
        );

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->method('withViewPath')->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('two-factor/index', $this->callback(
                static fn (array $params): bool => $params['method'] === 'email'
                    && $params['emailCodeSent'] === true
                    && $params['preloadContent'] === true,
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
        $config = new ModuleConfig(enableTwoFactorAuthentication: true);
        $this->harness = new ControllerHarness($config);
        $controller = $this->createController();
        $request = new ServerRequest('POST', '/');

        $user = $this->createUser(authTfEnabled: true);
        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn((string) $user->getId());
        $this->currentUser->method('getIdentity')->willReturn($identity);
        $this->twoFactorEmailCodeService->expects($this->never())->method('run');

        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->expects($this->once())
            ->method('createResponse')
            ->with(302)
            ->willReturn($response);
        $response->expects($this->once())->method('withHeader')->willReturnSelf();

        $result = $controller->sendEmailCode($request);

        $this->assertSame($response, $result);
    }

    public function testTwoFactorSendEmailCodeWhenGuestShowsError(): void
    {
        $config = new ModuleConfig(enableTwoFactorAuthentication: true);
        $this->harness = new ControllerHarness($config);
        $controller = $this->createController();
        $request = new ServerRequest('POST', '/');

        $this->currentUser->method('getIdentity')->willReturn($this->createMock(GuestIdentityInterface::class));

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->method('withViewPath')->willReturnSelf();
        $this->viewRenderer->method('render')->willReturn($response);

        $result = $controller->sendEmailCode($request);

        $this->assertSame($response, $result);
    }

    public function testTwoFactorWhenAlreadyEnabledShowsSettings(): void
    {
        $config = new ModuleConfig(enableTwoFactorAuthentication: true);
        $this->harness = new ControllerHarness($config);
        $controller = $this->createController();
        $request = new ServerRequest('GET', '/');

        $user = $this->createUser(authTfEnabled: true, authTfType: 'google');
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
                static fn (array $params): bool => $params['emailCodeSent'] === false
                    && $params['preloadContent'] === true,
            ))
            ->willReturn($response);

        $result = $controller->index($request);

        $this->assertSame($response, $result);
    }

    public function testTwoFactorWhenGuestShowsError(): void
    {
        $config = new ModuleConfig(enableTwoFactorAuthentication: true);
        $this->harness = new ControllerHarness($config);
        $controller = $this->createController();
        $request = new ServerRequest('GET', '/');

        $this->currentUser->method('getIdentity')->willReturn($this->createMock(GuestIdentityInterface::class));

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->method('withViewPath')->willReturnSelf();
        $this->viewRenderer->method('render')->willReturn($response);

        $result = $controller->index($request);

        $this->assertSame($response, $result);
    }

    public function testTwoFactorWhenNotEnabledRendersShellWithoutPreloadingContent(): void
    {
        $config = new ModuleConfig(enableTwoFactorAuthentication: true);
        $this->harness = new ControllerHarness($config);
        $controller = $this->createController();
        $request = new ServerRequest('GET', '/');

        $user = $this->createUser(authTfEnabled: false);
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
                static fn (array $params): bool => $params['method'] === 'google'
                    && $params['emailCodeSent'] === false
                    && $params['preloadContent'] === false,
            ))
            ->willReturn($response);

        $result = $controller->index($request);

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

    private function createUser(
        string $username = 'testuser',
        string $email = 'test@example.com',
        string $password = 'secret',
        bool $authTfEnabled = false,
        ?string $authTfType = null,
        ?string $authTfKey = null,
    ): User {
        $user = new User();
        $user->setUsername($username);
        $user->setEmail($email);
        $user->setPasswordHash($this->passwordHasher->hash($password));
        $user->setAuthKey('key');
        $user->setCreatedAt(time());
        $user->setUpdatedAt(time());
        $user->setConfirmedAt(time());
        $user->setAuthTfEnabled($authTfEnabled);
        $user->setAuthTfType($authTfType);
        $user->setAuthTfKey($authTfKey);
        $user->save();

        return $user;
    }
}
