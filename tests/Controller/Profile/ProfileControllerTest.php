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
use YiiRocks\Voyti\tests\Support\DatabaseSetupTrait;
use YiiRocks\Voyti\tests\Support\HydrateObjectTrait;
use YiiRocks\Voyti\tests\Support\RedirectResponseMockTrait;
use YiiRocks\Voyti\tests\Support\TestContainerTrait;
use YiiRocks\Voyti\tests\Support\TestPasswordHasherFactory;
use YiiRocks\Voyti\tests\Support\UserFactoryTrait;
use YiiRocks\Voyti\tests\Support\ViewCaptureTrait;
use YiiRocks\Voyti\tests\Support\VoytiConfigFactory;
use YiiRocks\Voyti\tests\TestCase;
use YiiRocks\Voyti\VoytiConfig;
use Yiisoft\Hydrator\HydratorInterface;
use Yiisoft\Session\Flash\FlashInterface;
use Yiisoft\User\CurrentUser;
use Yiisoft\User\Guest\GuestIdentity;
use Yiisoft\User\Guest\GuestIdentityInterface;
use Yiisoft\Validator\Validator;
use Yiisoft\Validator\ValidatorInterface;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

#[AllowMockObjectsWithoutExpectations]
final class ProfileControllerTest extends TestCase
{
    use DatabaseSetupTrait;
    use HydrateObjectTrait;
    use RedirectResponseMockTrait;
    use TestContainerTrait;
    use UserFactoryTrait;
    use ViewCaptureTrait;

    private AuthHelper&MockObject $authHelper;
    private CurrentUser&MockObject $currentUser;
    private FlashInterface&MockObject $flash;
    private HydratorInterface&MockObject $hydrator;
    private ResponseFactoryInterface&MockObject $responseFactory;
    private WebViewRenderer&MockObject $viewRenderer;

    protected function setUp(): void
    {
        $this->setUpDatabase();
        $this->viewRenderer = $this->createMock(WebViewRenderer::class);
        $this->viewRenderer->method('withAddedInjections')->willReturnSelf();
        $this->currentUser = $this->createMock(CurrentUser::class);
        $this->authHelper = $this->createMock(AuthHelper::class);
        $this->responseFactory = $this->createMock(ResponseFactoryInterface::class);
        $this->hydrator = $this->createMock(HydratorInterface::class);
        $this->flash = $this->createMock(FlashInterface::class);
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
        $this->currentUser->method('getIdentity')->willReturn($this->createMock(GuestIdentityInterface::class));

        $controller = $this->createController(
            VoytiConfigFactory::create(profileVisibility: ProfileVisibility::ADMIN),
        );

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->expects($this->once())
            ->method('withViewPath')
            ->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->willReturn($response);

        $result = $controller->show(1);

        $this->assertSame($response, $result);
    }

    public function testIsAdminReturnsFalseForIdentityWithNullId(): void
    {
        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn(null);
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $controller = $this->createController(
            VoytiConfigFactory::create(profileVisibility: ProfileVisibility::ADMIN),
        );

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->expects($this->once())
            ->method('withViewPath')
            ->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->willReturn($response);

        $result = $controller->show(1);

        $this->assertSame($response, $result);
    }

    #[DataProvider('showProfileAllowedProvider')]
    public function testShowProfileAllowed(ProfileVisibility $visibility, ?string $identityId, ?bool $isAdminReturn): void
    {
        $this->setUpIdentity($identityId);

        if ($isAdminReturn !== null) {
            $this->authHelper->method('isAdmin')->willReturn($isAdminReturn);
        }

        $this->createUserWithProfile();

        $controller = $this->createController(
            VoytiConfigFactory::create(profileVisibility: $visibility),
        );

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->expects($this->once())
            ->method('withViewPath')
            ->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('profile/show', $this->anything())
            ->willReturn($response);

        $result = $controller->show(1);

        $this->assertSame($response, $result);
    }

    #[DataProvider('showProfileForbiddenOrNotFoundProvider')]
    public function testShowProfileForbiddenOrNotFound(ProfileVisibility $visibility, ?string $identityId, ?bool $isAdminReturn): void
    {
        $this->setUpIdentity($identityId);

        if ($isAdminReturn !== null) {
            $this->authHelper->method('isAdmin')->willReturn($isAdminReturn);
        }

        $controller = $this->createController(
            VoytiConfigFactory::create(profileVisibility: $visibility),
        );

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->expects($this->once())
            ->method('withViewPath')
            ->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->willReturn($response);

        $result = $controller->show(1);

        $this->assertSame($response, $result);
    }

    public function testUpdateGetShowsFormWithExistingProfile(): void
    {
        $controller = $this->createController();
        $request = new ServerRequest('GET', '/');

        $user = $this->createUser(passwordHash: TestPasswordHasherFactory::create()->hash('secret'), confirmedAt: time());
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

    public function testUpdatePostClearingFieldsSetsThemToNullNotEmptyString(): void
    {
        $controller = $this->createController();
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['userProfile' => ['name' => '', 'publicEmail' => '', 'gravatarEmail' => '', 'location' => '', 'website' => '', 'timezone' => '', 'bio' => '', 'birthday' => '']]);

        $this->hydrator->method('hydrate')->willReturnCallback(
            function (object $object, array $data = []): void {
                $this->hydrateObject($object, $data);
            },
        );

        $user = $this->createUser(passwordHash: TestPasswordHasherFactory::create()->hash('secret'), confirmedAt: time());
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

        $result = $controller->update($request, ['name' => '', 'publicEmail' => '', 'gravatarEmail' => '', 'location' => '', 'website' => '', 'timezone' => '', 'bio' => '', 'birthday' => '']);

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

        $user = $this->createUser(passwordHash: TestPasswordHasherFactory::create()->hash('secret'), confirmedAt: time());
        $this->currentUser->method('getIdentity')->willReturn($user);

        $response = $this->mockRedirectResponse($this->responseFactory);

        $result = $controller->update($request, ['name' => 'Jane']);

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

        $user = $this->createUser(passwordHash: TestPasswordHasherFactory::create()->hash('secret'), confirmedAt: time());
        $this->createUserProfile((int) $user->getId(), name: 'OldName');
        $this->currentUser->method('getIdentity')->willReturn($user);

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->method('withViewPath')->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('profile/update', $this->anything())
            ->willReturn($response);
        $this->responseFactory->expects($this->never())->method('createResponse');

        $result = $controller->update($request, ['name' => 'John', 'publicEmail' => '', 'gravatarEmail' => '', 'location' => '', 'website' => '', 'timezone' => '', 'bio' => '<script>alert(1)</script>', 'birthday' => '']);

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

        $user = $this->createUser(passwordHash: TestPasswordHasherFactory::create()->hash('secret'), confirmedAt: time());
        $this->createUserProfile((int) $user->getId(), name: 'OldName');
        $this->currentUser->method('getIdentity')->willReturn($user);

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->method('withViewPath')->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('profile/update', $this->anything())
            ->willReturn($response);
        $this->responseFactory->expects($this->never())->method('createResponse');

        $result = $controller->update($request, ['name' => 'John', 'publicEmail' => '', 'gravatarEmail' => '', 'location' => '', 'website' => '', 'timezone' => '', 'bio' => '', 'birthday' => 'not-a-date']);

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

        $user = $this->createUser(passwordHash: TestPasswordHasherFactory::create()->hash('secret'), confirmedAt: time());
        $this->createUserProfile((int) $user->getId(), name: 'OldName');
        $this->currentUser->method('getIdentity')->willReturn($user);

        $response = $this->mockRedirectResponse($this->responseFactory);

        $result = $controller->update($request, ['name' => 'John', 'publicEmail' => '', 'gravatarEmail' => '', 'location' => '', 'website' => '', 'timezone' => '', 'bio' => '', 'birthday' => '1990-05-15']);

        $this->assertSame($response, $result);
        $updatedProfile = UserProfile::findByUserId((int) $user->getId());
        $this->assertNotNull($updatedProfile);
        $this->assertSame('John', $updatedProfile->getName());
        $this->assertSame('1990-05-15', $updatedProfile->getBirthday()?->format('Y-m-d'));
    }

    private function createController(?VoytiConfig $config = null): ProfileController
    {
        $overrides = [
            AuthHelper::class => $this->authHelper,
            CurrentUser::class => $this->currentUser,
            FlashInterface::class => $this->flash,
            HydratorInterface::class => $this->hydrator,
            ResponseFactoryInterface::class => $this->responseFactory,
            ValidatorInterface::class => new Validator(),
            WebViewRenderer::class => $this->viewRenderer,
        ];

        if ($config !== null) {
            $overrides[VoytiConfig::class] = $config;
        }

        return $this->getTestContainer($overrides)->get(ProfileController::class);
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
