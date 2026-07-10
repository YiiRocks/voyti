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
use YiiRocks\Voyti\Repository\UserProfileRepository;
use YiiRocks\Voyti\Repository\UserRepository;
use YiiRocks\Voyti\Repository\UserSessionHistoryRepository;
use YiiRocks\Voyti\Repository\UserSocialAccountRepository;
use YiiRocks\Voyti\Repository\UserTokenRepository;
use YiiRocks\Voyti\Service\EmailChangeService;
use YiiRocks\Voyti\Service\TwoFactor\EmailCodeGeneratorService;
use YiiRocks\Voyti\Service\TwoFactor\QrCodeUriGeneratorService;
use YiiRocks\Voyti\Service\UserSessionHistory\TerminateUserSessionsService;
use YiiRocks\Voyti\Strategy\EmailChangeStrategyFactory;
use YiiRocks\Voyti\Strategy\InsecureEmailChangeStrategy;
use YiiRocks\Voyti\tests\Support\ControllerHarness;
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
    private UserProfileRepository&MockObject $userProfileRepository;
    private UserRepository&MockObject $userRepository;
    private UserSessionHistoryRepository&MockObject $userSessionHistoryRepository;
    private UserSocialAccountRepository&MockObject $userSocialAccountRepository;
    private UserTokenRepository&MockObject $userTokenRepository;
    private ValidatorInterface&MockObject $validator;
    private WebViewRenderer&MockObject $viewRenderer;

    protected function setUp(): void
    {
        $this->config = new ModuleConfig();
        $this->harness = new ControllerHarness($this->config);
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->userProfileRepository = $this->createMock(UserProfileRepository::class);
        $this->userSessionHistoryRepository = $this->createMock(UserSessionHistoryRepository::class);
        $this->userSocialAccountRepository = $this->createMock(UserSocialAccountRepository::class);
        $this->userTokenRepository = $this->createMock(UserTokenRepository::class);
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

    public function testAccountGetShowsForm(): void
    {
        $controller = $this->createController();
        $request = new ServerRequest('GET', '/');

        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn('1');
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $user = $this->createMock(User::class);
        $user->method('getUsername')->willReturn('testuser');
        $user->method('getEmail')->willReturn('test@example.com');
        $this->userRepository->method('findById')->willReturn($user);

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
        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn('1');
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $user = $this->createMock(User::class);
        $user->method('getUsername')->willReturn('testuser');
        $user->method('getEmail')->willReturn('test@example.com');
        $user->method('getId')->willReturn('1');
        $user->expects($this->once())->method('setUsername');
        $user->expects($this->once())->method('setUpdatedAt');
        $user->expects($this->once())->method('save');

        $this->userRepository->method('findById')->willReturn($user);

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
        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn('1');
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $user = $this->createMock(User::class);
        $user->method('getUsername')->willReturn('testuser');
        $user->method('getEmail')->willReturn('old@example.com');
        $user->method('getId')->willReturn('1');
        $this->userRepository->method('findById')->willReturn($user);

        $strategy = $this->createMock(InsecureEmailChangeStrategy::class);
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
        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn('1');
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $user = $this->createMock(User::class);
        $user->method('getUsername')->willReturn('testuser');
        $user->method('getEmail')->willReturn('test@example.com');
        $user->method('getId')->willReturn('1');
        $user->expects($this->once())->method('setUsername');
        $user->expects($this->once())->method('setPasswordHash');
        $user->expects($this->once())->method('setPasswordChangedAt');
        $user->expects($this->once())->method('setUpdatedAt');
        $user->expects($this->once())->method('save');

        $this->userRepository->method('findById')->willReturn($user);

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
        $identity->method('getId')->willReturn('1');
        $this->currentUser->method('getIdentity')->willReturn($identity);
        $this->userRepository->method('findById')->willReturn(null);

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
        $hash = $this->passwordHasher->hash($password);

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
        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn('1');
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn('1');
        $user->method('getPasswordHash')->willReturn($hash);
        $user->expects($this->once())->method('setEmail');
        $user->expects($this->once())->method('setUsername');
        $user->expects($this->once())->method('setAnonymized');
        $user->expects($this->once())->method('setBlockedAt');
        $user->expects($this->once())->method('setAuthKey');
        $user->expects($this->once())->method('save');
        $this->userRepository->method('findById')->willReturn($user);

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->expects($this->once())
            ->method('withViewPath')
            ->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->willReturn($response);

        $result = $controller->anonymize($request);

        $this->assertSame($response, $result);
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

        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn('1');
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $user = $this->createMock(User::class);
        $this->userRepository->method('findById')->willReturn($user);
        $this->emailChangeService->expects($this->once())->method('run')->with('bad-code', $user)->willReturn(false);

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

        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn('1');
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $user = $this->createMock(User::class);
        $this->userRepository->method('findById')->willReturn($user);
        $this->emailChangeService->expects($this->once())->method('run')->with('good-code', $user)->willReturn(true);

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
        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn('1');
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn('1');
        $user->method('getPasswordHash')->willReturn($this->passwordHasher->hash('correctpassword'));
        $this->userRepository->method('findById')->willReturn($user);
        $this->userRepository->expects($this->never())->method('delete');

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

    public function testDeletePostWithValidPasswordDeletesUser(): void
    {
        $config = new ModuleConfig(allowAccountDelete: true);
        $this->harness = new ControllerHarness($config);
        $controller = $this->createController();

        $password = 'mypassword';
        $hash = $this->passwordHasher->hash($password);

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
        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn('1');
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn('1');
        $user->method('getPasswordHash')->willReturn($hash);
        $this->userRepository->method('findById')->willReturn($user);
        $this->userRepository->expects($this->once())->method('delete')->with($user);

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->expects($this->once())
            ->method('withViewPath')
            ->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->willReturn($response);

        $result = $controller->delete($request);

        $this->assertSame($response, $result);
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

        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn('1');
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $account = $this->createMock(UserSocialAccount::class);
        $account->method('getId')->willReturn(1);
        $account->expects($this->once())->method('delete');

        $this->userSocialAccountRepository->method('findByUserId')->willReturn([$account]);

        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->expects($this->once())
            ->method('createResponse')
            ->with(302)
            ->willReturn($response);
        $response->expects($this->once())
            ->method('withHeader')
            ->willReturnSelf();

        $result = $controller->disconnect($request, 1);

        $this->assertSame($response, $result);
    }

    public function testDisconnectWithNoAccountShowsNotFound(): void
    {
        $controller = $this->createController();
        $request = new ServerRequest('GET', '/');

        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn('1');
        $this->currentUser->method('getIdentity')->willReturn($identity);
        $this->userSocialAccountRepository->method('findByUserId')->willReturn([]);

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

        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn('1');
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn('1');
        $this->userRepository->method('findById')->willReturn($user);

        $sessionEntry = $this->createMock(UserSessionHistory::class);
        $sessionEntry->method('getIp')->willReturn('203.0.113.5');
        $sessionEntry->method('getUserAgent')->willReturn('TestAgent/1.0');
        $sessionEntry->method('getCreatedAt')->willReturn(1000);
        $sessionEntry->method('getUpdatedAt')->willReturn(2000);
        $this->userSessionHistoryRepository->expects($this->once())->method('findByUserId')->with(1)->willReturn([$sessionEntry]);

        $socialAccount = $this->createMock(UserSocialAccount::class);
        $socialAccount->method('getProvider')->willReturn('github');
        $socialAccount->method('getUsername')->willReturn('octocat');
        $socialAccount->method('getEmail')->willReturn('octocat@example.com');
        $socialAccount->method('getCreatedAt')->willReturn(3000);
        $socialAccount->method('getDecodedData')->willReturn(['name' => 'The Octocat', 'avatar_url' => 'https://example.com/avatar.png']);
        $this->userSocialAccountRepository->expects($this->once())->method('findByUserId')->with(1)->willReturn([$socialAccount]);

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

        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn('1');
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $profile = $this->createMock(UserProfile::class);
        $profile->method('getPublicEmail')->willReturn('public@example.com');
        $profile->method('getName')->willReturn('Jane Doe');
        $profile->method('getGravatarEmail')->willReturn('gravatar@example.com');
        $profile->method('getLocation')->willReturn('Berlin');
        $profile->method('getWebsite')->willReturn('https://example.com');
        $profile->method('getBio')->willReturn('Hello there');

        $user = $this->createMock(User::class);
        $user->method('getProfile')->willReturn($profile);
        $this->userRepository->method('findById')->willReturn($user);

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

        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn('1');
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $user = $this->createMock(User::class);
        $user->method('getEmail')->willReturn('test@example.com');
        $user->method('getUsername')->willReturn('testuser');
        $user->method('getProfile')->willReturn(null);
        $user->method('getId')->willReturn('1');
        $this->userRepository->method('findById')->willReturn($user);

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

        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn('1');
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $profile = $this->createMock(UserProfile::class);
        $profile->method('getTimezone')->willReturn('America/New_York');

        $user = $this->createMock(User::class);
        $user->method('isGdprConsent')->willReturn(true);
        $user->method('getGdprConsentDate')->willReturn(1700000000);
        $user->method('getProfile')->willReturn($profile);
        $this->userRepository->method('findById')->willReturn($user);

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

        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn('1');
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $user = $this->createMock(User::class);
        $user->method('isGdprConsent')->willReturn(false);
        $user->method('getGdprConsentDate')->willReturn(null);
        $user->method('getProfile')->willReturn(null);
        $this->userRepository->method('findById')->willReturn($user);

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

        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn('1');
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn('1');
        $user->method('isGdprConsent')->willReturn(true);
        $user->expects($this->never())->method('setGdprConsent');
        $user->expects($this->never())->method('setGdprConsentDate');
        $user->expects($this->never())->method('save');
        $this->userRepository->method('findById')->willReturn($user);

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

        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn('1');
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn('1');
        $user->method('isGdprConsent')->willReturn(true);
        $user->expects($this->never())->method('setGdprConsent');
        $user->expects($this->never())->method('setGdprConsentDate');
        $user->expects($this->never())->method('save');
        $this->userRepository->method('findById')->willReturn($user);

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

        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn('1');
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn('1');
        $user->method('isGdprConsent')->willReturn(false);
        $user->expects($this->once())->method('setGdprConsent')->with(true);
        $user->expects($this->once())->method('setGdprConsentDate');
        $user->expects($this->once())->method('save');
        $this->userRepository->method('findById')->willReturn($user);

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
    }

    public function testNetworksShowsConnectedAccounts(): void
    {
        $controller = $this->createController();
        $request = new ServerRequest('GET', '/');

        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn('1');
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $this->userSocialAccountRepository->method('findByUserId')->willReturn([]);

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

        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn('1');
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn('1');
        $user->method('getProfile')->willReturn(null);
        $this->userRepository->method('findById')->willReturn($user);

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
        $this->assertSame(1, $captured['userProfile']->getUserId());
    }

    public function testProfileGetDoesNotShowSwitchedBannerWhenNotSwitched(): void
    {
        $controller = $this->createController();
        $request = new ServerRequest('GET', '/');

        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn('1');
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn('1');
        $userProfile = $this->createMock(UserProfile::class);
        $userProfile->method('getName')->willReturn('John');
        $user->method('getProfile')->willReturn($userProfile);
        $this->userRepository->method('findById')->willReturn($user);

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

        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn('1');
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn('1');
        $userProfile = $this->createMock(UserProfile::class);
        $userProfile->method('getName')->willReturn('John');
        $user->method('getProfile')->willReturn($userProfile);
        $this->userRepository->method('findById')->willReturn($user);

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
        $controller = $this->createController();
        $request = new ServerRequest('GET', '/');

        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn('1');
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn('1');
        $userProfile = $this->createMock(UserProfile::class);
        $userProfile->method('getName')->willReturn('John');
        $user->method('getProfile')->willReturn($userProfile);
        $this->userRepository->method('findById')->willReturn($user);

        $this->harness->getSession()->set('voyti_original_user', '2');

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
        $this->assertSame($user, $captured['originalUser']);
    }

    public function testProfilePostUpdatesAndRedirects(): void
    {
        $controller = $this->createController();
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['userProfile' => ['name' => 'John', 'publicEmail' => '', 'gravatarEmail' => '', 'location' => '', 'website' => '', 'timezone' => '', 'bio' => '']]);

        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn('1');
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn('1');
        $userProfile = $this->createMock(UserProfile::class);
        $userProfile->method('getName')->willReturn('John');
        $user->method('getProfile')->willReturn($userProfile);
        $userProfile->expects($this->once())->method('save');
        $this->userRepository->method('findById')->willReturn($user);

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

    public function testTwoFactorDisable(): void
    {
        $config = new ModuleConfig(enableTwoFactorAuthentication: true);
        $this->harness = new ControllerHarness($config);
        $controller = $this->createController();
        $request = new ServerRequest('POST', '/');

        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn('1');
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn('1');
        $user->expects($this->once())->method('setAuthTfEnabled')->with(false);
        $user->expects($this->once())->method('setAuthTfKey')->with(null);
        $user->expects($this->once())->method('setAuthTfType')->with(null);
        $user->expects($this->once())->method('save');
        $this->userRepository->method('findById')->willReturn($user);

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

    public function testTwoFactorEmailRendersFragmentWithFragmentHeader(): void
    {
        $config = new ModuleConfig(enableTwoFactorAuthentication: true);
        $this->harness = new ControllerHarness($config);
        $controller = $this->createController();
        $request = (new ServerRequest('GET', '/'))->withHeader('X-Requested-With', 'XMLHttpRequest');

        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn('1');
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $user = $this->createMock(User::class);
        $user->method('isAuthTfEnabled')->willReturn(false);
        $user->expects($this->never())->method('setAuthTfType');
        $user->expects($this->never())->method('save');
        $this->userRepository->method('findById')->willReturn($user);
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

        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn('1');
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $user = $this->createMock(User::class);
        $user->method('isAuthTfEnabled')->willReturn(false);
        $this->userRepository->method('findById')->willReturn($user);

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

        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn('1');
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $user = $this->createMock(User::class);
        $user->method('isAuthTfEnabled')->willReturn(true);
        $this->userRepository->method('findById')->willReturn($user);

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

        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn('1');
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn('1');
        $user->method('getAuthTfKey')->willReturn('123456');
        $user->expects($this->once())->method('setAuthTfEnabled');
        $user->expects($this->once())->method('setAuthTfType');
        $user->expects($this->once())->method('save');

        $this->userRepository->method('findById')->willReturn($user);

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
    }

    public function testTwoFactorEnableWithInvalidEmailCodeShowsFormWithCodeSent(): void
    {
        $config = new ModuleConfig(enableTwoFactorAuthentication: true);
        $this->harness = new ControllerHarness($config);
        $controller = $this->createController();
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['method' => 'email', 'code' => 'wrong']);

        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn('1');
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn('1');
        $user->method('getAuthTfKey')->willReturn('123456');
        $user->expects($this->never())->method('setAuthTfEnabled');

        $this->userRepository->method('findById')->willReturn($user);

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
    }

    public function testTwoFactorEnableWithInvalidGoogleCodeShowsFormWithoutCodeSent(): void
    {
        $config = new ModuleConfig(enableTwoFactorAuthentication: true);
        $this->harness = new ControllerHarness($config);
        $controller = $this->createController();
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['method' => 'google', 'code' => 'wrong']);

        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn('1');
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn('1');
        $user->method('getAuthTfType')->willReturn('google');
        $user->method('getAuthTfKey')->willReturn(null);
        $user->expects($this->never())->method('setAuthTfEnabled');

        $this->userRepository->method('findById')->willReturn($user);
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

        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn('1');
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn('1');
        $user->method('getAuthTfType')->willReturn('google');
        $user->method('getAuthTfKey')->willReturn($secret);
        $user->expects($this->once())->method('setAuthTfType')->with('google');
        $user->expects($this->once())->method('setAuthTfEnabled')->with(true);
        $user->expects($this->once())->method('save');

        $this->userRepository->method('findById')->willReturn($user);

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
    }

    public function testTwoFactorGoogleRendersFragmentWithFragmentHeader(): void
    {
        $config = new ModuleConfig(enableTwoFactorAuthentication: true);
        $this->harness = new ControllerHarness($config);
        $controller = $this->createController();
        $request = (new ServerRequest('GET', '/'))->withHeader('X-Requested-With', 'XMLHttpRequest');

        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn('1');
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $user = $this->createMock(User::class);
        $user->method('isAuthTfEnabled')->willReturn(false);
        $user->method('getAuthTfType')->willReturn(null);
        $user->method('getAuthTfKey')->willReturn('secret');
        $this->userRepository->method('findById')->willReturn($user);
        $this->twoFactorQrCodeService->method('generateQrCodeSvg')->willReturn('<svg></svg>');

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->expects($this->once())
            ->method('withViewPath')
            ->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('renderPartial')
            ->with('settings/two-factor/_google', $this->callback(
                static fn (array $params): bool => $params['qrCodeUri'] === '<svg></svg>' && $params['secret'] === 'secret',
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

        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn('1');
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $user = $this->createMock(User::class);
        $user->method('isAuthTfEnabled')->willReturn(false);
        $user->method('getAuthTfType')->willReturn(null);
        $user->method('getAuthTfKey')->willReturn(null);
        $this->userRepository->method('findById')->willReturn($user);
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

        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn('1');
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $user = $this->createMock(User::class);
        $user->method('isAuthTfEnabled')->willReturn(true);
        $this->userRepository->method('findById')->willReturn($user);
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

        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn('1');
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $user = $this->createMock(User::class);
        $user->method('isAuthTfEnabled')->willReturn(false);
        $user->method('getAuthTfType')->willReturn('google');
        $user->method('getAuthTfKey')->willReturn('secret');
        $user->expects($this->never())->method('setAuthTfType');
        $this->userRepository->method('findById')->willReturn($user);

        $this->twoFactorQrCodeService->method('isAvailable')->willReturn(true);
        $this->twoFactorQrCodeService->method('generateQrCodeSvg')->willReturn('<svg></svg>');

        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->method('createResponse')->willReturn($response);
        $response->method('withHeader')->willReturnSelf();
        $response->method('getBody')->willReturn($this->createMock(StreamInterface::class));

        $result = $controller->twoFactorRenew($request);

        $this->assertSame($response, $result);
    }

    public function testTwoFactorRenewGeneratesNewSecret(): void
    {
        $config = new ModuleConfig(enableTwoFactorAuthentication: true);
        $this->harness = new ControllerHarness($config);
        $controller = $this->createController();
        $request = new ServerRequest('POST', '/');

        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn('1');
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $user = $this->createMock(User::class);
        $user->method('isAuthTfEnabled')->willReturn(false);
        $user->method('getAuthTfType')->willReturn('email');
        $user->method('getAuthTfKey')->willReturn('new-secret');
        $user->expects($this->once())->method('setAuthTfType')->with('google');
        $this->userRepository->method('findById')->willReturn($user);

        $this->twoFactorQrCodeService->method('isAvailable')->willReturn(true);
        $this->twoFactorQrCodeService->expects($this->once())
            ->method('generateQrCodeSvg')
            ->with($user, forceNewSecret: true)
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

        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn('1');
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $user = $this->createMock(User::class);
        $user->method('isAuthTfEnabled')->willReturn(true);
        $this->userRepository->method('findById')->willReturn($user);
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

        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn('1');
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $user = $this->createMock(User::class);
        $user->method('isAuthTfEnabled')->willReturn(false);
        $this->userRepository->method('findById')->willReturn($user);
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
        $identity->method('getId')->willReturn('1');
        $this->currentUser->method('getIdentity')->willReturn($identity);
        $this->userRepository->method('findById')->willReturn(null);

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

        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn('1');
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $user = $this->createMock(User::class);
        $user->method('isAuthTfEnabled')->willReturn(false);
        $user->method('getAuthTfType')->willReturn('email');
        $user->expects($this->never())->method('setAuthTfType');
        $user->expects($this->never())->method('save');
        $this->userRepository->method('findById')->willReturn($user);
        $this->twoFactorEmailCodeService->expects($this->once())->method('run')->with($user);

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->method('withViewPath')->willReturnSelf();
        $this->viewRenderer->method('render')->willReturn($response);

        $result = $controller->twoFactorSendEmailCode($request);

        $this->assertSame($response, $result);
    }

    public function testTwoFactorSendEmailCodeSendsCodeAndRendersView(): void
    {
        $config = new ModuleConfig(enableTwoFactorAuthentication: true);
        $this->harness = new ControllerHarness($config);
        $controller = $this->createController();
        $request = new ServerRequest('POST', '/');

        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn('1');
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $user = $this->createMock(User::class);
        $user->method('isAuthTfEnabled')->willReturn(false);
        $user->method('getAuthTfType')->willReturn(null);
        $user->expects($this->once())->method('setAuthTfType')->with('email');
        $user->expects($this->once())->method('save');
        $this->userRepository->method('findById')->willReturn($user);
        $this->twoFactorEmailCodeService->expects($this->once())->method('run')->with($user);

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
    }

    public function testTwoFactorSendEmailCodeWhenAlreadyEnabledRedirects(): void
    {
        $config = new ModuleConfig(enableTwoFactorAuthentication: true);
        $this->harness = new ControllerHarness($config);
        $controller = $this->createController();
        $request = new ServerRequest('POST', '/');

        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn('1');
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $user = $this->createMock(User::class);
        $user->method('isAuthTfEnabled')->willReturn(true);
        $this->userRepository->method('findById')->willReturn($user);
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

        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn('1');
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $user = $this->createMock(User::class);
        $user->method('isAuthTfEnabled')->willReturn(true);
        $user->method('getAuthTfType')->willReturn('google');
        $this->userRepository->method('findById')->willReturn($user);

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

        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn('1');
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $user = $this->createMock(User::class);
        $user->method('isAuthTfEnabled')->willReturn(false);
        $this->userRepository->method('findById')->willReturn($user);
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
            userRepository: $this->userRepository,
            userProfileRepository: $this->userProfileRepository,
            userSessionHistoryRepository: $this->userSessionHistoryRepository,
            userSocialAccountRepository: $this->userSocialAccountRepository,
            userTokenRepository: $this->userTokenRepository,
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
}
