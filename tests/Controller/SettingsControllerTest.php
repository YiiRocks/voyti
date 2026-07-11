<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Controller;

use chillerlan\Authenticator\Authenticator;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use YiiRocks\Voyti\Controller\SettingsController;
use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\Entity\UserProfile;
use YiiRocks\Voyti\Entity\UserSessionHistory;
use YiiRocks\Voyti\Entity\UserSocialAccount;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Service\EmailChangeService;
use YiiRocks\Voyti\Service\TwoFactor\EmailCodeGeneratorService;
use YiiRocks\Voyti\Service\TwoFactor\QrCodeUriGeneratorService;
use YiiRocks\Voyti\Service\UserSessionHistory\TerminateUserSessionsService;
use YiiRocks\Voyti\Strategy\EmailChangeStrategyFactory;
use YiiRocks\Voyti\Strategy\NoneEmailChangeStrategy;
use YiiRocks\Voyti\tests\Support\ControllerHarness;
use YiiRocks\Voyti\tests\Support\DatabaseSetupTrait;
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

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
final class SettingsControllerTest extends TestCase
{
    use DatabaseSetupTrait;

    private ModuleConfig $config;
    private CurrentUser&MockObject $currentUser;
    private EmailChangeService&MockObject $emailChangeService;
    private EmailChangeStrategyFactory&MockObject $emailChangeStrategyFactory;
    private FlashInterface&MockObject $flash;
    private ControllerHarness $harness;
    private HydratorInterface&MockObject $hydrator;
    private PasswordHasher $passwordHasher;
    private ResponseFactoryInterface&MockObject $responseFactory;
    private TerminateUserSessionsService&MockObject $terminateUserSessionsService;
    private TranslatorInterface $translator;
    private EmailCodeGeneratorService&MockObject $twoFactorEmailCodeService;
    private QrCodeUriGeneratorService&MockObject $twoFactorQrCodeService;
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
        $this->emailChangeStrategyFactory = $this->createMock(EmailChangeStrategyFactory::class);
        $this->twoFactorQrCodeService = $this->createMock(QrCodeUriGeneratorService::class);
        $this->twoFactorEmailCodeService = $this->createMock(EmailCodeGeneratorService::class);
        $this->emailChangeService = $this->createMock(EmailChangeService::class);
        $this->terminateUserSessionsService = $this->createMock(TerminateUserSessionsService::class);
    }

    protected function tearDown(): void
    {
        $this->tearDownDatabase();
    }

    public function testAccountGetShowsForm(): void
    {
        $controller = $this->createController();
        $request = new ServerRequest('GET', '/');

        $user = $this->createUser();
        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn((string) $user->getId());
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->expects($this->once())
            ->method('withViewPath')
            ->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('settings/account', $this->anything())
            ->willReturn($response);

        $result = $controller->account($request);

        $this->assertSame($response, $result);
    }

    public function testAccountPostUpdatesAndRedirects(): void
    {
        $controller = $this->createController();
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['settings' => ['username' => 'testuser', 'email' => 'test@example.com', 'password' => '', 'passwordRepeat' => '']]);

        $this->validator->method('validate')->willReturn(new Result());
        $user = $this->createUser();
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

        $result = $controller->account($request);

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
        $user = $this->createUser(email: 'old@example.com');
        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn((string) $user->getId());
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $strategy = $this->createMock(NoneEmailChangeStrategy::class);
        $strategy->expects($this->once())->method('run');
        $this->emailChangeStrategyFactory->expects($this->once())
            ->method('makeByStrategyType')
            ->willReturn($strategy);

        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->method('createResponse')->willReturn($response);
        $response->method('withHeader')->willReturnSelf();

        $result = $controller->account($request);

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
        $user = $this->createUser();
        $originalHash = $user->getPasswordHash();
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

        $result = $controller->account($request);

        $this->assertSame($response, $result);
        $updated = User::findById((int) $user->getId());
        $this->assertNotNull($updated);
        $this->assertNotSame($originalHash, $updated->getPasswordHash());
        $this->assertNotNull($updated->getPasswordChangedAt());
    }

    public function testAccountWhenGuestShowsError(): void
    {
        $controller = $this->createController();
        $request = new ServerRequest('GET', '/');

        $this->currentUser->method('getIdentity')->willReturn($this->createMock(GuestIdentityInterface::class));

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->expects($this->once())
            ->method('withViewPath')
            ->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->willReturn($response);

        $result = $controller->account($request);

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

        $result = $controller->account($request);

        $this->assertSame($response, $result);
    }

    public function testAnonymizeGetShowsForm(): void
    {
        $config = new ModuleConfig(enableGdprCompliance: true);
        $this->harness = new ControllerHarness($config);
        $controller = $this->createController();
        $request = new ServerRequest('GET', '/');

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->expects($this->once())
            ->method('withViewPath')
            ->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('settings/privacy/anonymize', $this->anything())
            ->willReturn($response);

        $result = $controller->anonymize($request);

        $this->assertSame($response, $result);
    }

    public function testAnonymizePostWithValidPasswordAnonymizesUser(): void
    {
        $config = new ModuleConfig(enableGdprCompliance: true);
        $this->harness = new ControllerHarness($config);
        $controller = $this->createController();

        $password = 'mypassword';

        $request = (new ServerRequest('POST', '/'))->withParsedBody(['anonymize' => ['password' => $password, 'consent' => '1']]);

        $this->hydrator->method('hydrate')->willReturnCallback(
            function (object $object, array $data = []): void {
                if (property_exists($object, 'password') && isset($data['password'])) {
                    $object->password = $data['password'];
                }
                if (property_exists($object, 'consent') && isset($data['consent'])) {
                    $object->consent = (bool) $data['consent'];
                }
            },
        );
        $this->validator->method('validate')->willReturn(new Result());
        $user = $this->createUser(password: $password);
        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn((string) $user->getId());
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->expects($this->once())
            ->method('withViewPath')
            ->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->willReturn($response);

        $result = $controller->anonymize($request);

        $this->assertSame($response, $result);
        $updated = User::findById((int) $user->getId());
        $this->assertNotNull($updated);
        $this->assertTrue($updated->isAnonymized());
        $this->assertTrue($updated->isBlocked());
    }

    public function testConfirmWhenGuestShowsError(): void
    {
        $controller = $this->createController();
        $request = new ServerRequest('GET', '/');

        $this->currentUser->method('getIdentity')->willReturn($this->createMock(GuestIdentityInterface::class));

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->method('withViewPath')->willReturnSelf();
        $this->viewRenderer->method('render')->willReturn($response);

        $result = $controller->confirm($request, 'good-code');

        $this->assertSame($response, $result);
    }

    public function testConfirmWithInvalidCodeShowsFailureMessage(): void
    {
        $controller = $this->createController();
        $request = new ServerRequest('GET', '/');

        $user = $this->createUser();
        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn((string) $user->getId());
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $this->emailChangeService->expects($this->once())->method('run')->with(
            'bad-code',
            $this->callback(static fn (User $u): bool => $u->getId() === $user->getId()),
        )->willReturn(false);

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->method('withViewPath')->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('shared/message', $this->callback(
                static fn (array $params): bool => $params['title'] === 'Failed to change email',
            ))
            ->willReturn($response);

        $result = $controller->confirm($request, 'bad-code');

        $this->assertSame($response, $result);
    }

    public function testConfirmWithValidCodeShowsSuccessMessage(): void
    {
        $controller = $this->createController();
        $request = new ServerRequest('GET', '/');

        $user = $this->createUser();
        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn((string) $user->getId());
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $this->emailChangeService->expects($this->once())->method('run')->with(
            'good-code',
            $this->callback(static fn (User $u): bool => $u->getId() === $user->getId()),
        )->willReturn(true);

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->method('withViewPath')->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('shared/message', $this->callback(
                static fn (array $params): bool => $params['title'] === 'Your email has been changed',
            ))
            ->willReturn($response);

        $result = $controller->confirm($request, 'good-code');

        $this->assertSame($response, $result);
    }

    public function testDeleteGetShowsForm(): void
    {
        $config = new ModuleConfig(allowAccountDelete: true);
        $this->harness = new ControllerHarness($config);
        $controller = $this->createController();
        $request = new ServerRequest('GET', '/');

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->expects($this->once())
            ->method('withViewPath')
            ->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('settings/privacy/delete', $this->anything())
            ->willReturn($response);

        $result = $controller->delete($request);

        $this->assertSame($response, $result);
    }

    public function testDeletePostWithInvalidPasswordShowsForm(): void
    {
        $config = new ModuleConfig(allowAccountDelete: true);
        $this->harness = new ControllerHarness($config);
        $controller = $this->createController();

        $request = (new ServerRequest('POST', '/'))->withParsedBody(['delete-account' => ['password' => 'wrongpassword', 'consent' => '1']]);

        $this->hydrator->method('hydrate')->willReturnCallback(
            function (object $object, array $data = []): void {
                if (property_exists($object, 'password') && isset($data['password'])) {
                    $object->password = $data['password'];
                }
                if (property_exists($object, 'consent') && isset($data['consent'])) {
                    $object->consent = (bool) $data['consent'];
                }
            },
        );
        $this->validator->method('validate')->willReturn(new Result());
        $user = $this->createUser(password: 'correctpassword');
        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn((string) $user->getId());
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->expects($this->once())
            ->method('withViewPath')
            ->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('settings/privacy/delete', $this->anything())
            ->willReturn($response);

        $result = $controller->delete($request);

        $this->assertSame($response, $result);
        $this->assertNotNull(User::findById((int) $user->getId()));
    }

    public function testDeletePostWithValidPasswordDeletesUser(): void
    {
        $config = new ModuleConfig(allowAccountDelete: true);
        $this->harness = new ControllerHarness($config);
        $controller = $this->createController();

        $password = 'mypassword';

        $request = (new ServerRequest('POST', '/'))->withParsedBody(['delete-account' => ['password' => $password, 'consent' => '1']]);

        $this->hydrator->method('hydrate')->willReturnCallback(
            function (object $object, array $data = []): void {
                if (property_exists($object, 'password') && isset($data['password'])) {
                    $object->password = $data['password'];
                }
                if (property_exists($object, 'consent') && isset($data['consent'])) {
                    $object->consent = (bool) $data['consent'];
                }
            },
        );
        $this->validator->method('validate')->willReturn(new Result());
        $user = $this->createUser(password: $password);
        $userId = (int) $user->getId();
        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn((string) $userId);
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->expects($this->once())
            ->method('withViewPath')
            ->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->willReturn($response);

        $result = $controller->delete($request);

        $this->assertSame($response, $result);
        $this->assertNull(User::findById($userId));
    }

    public function testDisconnectWhenGuestShowsError(): void
    {
        $controller = $this->createController();
        $request = new ServerRequest('GET', '/');

        $this->currentUser->method('getIdentity')->willReturn($this->createMock(GuestIdentityInterface::class));

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->expects($this->once())
            ->method('withViewPath')
            ->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->willReturn($response);

        $result = $controller->disconnect($request, 1);

        $this->assertSame($response, $result);
    }

    public function testDisconnectWithFoundAccountDeletesAndRedirects(): void
    {
        $controller = $this->createController();
        $request = new ServerRequest('GET', '/');

        $user = $this->createUser();
        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn((string) $user->getId());
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $account = $this->createSocialAccount((int) $user->getId());
        $accountId = $account->getId();

        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->expects($this->once())
            ->method('createResponse')
            ->with(302)
            ->willReturn($response);
        $response->expects($this->once())
            ->method('withHeader')
            ->willReturnSelf();

        $result = $controller->disconnect($request, $accountId);

        $this->assertSame($response, $result);
        $this->assertSame([], UserSocialAccount::findByUserId((int) $user->getId()));
    }

    public function testDisconnectWithNoAccountShowsNotFound(): void
    {
        $controller = $this->createController();
        $request = new ServerRequest('GET', '/');

        $user = $this->createUser();
        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn((string) $user->getId());
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->expects($this->once())
            ->method('withViewPath')
            ->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->willReturn($response);

        $result = $controller->disconnect($request, 999);

        $this->assertSame($response, $result);
    }

    public function testExportIncludesSessionHistoryAndSocialAccounts(): void
    {
        $config = new ModuleConfig(enableGdprCompliance: true, gdprExportProperties: ['userSessionHistory', 'userSocialAccount']);
        $this->harness = new ControllerHarness($config);
        $controller = $this->createController();
        $request = new ServerRequest('GET', '/');

        $user = $this->createUser();
        $userId = (int) $user->getId();
        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn((string) $userId);
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $sessionEntry = new UserSessionHistory();
        $sessionEntry->setUserId($userId);
        $sessionEntry->setSessionId('session-1');
        $sessionEntry->setIp('203.0.113.5');
        $sessionEntry->setUserAgent('TestAgent/1.0');
        $sessionEntry->setCreatedAt(1000);
        $sessionEntry->setUpdatedAt(2000);
        $sessionEntry->save();

        $socialAccount = $this->createSocialAccount($userId, 'github', 'octocat');
        $socialAccount->setEmail('octocat@example.com');
        $socialAccount->setCreatedAt(3000);
        $socialAccount->setData(json_encode(['name' => 'The Octocat', 'avatar_url' => 'https://example.com/avatar.png'], JSON_THROW_ON_ERROR));
        $socialAccount->save();

        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->method('createResponse')->willReturn($response);
        $response->method('withHeader')->willReturnSelf();

        $expected = [
            'userSessionHistory' => [
                ['ip' => '203.0.113.5', 'user_agent' => 'TestAgent/1.0', 'created_at' => 1000, 'updated_at' => 2000],
            ],
            'userSocialAccount' => [
                [
                    'provider' => 'github',
                    'username' => 'octocat',
                    'email' => 'octocat@example.com',
                    'created_at' => 3000,
                    'data' => ['name' => 'The Octocat', 'avatar_url' => 'https://example.com/avatar.png'],
                ],
            ],
        ];

        $body = $this->createMock(StreamInterface::class);
        $body->expects($this->once())
            ->method('write')
            ->with($this->callback(
                static fn (string $json): bool => json_decode($json, true) === $expected,
            ));
        $response->method('getBody')->willReturn($body);

        $result = $controller->export($request);

        $this->assertSame($response, $result);
    }

    public function testExportIncludesUserProfileFields(): void
    {
        $config = new ModuleConfig(enableGdprCompliance: true, gdprExportProperties: [
            'userProfile.public_email',
            'userProfile.name',
            'userProfile.gravatar_email',
            'userProfile.location',
            'userProfile.website',
            'userProfile.bio',
        ]);
        $this->harness = new ControllerHarness($config);
        $controller = $this->createController();
        $request = new ServerRequest('GET', '/');

        $user = $this->createUser();
        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn((string) $user->getId());
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $profile = new UserProfile();
        $profile->setUserId((int) $user->getId());
        $profile->setPublicEmail('public@example.com');
        $profile->setName('Jane Doe');
        $profile->setGravatarEmail('gravatar@example.com');
        $profile->setLocation('Berlin');
        $profile->setWebsite('https://example.com');
        $profile->setBio('Hello there');
        $profile->save();

        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->method('createResponse')->willReturn($response);
        $response->method('withHeader')->willReturnSelf();

        $expected = [
            'userProfile.public_email' => 'public@example.com',
            'userProfile.name' => 'Jane Doe',
            'userProfile.gravatar_email' => 'gravatar@example.com',
            'userProfile.location' => 'Berlin',
            'userProfile.website' => 'https://example.com',
            'userProfile.bio' => 'Hello there',
        ];

        $body = $this->createMock(StreamInterface::class);
        $body->expects($this->once())
            ->method('write')
            ->with($this->callback(
                static fn (string $json): bool => json_decode($json, true) === $expected,
            ));
        $response->method('getBody')->willReturn($body);

        $result = $controller->export($request);

        $this->assertSame($response, $result);
    }

    public function testExportReturnsData(): void
    {
        $config = new ModuleConfig(enableGdprCompliance: true, gdprExportProperties: ['email', 'username']);
        $this->harness = new ControllerHarness($config);
        $controller = $this->createController();
        $request = new ServerRequest('GET', '/');

        $user = $this->createUser();
        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn((string) $user->getId());
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->expects($this->once())
            ->method('createResponse')
            ->with(200)
            ->willReturn($response);
        $response->expects($this->exactly(2))
            ->method('withHeader')
            ->willReturnSelf();

        $body = $this->createMock(StreamInterface::class);
        $body->expects($this->once())
            ->method('write')
            ->with($this->callback(
                static fn (string $json): bool => json_decode($json, true) === ['email' => 'test@example.com', 'username' => 'testuser'],
            ));
        $response->method('getBody')->willReturn($body);

        $result = $controller->export($request);

        $this->assertSame($response, $result);
    }

    public function testExportWhenGuestShowsError(): void
    {
        $config = new ModuleConfig(enableGdprCompliance: true);
        $this->harness = new ControllerHarness($config);
        $controller = $this->createController();
        $request = new ServerRequest('GET', '/');

        $this->currentUser->method('getIdentity')->willReturn($this->createMock(GuestIdentityInterface::class));

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->method('withViewPath')->willReturnSelf();
        $this->viewRenderer->method('render')->willReturn($response);

        $result = $controller->export($request);

        $this->assertSame($response, $result);
    }

    public function testGdprConsentGetShowsConsentDateWhenAlreadyConsented(): void
    {
        $config = new ModuleConfig(enableGdprCompliance: true);
        $this->harness = new ControllerHarness($config);
        $controller = $this->createController();
        $request = new ServerRequest('GET', '/');

        $user = $this->createUser(gdprConsent: true, gdprConsentDate: 1700000000);
        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn((string) $user->getId());
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $profile = new UserProfile();
        $profile->setUserId((int) $user->getId());
        $profile->setTimezone('America/New_York');
        $profile->save();

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->expects($this->once())
            ->method('withViewPath')
            ->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with(
                'settings/privacy/gdpr-consent',
                $this->callback(static function (array $params): bool {
                    return $params['model']->consent === true
                        && $params['model']->consentDate === 1700000000
                        && $params['model']->timezone === 'America/New_York';
                }),
            )
            ->willReturn($response);

        $result = $controller->gdprConsent($request);

        $this->assertSame($response, $result);
    }

    public function testGdprConsentGetShowsForm(): void
    {
        $config = new ModuleConfig(enableGdprCompliance: true);
        $this->harness = new ControllerHarness($config);
        $controller = $this->createController();
        $request = new ServerRequest('GET', '/');

        $user = $this->createUser(gdprConsent: false);
        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn((string) $user->getId());
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->expects($this->once())
            ->method('withViewPath')
            ->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with(
                'settings/privacy/gdpr-consent',
                $this->callback(static function (array $params): bool {
                    return $params['model']->consent === false && $params['model']->timezone === null;
                }),
            )
            ->willReturn($response);

        $result = $controller->gdprConsent($request);

        $this->assertSame($response, $result);
    }

    public function testGdprConsentPostAlreadyConsentedResubmitIsNoop(): void
    {
        $config = new ModuleConfig(enableGdprCompliance: true);
        $this->harness = new ControllerHarness($config);
        $controller = $this->createController();
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['gdpr-consent' => ['consent' => '1']]);

        $this->hydrator->method('hydrate')->willReturnCallback(
            function (object $object, array $data = []): void {
                if (property_exists($object, 'consent') && isset($data['consent'])) {
                    $object->consent = (bool) $data['consent'];
                }
            },
        );

        $user = $this->createUser(gdprConsent: true);
        $consentDate = $user->getGdprConsentDate();
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

        $result = $controller->gdprConsent($request);

        $this->assertSame($response, $result);
        $updated = User::findById((int) $user->getId());
        $this->assertNotNull($updated);
        $this->assertSame($consentDate, $updated->getGdprConsentDate());
    }

    public function testGdprConsentPostCannotRevokeConsent(): void
    {
        $config = new ModuleConfig(enableGdprCompliance: true);
        $this->harness = new ControllerHarness($config);
        $controller = $this->createController();
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['gdpr-consent' => ['consent' => '0']]);

        $this->hydrator->method('hydrate')->willReturnCallback(
            function (object $object, array $data = []): void {
                if (property_exists($object, 'consent') && isset($data['consent'])) {
                    $object->consent = (bool) $data['consent'];
                }
            },
        );

        $user = $this->createUser(gdprConsent: true);
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

        $result = $controller->gdprConsent($request);

        $this->assertSame($response, $result);
        $updated = User::findById((int) $user->getId());
        $this->assertNotNull($updated);
        $this->assertTrue($updated->isGdprConsent());
    }

    public function testGdprConsentPostSavesAndRedirects(): void
    {
        $config = new ModuleConfig(enableGdprCompliance: true);
        $this->harness = new ControllerHarness($config);
        $controller = $this->createController();
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['gdpr-consent' => ['consent' => '1']]);

        $this->hydrator->method('hydrate')->willReturnCallback(
            function (object $object, array $data = []): void {
                if (property_exists($object, 'consent') && isset($data['consent'])) {
                    $object->consent = (bool) $data['consent'];
                }
            },
        );

        $user = $this->createUser(gdprConsent: false);
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

        $result = $controller->gdprConsent($request);

        $this->assertSame($response, $result);
        $updated = User::findById((int) $user->getId());
        $this->assertNotNull($updated);
        $this->assertTrue($updated->isGdprConsent());
        $this->assertNotNull($updated->getGdprConsentDate());
    }

    public function testNetworksShowsConnectedAccounts(): void
    {
        $controller = $this->createController();
        $request = new ServerRequest('GET', '/');

        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn('1');
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->expects($this->once())
            ->method('withViewPath')
            ->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('settings/networks', $this->anything())
            ->willReturn($response);

        $result = $controller->networks($request);

        $this->assertSame($response, $result);
    }

    public function testNetworksWhenGuestShowsError(): void
    {
        $controller = $this->createController();
        $request = new ServerRequest('GET', '/');

        $this->currentUser->method('getIdentity')->willReturn($this->createMock(GuestIdentityInterface::class));

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->expects($this->once())
            ->method('withViewPath')
            ->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->willReturn($response);

        $result = $controller->networks($request);

        $this->assertSame($response, $result);
    }

    public function testPrivacyShowsView(): void
    {
        $controller = $this->createController();
        $request = new ServerRequest('GET', '/');

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->expects($this->once())
            ->method('withViewPath')
            ->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('settings/privacy', $this->anything())
            ->willReturn($response);

        $result = $controller->privacy($request);

        $this->assertSame($response, $result);
    }

    public function testProfileGetCreatesNewProfileWhenNoneExists(): void
    {
        $controller = $this->createController();
        $request = new ServerRequest('GET', '/');

        $user = $this->createUser();
        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn((string) $user->getId());
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $captured = [];
        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->method('withViewPath')->willReturnSelf();
        $this->viewRenderer->method('render')
            ->willReturnCallback(function (string $view, array $params) use (&$captured, $response): ResponseInterface {
                $captured = $params;
                return $response;
            });

        $controller->userProfile($request);

        $this->assertInstanceOf(UserProfile::class, $captured['userProfile']);
        $this->assertSame((int) $user->getId(), $captured['userProfile']->getUserId());
    }

    public function testProfileGetDoesNotShowSwitchedBannerWhenNotSwitched(): void
    {
        $controller = $this->createController();
        $request = new ServerRequest('GET', '/');

        $user = $this->createUser();
        $this->createUserProfile((int) $user->getId());
        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn((string) $user->getId());
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $captured = [];
        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->expects($this->once())
            ->method('withViewPath')
            ->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->willReturnCallback(function (string $view, array $params) use (&$captured, $response): ResponseInterface {
                $captured = $params;
                return $response;
            });

        $controller->userProfile($request);

        $this->assertFalse($captured['isSwitched']);
        $this->assertNull($captured['originalUser']);
    }

    public function testProfileGetShowsFormWithExistingProfile(): void
    {
        $controller = $this->createController();
        $request = new ServerRequest('GET', '/');

        $user = $this->createUser();
        $this->createUserProfile((int) $user->getId());
        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn((string) $user->getId());
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->expects($this->once())
            ->method('withViewPath')
            ->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('settings/profile', $this->anything())
            ->willReturn($response);

        $result = $controller->userProfile($request);

        $this->assertSame($response, $result);
    }

    public function testProfileGetShowsSwitchedBanner(): void
    {
        $originalUser = $this->createUser(username: 'original', email: 'original@example.com');

        $controller = $this->createController();
        $request = new ServerRequest('GET', '/');

        $user = $this->createUser(username: 'switcheduser', email: 'switched@example.com');
        $this->createUserProfile((int) $user->getId());
        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn((string) $user->getId());
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $this->harness->getSession()->set('voyti_original_user', (string) $originalUser->getId());

        $captured = [];
        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->expects($this->once())
            ->method('withViewPath')
            ->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->willReturnCallback(function (string $view, array $params) use (&$captured, $response): ResponseInterface {
                $captured = $params;
                return $response;
            });

        $controller->userProfile($request);

        $this->assertTrue($captured['isSwitched']);
        $this->assertSame($originalUser->getId(), $captured['originalUser']->getId());
    }

    public function testProfilePostUpdatesAndRedirects(): void
    {
        $controller = $this->createController();
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['userProfile' => ['name' => 'John', 'publicEmail' => '', 'gravatarEmail' => '', 'location' => '', 'website' => '', 'timezone' => '', 'bio' => '']]);

        $this->hydrator->method('hydrate')->willReturnCallback(
            function (object $object, array $data = []): void {
                $this->hydrateObject($object, $data);
            },
        );

        $user = $this->createUser();
        $this->createUserProfile((int) $user->getId(), name: 'OldName');
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

        $result = $controller->userProfile($request);

        $this->assertSame($response, $result);
        $updatedProfile = UserProfile::findByUserId((int) $user->getId());
        $this->assertNotNull($updatedProfile);
        $this->assertSame('John', $updatedProfile->getName());
    }

    public function testProfileWhenGuestShowsError(): void
    {
        $controller = $this->createController();
        $request = new ServerRequest('GET', '/');

        $this->currentUser->method('getIdentity')->willReturn($this->createMock(GuestIdentityInterface::class));

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->method('withViewPath')->willReturnSelf();
        $this->viewRenderer->method('render')->willReturn($response);

        $result = $controller->userProfile($request);

        $this->assertSame($response, $result);
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
            ->with('settings/two-factor', $this->callback(
                static fn (array $params): bool => $params['method'] === 'email'
                    && $params['emailCodeSent'] === true
                    && $params['preloadContent'] === true,
            ))
            ->willReturn($response);

        $result = $controller->twoFactorDisableSendCode($request);

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

        $result = $controller->twoFactorDisableSendCode($request);

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

        $result = $controller->twoFactorDisableSendCode($request);

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

        $result = $controller->twoFactorDisableSendCode($request);

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

        $result = $controller->twoFactorDisable($request);

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
            ->with('settings/two-factor', $this->callback(
                static fn (array $params): bool => $params['method'] === 'email'
                    && $params['emailCodeSent'] === true
                    && $params['preloadContent'] === true
                    && $params['errors'] !== [],
            ))
            ->willReturn($response);

        $result = $controller->twoFactorDisable($request);

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
            ->with('settings/two-factor', $this->callback(
                static fn (array $params): bool => $params['method'] === 'google'
                    && $params['emailCodeSent'] === false
                    && $params['preloadContent'] === true
                    && $params['errors'] !== [],
            ))
            ->willReturn($response);

        $result = $controller->twoFactorDisable($request);

        $this->assertSame($response, $result);
        $updated = User::findById((int) $user->getId());
        $this->assertNotNull($updated);
        $this->assertFalse($updated->isAuthTfEnabled());
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

        $result = $controller->twoFactorDisable($request);

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

        $result = $controller->twoFactorDisable($request);

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
            ->with('settings/two-factor/_email', $this->callback(
                static fn (array $params): bool => $params['emailCodeSent'] === false,
            ))
            ->willReturn($response);

        $result = $controller->twoFactorEmail($request);

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
            ->with('settings/two-factor', $this->callback(
                static fn (array $params): bool => $params['method'] === 'email'
                    && $params['emailCodeSent'] === false
                    && $params['preloadContent'] === true,
            ))
            ->willReturn($response);

        $result = $controller->twoFactorEmail($request);

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

        $result = $controller->twoFactorEmail($request);

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

        $result = $controller->twoFactorEmail($request);

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

        $result = $controller->twoFactorEnable($request);

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

        $result = $controller->twoFactorEnable($request);

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
        $this->responseFactory->expects($this->once())
            ->method('createResponse')
            ->with(302)
            ->willReturn($response);
        $response->expects($this->once())
            ->method('withHeader')
            ->willReturnSelf();

        $result = $controller->twoFactorEnable($request);

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
            ->with('settings/two-factor', $this->callback(
                static fn (array $params): bool => $params['method'] === 'email'
                    && $params['emailCodeSent'] === true
                    && $params['preloadContent'] === true,
            ))
            ->willReturn($response);

        $result = $controller->twoFactorEnable($request);

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
            ->with('settings/two-factor', $this->callback(
                static fn (array $params): bool => $params['method'] === 'google'
                    && $params['emailCodeSent'] === false
                    && $params['preloadContent'] === true,
            ))
            ->willReturn($response);

        $result = $controller->twoFactorEnable($request);

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
        $this->responseFactory->expects($this->once())
            ->method('createResponse')
            ->with(302)
            ->willReturn($response);
        $response->expects($this->once())
            ->method('withHeader')
            ->willReturnSelf();

        $result = $controller->twoFactorEnable($request);

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
            ->with('settings/two-factor/_google', $this->callback(
                static fn (array $params): bool => $params['qrCodeUri'] === '<svg></svg>' && $params['secret'] === null,
            ))
            ->willReturn($response);

        $result = $controller->twoFactorGoogle($request);

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
            ->with('settings/two-factor', $this->callback(
                static fn (array $params): bool => $params['method'] === 'google'
                    && $params['emailCodeSent'] === false
                    && $params['preloadContent'] === true,
            ))
            ->willReturn($response);

        $result = $controller->twoFactorGoogle($request);

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

        $result = $controller->twoFactorGoogle($request);

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

        $result = $controller->twoFactorGoogle($request);

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

        $result = $controller->twoFactorRenew($request);

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

        $result = $controller->twoFactorRenew($request);

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

        $result = $controller->twoFactorRenew($request);

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

        $result = $controller->twoFactorRenew($request);

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

        $result = $controller->twoFactorRenew($request);

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

        $result = $controller->twoFactorRenew($request);

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

        $result = $controller->twoFactorSendEmailCode($request);

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
            ->with('settings/two-factor', $this->callback(
                static fn (array $params): bool => $params['method'] === 'email'
                    && $params['emailCodeSent'] === true
                    && $params['preloadContent'] === true,
            ))
            ->willReturn($response);

        $result = $controller->twoFactorSendEmailCode($request);

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

        $result = $controller->twoFactorSendEmailCode($request);

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

        $result = $controller->twoFactorSendEmailCode($request);

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
            ->with('settings/two-factor', $this->callback(
                static fn (array $params): bool => $params['emailCodeSent'] === false
                    && $params['preloadContent'] === true,
            ))
            ->willReturn($response);

        $result = $controller->twoFactor($request);

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

        $result = $controller->twoFactor($request);

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
            ->with('settings/two-factor', $this->callback(
                static fn (array $params): bool => $params['method'] === 'google'
                    && $params['emailCodeSent'] === false
                    && $params['preloadContent'] === false,
            ))
            ->willReturn($response);

        $result = $controller->twoFactor($request);

        $this->assertSame($response, $result);
    }

    private function createController(): SettingsController
    {
        return $this->harness->createSettingsController(
            translator: $this->translator,
            viewRenderer: $this->viewRenderer,
            validator: $this->validator,
            currentUser: $this->currentUser,
            responseFactory: $this->responseFactory,
            hydrator: $this->hydrator,
            flash: $this->flash,
            passwordHasher: $this->passwordHasher,
            emailChangeStrategyFactory: $this->emailChangeStrategyFactory,
            twoFactorQrCodeService: $this->twoFactorQrCodeService,
            twoFactorEmailCodeService: $this->twoFactorEmailCodeService,
            emailChangeService: $this->emailChangeService,
            terminateUserSessionsService: $this->terminateUserSessionsService,
        );
    }

    private function createSocialAccount(int $userId, string $provider = 'github', string $username = 'octocat'): UserSocialAccount
    {
        $account = new UserSocialAccount();
        $account->setUserId($userId);
        $account->setProvider($provider);
        $account->setClientId('client123');
        $account->setUsername($username);
        $account->setCreatedAt(time());
        $account->save();

        return $account;
    }

    private function createUser(
        string $username = 'testuser',
        string $email = 'test@example.com',
        string $password = 'secret',
        bool $blocked = false,
        bool $confirmed = true,
        bool $authTfEnabled = false,
        ?string $authTfType = null,
        ?string $authTfKey = null,
        bool $gdprConsent = false,
        ?int $gdprConsentDate = null,
        bool $anonymized = false,
    ): User {
        $user = new User();
        $user->setUsername($username);
        $user->setEmail($email);
        $user->setPasswordHash($this->passwordHasher->hash($password));
        $user->setAuthKey('key');
        $user->setCreatedAt(time());
        $user->setUpdatedAt(time());
        $user->setBlockedAt($blocked ? time() : null);
        $user->setConfirmedAt($confirmed ? time() : null);
        $user->setAuthTfEnabled($authTfEnabled);
        $user->setAuthTfType($authTfType);
        $user->setAuthTfKey($authTfKey);
        $user->setGdprConsent($gdprConsent);
        $user->setGdprConsentDate($gdprConsentDate);
        $user->setAnonymized($anonymized);
        $user->save();

        return $user;
    }

    private function createUserProfile(int $userId, string $name = 'John'): UserProfile
    {
        $profile = new UserProfile();
        $profile->setUserId($userId);
        $profile->setName($name);
        $profile->save();

        return $profile;
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
