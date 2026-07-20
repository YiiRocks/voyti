<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Controller\Privacy;

use DateTimeImmutable;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use YiiRocks\Voyti\Controller\Privacy\PrivacyController;
use YiiRocks\Voyti\Event\Gdpr\GdprEvent;
use YiiRocks\Voyti\Event\User\UserEvent;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Model\UserProfile;
use YiiRocks\Voyti\Model\UserSessions;
use YiiRocks\Voyti\Model\UserSocialAccount;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Service\UserSession\TerminateUserSessionsService;
use YiiRocks\Voyti\tests\Support\ControllerHarness;
use YiiRocks\Voyti\tests\Support\DatabaseSetupTrait;
use YiiRocks\Voyti\tests\Support\RedirectResponseMockTrait;
use YiiRocks\Voyti\tests\Support\TestPasswordHasherFactory;
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
final class PrivacyControllerTest extends TestCase
{
    use DatabaseSetupTrait;
    use RedirectResponseMockTrait;
    use UserFactoryTrait;

    private ModuleConfig $config;
    private CurrentUser&MockObject $currentUser;
    private FlashInterface&MockObject $flash;
    private ControllerHarness $harness;
    private HydratorInterface&MockObject $hydrator;
    private PasswordHasher $passwordHasher;
    private ResponseFactoryInterface&MockObject $responseFactory;
    private TerminateUserSessionsService&MockObject $terminateUserSessionsService;
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
        $this->passwordHasher = TestPasswordHasherFactory::create();
        $this->terminateUserSessionsService = $this->createMock(TerminateUserSessionsService::class);
    }

    protected function tearDown(): void
    {
        $this->tearDownDatabase();
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
            ->with('privacy/anonymize', $this->anything())
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
        $user = $this->createUser(passwordHash: $this->passwordHasher->hash($password), confirmedAt: time());
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
        $event = $this->harness->getEventDispatcher()->getEvent(GdprEvent::class);
        $this->assertNotNull($event);
        $this->assertTrue($event->getUser()->isAnonymized());
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
            ->with('privacy/delete', $this->anything())
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
        $user = $this->createUser(passwordHash: $this->passwordHasher->hash('correctpassword'), confirmedAt: time());
        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn((string) $user->getId());
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->expects($this->once())
            ->method('withViewPath')
            ->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('privacy/delete', $this->anything())
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
        $user = $this->createUser(passwordHash: $this->passwordHasher->hash($password), confirmedAt: time());
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
        $event = $this->harness->getEventDispatcher()->getEvent(UserEvent::class);
        $this->assertNotNull($event);
        $this->assertSame(UserEvent::DELETE, $event->getType());
    }

    public function testExportIncludesSessionsAndSocialAccounts(): void
    {
        $config = new ModuleConfig(enableGdprCompliance: true, gdprExportProperties: ['userSessions', 'userSocialAccount']);
        $this->harness = new ControllerHarness($config);
        $controller = $this->createController();
        $request = new ServerRequest('GET', '/');

        $user = $this->createUser(confirmedAt: time());
        $userId = (int) $user->getId();
        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn((string) $userId);
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $sessionEntry = new UserSessions();
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
            'userSessions' => [
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
                static fn(string $json): bool => json_decode($json, true) === $expected,
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
            'userProfile.birthday',
        ]);
        $this->harness = new ControllerHarness($config);
        $controller = $this->createController();
        $request = new ServerRequest('GET', '/');

        $user = $this->createUser(confirmedAt: time());
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
        $profile->setBirthday(new DateTimeImmutable('1990-05-15'));
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
            'userProfile.birthday' => '1990-05-15',
        ];

        $body = $this->createMock(StreamInterface::class);
        $body->expects($this->once())
            ->method('write')
            ->with($this->callback(
                static fn(string $json): bool => json_decode($json, true) === $expected,
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

        $user = $this->createUser(confirmedAt: time());
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
                static fn(string $json): bool => json_decode($json, true) === ['email' => 'test@example.com', 'username' => 'testuser'],
            ));
        $response->method('getBody')->willReturn($body);

        $result = $controller->export($request);

        $this->assertSame($response, $result);
    }

    public function testExportWhenGuestRedirectsToLogin(): void
    {
        $config = new ModuleConfig(enableGdprCompliance: true);
        $this->harness = new ControllerHarness($config);
        $controller = $this->createController();
        $request = new ServerRequest('GET', '/');

        $this->currentUser->method('getIdentity')->willReturn($this->createMock(GuestIdentityInterface::class));

        $response = $this->mockRedirectResponse($this->responseFactory, '//voyti/session-login');

        $result = $controller->export($request);

        $this->assertSame($response, $result);
    }

    public function testExportWhenUserNotFoundShowsError(): void
    {
        $config = new ModuleConfig(enableGdprCompliance: true);
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

        $result = $controller->export($request);

        $this->assertSame($response, $result);
    }

    public function testGdprConsentGetShowsConsentDateWhenAlreadyConsented(): void
    {
        $config = new ModuleConfig(enableGdprCompliance: true);
        $this->harness = new ControllerHarness($config);
        $controller = $this->createController();
        $request = new ServerRequest('GET', '/');

        $user = $this->createUser(gdprConsent: true, gdprConsentDate: 1700000000, confirmedAt: time());
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
                'privacy/gdpr-consent',
                $this->callback(static function (array $params): bool {
                    return $params['form']->consent === true
                        && $params['form']->consentDate === 1700000000
                        && $params['form']->timezone === 'America/New_York';
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

        $user = $this->createUser(gdprConsent: false, confirmedAt: time());
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
                'privacy/gdpr-consent',
                $this->callback(static function (array $params): bool {
                    return $params['form']->consent === false && $params['form']->timezone === null;
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

        $user = $this->createUser(gdprConsent: true, confirmedAt: time());
        $consentDate = $user->getGdprConsentDate();
        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn((string) $user->getId());
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $response = $this->mockRedirectResponse($this->responseFactory);

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

        $user = $this->createUser(gdprConsent: true, confirmedAt: time());
        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn((string) $user->getId());
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $response = $this->mockRedirectResponse($this->responseFactory);

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

        $user = $this->createUser(gdprConsent: false, confirmedAt: time());
        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn((string) $user->getId());
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $response = $this->mockRedirectResponse($this->responseFactory);

        $result = $controller->gdprConsent($request);

        $this->assertSame($response, $result);
        $updated = User::findById((int) $user->getId());
        $this->assertNotNull($updated);
        $this->assertTrue($updated->isGdprConsent());
        $this->assertNotNull($updated->getGdprConsentDate());
    }

    public function testIndexShowsView(): void
    {
        $controller = $this->createController();

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->expects($this->once())
            ->method('withViewPath')
            ->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('privacy/index', $this->anything())
            ->willReturn($response);

        $result = $controller->index();

        $this->assertSame($response, $result);
    }

    private function createController(): PrivacyController
    {
        return $this->harness->createPrivacyController(
            translator: $this->translator,
            viewRenderer: $this->viewRenderer,
            validator: $this->validator,
            currentUser: $this->currentUser,
            responseFactory: $this->responseFactory,
            hydrator: $this->hydrator,
            flash: $this->flash,
            passwordHasher: $this->passwordHasher,
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
}
