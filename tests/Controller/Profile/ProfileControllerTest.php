<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Controller\Profile;

use DateTimeImmutable;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use YiiRocks\Voyti\Controller\Profile\ProfileController;
use YiiRocks\Voyti\Enum\ProfileVisibility;
use YiiRocks\Voyti\Helper\AuthHelper;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Model\UserProfile;
use YiiRocks\Voyti\ModuleConfig;
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
use Yiisoft\User\Guest\GuestIdentity;
use Yiisoft\User\Guest\GuestIdentityInterface;
use Yiisoft\Validator\Validator;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

#[AllowMockObjectsWithoutExpectations]
final class ProfileControllerTest extends TestCase
{
    use DatabaseSetupTrait;
    use RedirectResponseMockTrait;
    use UserFactoryTrait;

    private AuthHelper&MockObject $authHelper;
    private ModuleConfig $config;
    private CurrentUser&MockObject $currentUser;
    private FlashInterface&MockObject $flash;
    private ControllerHarness $harness;
    private HydratorInterface&MockObject $hydrator;
    private PasswordHasher $passwordHasher;
    private ResponseFactoryInterface&MockObject $responseFactory;
    private TranslatorInterface $translator;
    private Validator $validator;
    private WebViewRenderer&MockObject $viewRenderer;

    protected function setUp(): void
    {
        $this->setUpDatabase();
        $this->config = new ModuleConfig();
        $this->harness = new ControllerHarness($this->config);
        $this->translator = $this->createTranslator();
        $this->viewRenderer = $this->createMock(WebViewRenderer::class);
        $this->currentUser = $this->createMock(CurrentUser::class);
        $this->authHelper = $this->createMock(AuthHelper::class);
        $this->responseFactory = $this->createMock(ResponseFactoryInterface::class);
        $this->hydrator = $this->createMock(HydratorInterface::class);
        $this->flash = $this->createMock(FlashInterface::class);
        $this->passwordHasher = TestPasswordHasherFactory::create();
        $this->validator = new Validator();
    }

    protected function tearDown(): void
    {
        $this->tearDownDatabase();
    }

    /**
     * @return iterable<string, array{ProfileVisibility, string|null, bool|null}>
     */
    public static function showProfileAllowedProvider(): iterable
    {
        yield 'admin different user admin allowed' => [ProfileVisibility::ADMIN, '2', true];
        yield 'owner same user allowed' => [ProfileVisibility::OWNER, '1', null];
        yield 'public no auth' => [ProfileVisibility::PUBLIC, null, null];
        yield 'users authenticated allowed' => [ProfileVisibility::USERS, '2', null];
    }

    /**
     * @return iterable<string, array{ProfileVisibility, string|null, bool|null}>
     */
    public static function showProfileForbiddenOrNotFoundProvider(): iterable
    {
        yield 'profile not found' => [ProfileVisibility::PUBLIC, null, null];
        yield 'admin different user not admin forbidden' => [ProfileVisibility::ADMIN, '2', false];
        yield 'owner different user forbidden' => [ProfileVisibility::OWNER, '2', null];
        yield 'users no auth forbidden' => [ProfileVisibility::USERS, null, null];
    }

    public function testIsAdminReturnsFalseForGuestIdentity(): void
    {
        $config = new ModuleConfig(profileVisibility: ProfileVisibility::ADMIN);
        $this->harness = new ControllerHarness($config);

        $this->currentUser->method('getIdentity')->willReturn($this->createMock(GuestIdentityInterface::class));

        $controller = $this->createController();
        $request = new ServerRequest('GET', '/');

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->expects($this->once())
            ->method('withViewPath')
            ->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->willReturn($response);

        $result = $controller->show($request, 1);

        $this->assertSame($response, $result);
    }

    public function testIsAdminReturnsFalseForIdentityWithNullId(): void
    {
        $config = new ModuleConfig(profileVisibility: ProfileVisibility::ADMIN);
        $this->harness = new ControllerHarness($config);

        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn(null);
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $controller = $this->createController();
        $request = new ServerRequest('GET', '/');

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->expects($this->once())
            ->method('withViewPath')
            ->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->willReturn($response);

        $result = $controller->show($request, 1);

        $this->assertSame($response, $result);
    }

    #[DataProvider('showProfileAllowedProvider')]
    public function testShowProfileAllowed(ProfileVisibility $visibility, ?string $identityId, ?bool $isAdminReturn): void
    {
        $config = new ModuleConfig(profileVisibility: $visibility);
        $this->harness = new ControllerHarness($config);
        $this->setUpIdentity($identityId);

        if ($isAdminReturn !== null) {
            $this->authHelper->method('isAdmin')->willReturn($isAdminReturn);
        }

        $this->createUserWithProfile();

        $controller = $this->createController();
        $request = new ServerRequest('GET', '/');

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->expects($this->once())
            ->method('withViewPath')
            ->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('profile/show', $this->anything())
            ->willReturn($response);

        $result = $controller->show($request, 1);

        $this->assertSame($response, $result);
    }

    #[DataProvider('showProfileForbiddenOrNotFoundProvider')]
    public function testShowProfileForbiddenOrNotFound(ProfileVisibility $visibility, ?string $identityId, ?bool $isAdminReturn): void
    {
        $config = new ModuleConfig(profileVisibility: $visibility);
        $this->harness = new ControllerHarness($config);
        $this->setUpIdentity($identityId);

        if ($isAdminReturn !== null) {
            $this->authHelper->method('isAdmin')->willReturn($isAdminReturn);
        }

        $controller = $this->createController();
        $request = new ServerRequest('GET', '/');

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->expects($this->once())
            ->method('withViewPath')
            ->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->willReturn($response);

        $result = $controller->show($request, 1);

        $this->assertSame($response, $result);
    }

    public function testUpdateGetDoesNotShowSwitchedBannerWhenNotSwitched(): void
    {
        $controller = $this->createController();
        $request = new ServerRequest('GET', '/');

        $user = $this->createUser(passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $this->createUserProfile((int) $user->getId());
        $this->currentUser->method('getIdentity')->willReturn($user);

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

        $controller->update($request);

        $this->assertNull($captured['data']->switchedBannerMessage);
    }

    public function testUpdateGetShowsFormWithExistingProfile(): void
    {
        $controller = $this->createController();
        $request = new ServerRequest('GET', '/');

        $user = $this->createUser(passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $this->createUserProfile((int) $user->getId());
        $this->currentUser->method('getIdentity')->willReturn($user);

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->expects($this->once())
            ->method('withViewPath')
            ->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('profile/update', $this->anything())
            ->willReturn($response);

        $result = $controller->update($request);

        $this->assertSame($response, $result);
    }

    public function testUpdateGetShowsSwitchedBanner(): void
    {
        $originalUser = $this->createUser(username: 'original', email: 'original@example.com', passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());

        $controller = $this->createController();
        $request = new ServerRequest('GET', '/');

        $user = $this->createUser(username: 'switcheduser', email: 'switched@example.com', passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $this->createUserProfile((int) $user->getId());
        $this->currentUser->method('getIdentity')->willReturn($user);

        $this->harness->getSession()->set('voyti_original_admin_user', (string) $originalUser->getId());

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

        $controller->update($request);

        $this->assertStringContainsString('original', (string) $captured['data']->switchedBannerMessage);
    }

    public function testUpdatePostClearingFieldsSetsThemToNullNotEmptyString(): void
    {
        $controller = $this->createController();
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['userProfile' => ['name' => '', 'publicEmail' => '', 'gravatarEmail' => '', 'location' => '', 'website' => '', 'timezone' => '', 'bio' => '', 'birthday' => '']]);

        $this->hydrator->method('hydrate')->willReturnCallback(
            function (object $object, array $data = []): void {
                $this->hydrateObject($object, $data);
            },
        );

        $user = $this->createUser(passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $profile = $this->createUserProfile((int) $user->getId());
        $profile->setPublicEmail('public@example.com');
        $profile->setGravatarEmail('gravatar@example.com');
        $profile->setLocation('Somewhere');
        $profile->setWebsite('https://example.com');
        $profile->setTimezone('UTC');
        $profile->setBio('Some bio');
        $profile->setBirthday(new DateTimeImmutable('1990-05-15'));
        $profile->save();

        $this->currentUser->method('getIdentity')->willReturn($user);

        $response = $this->mockRedirectResponse($this->responseFactory);

        $result = $controller->update($request);

        $this->assertSame($response, $result);
        $updatedProfile = UserProfile::findByUserId((int) $user->getId());
        $this->assertNotNull($updatedProfile);
        $this->assertNull($updatedProfile->getName());
        $this->assertNull($updatedProfile->getPublicEmail());
        $this->assertNull($updatedProfile->getGravatarEmail());
        $this->assertNull($updatedProfile->getLocation());
        $this->assertNull($updatedProfile->getWebsite());
        $this->assertNull($updatedProfile->getTimezone());
        $this->assertNull($updatedProfile->getBio());
        $this->assertNull($updatedProfile->getBirthday());
    }

    public function testUpdatePostCreatesAndSavesNewProfileWhenNoneExists(): void
    {
        $controller = $this->createController();
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['userProfile' => ['name' => 'Jane']]);

        $this->hydrator->method('hydrate')->willReturnCallback(
            function (object $object, array $data = []): void {
                $this->hydrateObject($object, $data);
            },
        );

        $user = $this->createUser(passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $this->currentUser->method('getIdentity')->willReturn($user);

        $response = $this->mockRedirectResponse($this->responseFactory);

        $result = $controller->update($request);

        $this->assertSame($response, $result);
        $savedProfile = UserProfile::findByUserId((int) $user->getId());
        $this->assertNotNull($savedProfile);
        $this->assertSame((int) $user->getId(), $savedProfile->getUserId());
        $this->assertSame('Jane', $savedProfile->getName());
    }

    public function testUpdatePostRejectsHtmlInBioAndDoesNotSave(): void
    {
        $controller = $this->createController();
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['userProfile' => ['name' => 'John', 'publicEmail' => '', 'gravatarEmail' => '', 'location' => '', 'website' => '', 'timezone' => '', 'bio' => '<script>alert(1)</script>', 'birthday' => '']]);

        $this->hydrator->method('hydrate')->willReturnCallback(
            function (object $object, array $data = []): void {
                $this->hydrateObject($object, $data);
            },
        );

        $user = $this->createUser(passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $this->createUserProfile((int) $user->getId(), name: 'OldName');
        $this->currentUser->method('getIdentity')->willReturn($user);

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->method('withViewPath')->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('profile/update', $this->anything())
            ->willReturn($response);
        $this->responseFactory->expects($this->never())->method('createResponse');

        $result = $controller->update($request);

        $this->assertSame($response, $result);
        $updatedProfile = UserProfile::findByUserId((int) $user->getId());
        $this->assertNotNull($updatedProfile);
        $this->assertSame('OldName', $updatedProfile->getName());
        $this->assertNull($updatedProfile->getBio());
    }

    public function testUpdatePostRejectsMalformedBirthdayAndDoesNotSave(): void
    {
        $controller = $this->createController();
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['userProfile' => ['name' => 'John', 'publicEmail' => '', 'gravatarEmail' => '', 'location' => '', 'website' => '', 'timezone' => '', 'bio' => '', 'birthday' => 'not-a-date']]);

        $this->hydrator->method('hydrate')->willReturnCallback(
            function (object $object, array $data = []): void {
                $this->hydrateObject($object, $data);
            },
        );

        $user = $this->createUser(passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $this->createUserProfile((int) $user->getId(), name: 'OldName');
        $this->currentUser->method('getIdentity')->willReturn($user);

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->method('withViewPath')->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('profile/update', $this->anything())
            ->willReturn($response);
        $this->responseFactory->expects($this->never())->method('createResponse');

        $result = $controller->update($request);

        $this->assertSame($response, $result);
        $updatedProfile = UserProfile::findByUserId((int) $user->getId());
        $this->assertNotNull($updatedProfile);
        $this->assertNull($updatedProfile->getBirthday());
    }

    public function testUpdatePostUpdatesAndRedirects(): void
    {
        $controller = $this->createController();
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['userProfile' => ['name' => 'John', 'publicEmail' => '', 'gravatarEmail' => '', 'location' => '', 'website' => '', 'timezone' => '', 'bio' => '', 'birthday' => '1990-05-15']]);

        $this->hydrator->method('hydrate')->willReturnCallback(
            function (object $object, array $data = []): void {
                $this->hydrateObject($object, $data);
            },
        );

        $user = $this->createUser(passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $this->createUserProfile((int) $user->getId(), name: 'OldName');
        $this->currentUser->method('getIdentity')->willReturn($user);

        $response = $this->mockRedirectResponse($this->responseFactory);

        $result = $controller->update($request);

        $this->assertSame($response, $result);
        $updatedProfile = UserProfile::findByUserId((int) $user->getId());
        $this->assertNotNull($updatedProfile);
        $this->assertSame('John', $updatedProfile->getName());
        $this->assertSame('1990-05-15', $updatedProfile->getBirthday()?->format('Y-m-d'));
    }

    private function createController(): ProfileController
    {
        return $this->harness->createProfileController(
            translator: $this->translator,
            viewRenderer: $this->viewRenderer,
            validator: $this->validator,
            currentUser: $this->currentUser,
            responseFactory: $this->responseFactory,
            hydrator: $this->hydrator,
            flash: $this->flash,
            authHelper: $this->authHelper,
        );
    }

    private function createUserProfile(int $userId, string $name = 'John'): UserProfile
    {
        $profile = new UserProfile();
        $profile->setUserId($userId);
        $profile->setName($name);
        $profile->save();

        return $profile;
    }

    private function createUserWithProfile(): User
    {
        $user = new User();
        $user->setUsername('profileuser');
        $user->setEmail('profileuser@example.com');
        $user->setPasswordHash('hash');
        $user->setAuthKey('key');
        $user->setCreatedAt(time());
        $user->setUpdatedAt(time());
        $user->save();

        $profile = new UserProfile();
        $profile->setUserId((int) $user->getId());
        $profile->save();

        return $user;
    }

    private function hydrateObject(object $object, array $data): void
    {
        foreach ($data as $key => $value) {
            if (property_exists($object, $key)) {
                $object->$key = $value;
            }
        }
    }

    private function setUpIdentity(?string $identityId): void
    {
        if ($identityId === null) {
            $this->currentUser->method('getIdentity')->willReturn(new GuestIdentity());
            return;
        }

        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn($identityId);
        $this->currentUser->method('getIdentity')->willReturn($identity);
    }
}
