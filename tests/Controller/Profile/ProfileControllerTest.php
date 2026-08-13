<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Controller\Profile;

use DateTimeImmutable;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\EventDispatcher\EventDispatcherInterface;
use YiiRocks\Voyti\Controller\Profile\ProfileController;
use YiiRocks\Voyti\Enum\ProfileVisibility;
use YiiRocks\Voyti\Event\User\UserProfileEvent;
use YiiRocks\Voyti\Helper\AuthHelper;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Model\UserProfile;
use YiiRocks\Voyti\tests\Support\CurrentUserTrait;
use YiiRocks\Voyti\tests\Support\DatabaseTestCase;
use YiiRocks\Voyti\tests\Support\EventCaptureDispatcher;
use YiiRocks\Voyti\tests\Support\SimpleAssignmentsStorage;
use YiiRocks\Voyti\tests\Support\SimpleItemsStorage;
use YiiRocks\Voyti\tests\Support\TestContainerTrait;
use YiiRocks\Voyti\tests\Support\TestPasswordHasherFactory;
use YiiRocks\Voyti\tests\Support\UserFactoryTrait;
use YiiRocks\Voyti\tests\Support\ValidatorMockTrait;
use YiiRocks\Voyti\tests\Support\VoytiConfigFactory;
use YiiRocks\Voyti\VoytiConfig;
use Yiisoft\Rbac\Manager;
use Yiisoft\Rbac\Permission;
use Yiisoft\Rbac\Role;
use Yiisoft\Session\Flash\FlashInterface;
use Yiisoft\User\CurrentUser;
use Yiisoft\Validator\ValidatorInterface;

#[AllowMockObjectsWithoutExpectations]
final class ProfileControllerTest extends DatabaseTestCase
{
    use CurrentUserTrait;
    use TestContainerTrait;
    use UserFactoryTrait;
    use ValidatorMockTrait;

    private CurrentUser $currentUser;
    private FlashInterface&MockObject $flash;
    private bool $isViewerAdmin = false;
    private ValidatorInterface&MockObject $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->currentUser = $this->createCurrentUser();
        $this->flash = $this->createMock(FlashInterface::class);
        $this->validator = $this->mockValidValidator();
    }

    public static function showProfileAllowedProvider(): iterable
    {
        yield 'admin different user admin allowed' => [ProfileVisibility::ADMIN, '2', true];
        // Own profile under ADMIN visibility is allowed even for a non-admin (the id-equality short-circuit).
        yield 'admin same user non-admin allowed' => [ProfileVisibility::ADMIN, '1', false];
        yield 'owner same user allowed' => [ProfileVisibility::OWNER, '1', null];
        yield 'public no auth' => [ProfileVisibility::PUBLIC, null, null];
        yield 'users authenticated allowed' => [ProfileVisibility::USERS, '2', null];
    }

    public static function showProfileForbiddenOrNotFoundProvider(): iterable
    {
        yield 'profile not found' => [ProfileVisibility::PUBLIC, null, null, 'Profile not found'];
        yield 'admin different user not admin forbidden' => [ProfileVisibility::ADMIN, '2', false, 'Forbidden'];
        yield 'owner different user forbidden' => [ProfileVisibility::OWNER, '2', null, 'Forbidden'];
        yield 'users no auth forbidden' => [ProfileVisibility::USERS, null, null, 'Forbidden'];
    }

    public function testIsAdminReturnsFalseForNonAdminUsers(): void
    {
        // Guest identity: never admin, so admin-only profile is forbidden
        $controller = $this->createController(
            VoytiConfigFactory::create(profileVisibility: ProfileVisibility::ADMIN),
        );
        $html = (string) $controller->show(1)->getBody();
        self::assertStringContainsString('Forbidden', $html);

        // Logged-in identity with null ID: also not admin, profile forbidden
        $this->currentUser->login(new User());
        $html = (string) $controller->show(1)->getBody();
        self::assertStringContainsString('Forbidden', $html);
    }

    #[DataProvider('showProfileAllowedProvider')]
    public function testShowProfileAllowed(ProfileVisibility $visibility, ?string $identityId, ?bool $isAdminReturn): void
    {
        $owner = $this->createUserWithProfile();
        $this->loginViewerFor($identityId, (int) $owner->getId());

        $this->isViewerAdmin = $isAdminReturn === true;

        $controller = $this->createController(
            VoytiConfigFactory::create(profileVisibility: $visibility),
        );

        $html = (string) $controller->show((int) $owner->getId())->getBody();

        // The owner's profile card renders (its display name is the owner's username).
        self::assertStringContainsString('profileuser', $html);
        // The owner-view card never shows admin-only fields (email/registered/status block).
        self::assertStringNotContainsString('<b>Email</b>', $html);
        self::assertStringNotContainsString('<b>Status</b>', $html);
    }

    #[DataProvider('showProfileForbiddenOrNotFoundProvider')]
    public function testShowProfileForbiddenOrNotFound(
        ProfileVisibility $visibility,
        ?string $identityId,
        ?bool $isAdminReturn,
        string $expectedMessage,
    ): void {
        $this->loginViewerFor($identityId, 1);

        $this->isViewerAdmin = $isAdminReturn === true;

        $controller = $this->createController(
            VoytiConfigFactory::create(profileVisibility: $visibility),
        );

        $html = (string) $controller->show(1)->getBody();

        self::assertStringContainsString($expectedMessage, $html);
    }

    public function testUpdateGetShowsFormWithExistingProfile(): void
    {
        $controller = $this->createController();

        $user = $this->createUser(passwordHash: TestPasswordHasherFactory::create()->hash('secret'), confirmedAt: time());
        $this->createUserProfile((int) $user->getId());
        $this->currentUser->login($user);

        $html = (string) $controller->update(new ServerRequest('GET', '/'))->getBody();

        self::assertStringContainsString('Edit Profile', $html);
    }

    public function testUpdatePostClearingFieldsSetsThemToNullNotEmptyString(): void
    {
        $controller = $this->createController();
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['userProfile' => ['name' => '', 'publicEmail' => '', 'gravatarEmail' => '', 'location' => '', 'website' => '', 'timezone' => '', 'bio' => '', 'birthday' => '']]);

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

        $this->currentUser->login($user);

        $result = $controller->update($request);

        $this->assertSame(302, $result->getStatusCode());
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

        $user = $this->createUser(passwordHash: TestPasswordHasherFactory::create()->hash('secret'), confirmedAt: time());
        $this->currentUser->login($user);

        $result = $controller->update($request);

        $this->assertSame(302, $result->getStatusCode());
        $savedProfile = UserProfile::findByUserId((int) $user->getId());
        $this->assertNotNull($savedProfile);
        $this->assertSame((int) $user->getId(), $savedProfile->getUserId());
        $this->assertSame('Jane', $savedProfile->getName());
    }

    public function testUpdatePostDispatchesProfileEvent(): void
    {
        $eventDispatcher = new EventCaptureDispatcher();
        $container = $this->getTestContainer([
            ...$this->baseOverrides(),
            ValidatorInterface::class => $this->validator,
            EventDispatcherInterface::class => $eventDispatcher,
        ]);

        $user = $this->createUser(passwordHash: TestPasswordHasherFactory::create()->hash('secret'), confirmedAt: time());
        $this->createUserProfile((int) $user->getId(), name: 'OldName');
        $this->currentUser->login($user);

        $request = (new ServerRequest('POST', '/'))->withParsedBody(['userProfile' => ['name' => 'John', 'publicEmail' => '', 'gravatarEmail' => '', 'location' => '', 'website' => '', 'timezone' => '', 'bio' => '', 'birthday' => '']]);

        $result = $container->get(ProfileController::class)->update($request);

        $this->assertSame(302, $result->getStatusCode());
        // A successful profile update notifies listeners via UserProfileEvent.
        self::assertTrue($eventDispatcher->hasEvent(UserProfileEvent::class));
    }

    public function testUpdatePostRejectsHtmlInBioAndDoesNotSave(): void
    {
        // Needs the real Validator - this test's whole point is that a real rule rejects HTML in bio.
        $controller = $this->createControllerWithRealValidation();
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['userProfile' => ['name' => 'John', 'publicEmail' => '', 'gravatarEmail' => '', 'location' => '', 'website' => '', 'timezone' => '', 'bio' => '<script>alert(1)</script>', 'birthday' => '']]);

        $user = $this->createUser(passwordHash: TestPasswordHasherFactory::create()->hash('secret'), confirmedAt: time());
        $this->createUserProfile((int) $user->getId(), name: 'OldName');
        $this->currentUser->login($user);

        // Invalid input re-renders the edit form instead of redirecting, and nothing is persisted.
        $html = (string) $controller->update($request)->getBody();

        self::assertStringContainsString('Edit Profile', $html);
        $updatedProfile = UserProfile::findByUserId((int) $user->getId());
        $this->assertNotNull($updatedProfile);
        $this->assertSame('OldName', $updatedProfile->getName());
        $this->assertNull($updatedProfile->getBio());
    }

    private function baseOverrides(?VoytiConfig $config = null): array
    {
        $overrides = [
            AuthHelper::class => $this->createAuthHelper($config),
            CurrentUser::class => $this->currentUser,
            FlashInterface::class => $this->flash,
        ];

        if ($config !== null) {
            $overrides[VoytiConfig::class] = $config;
        }

        return $overrides;
    }

    private function createAuthHelper(?VoytiConfig $config): AuthHelper
    {
        $config ??= VoytiConfigFactory::create();
        $itemsStorage = new SimpleItemsStorage();
        $assignmentsStorage = new SimpleAssignmentsStorage();
        $manager = new Manager($itemsStorage, $assignmentsStorage);

        if ($this->isViewerAdmin) {
            $itemsStorage->add(new Permission($config->administratorPermissionName));
            $itemsStorage->add(new Role('admin'));
            $manager->addChild('admin', $config->administratorPermissionName);
            $manager->assign('admin', (string) $this->currentUser->getId());
        }

        return new AuthHelper($manager, $itemsStorage, $assignmentsStorage, $config, $this->currentUser);
    }

    private function createController(?VoytiConfig $config = null): ProfileController
    {
        return $this->getTestContainer([
            ...$this->baseOverrides($config),
            ValidatorInterface::class => $this->validator,
        ])->get(ProfileController::class);
    }

    /**
     * Uses the real ValidatorInterface instead of the fast valid-by-default mock, for tests whose point is that a
     * real validation rule rejects the input.
     */
    private function createControllerWithRealValidation(?VoytiConfig $config = null): ProfileController
    {
        return $this->getTestContainer($this->baseOverrides($config))->get(ProfileController::class);
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

    /**
     * Logs in a viewer for a profile owned by $profileOwnerId: null leaves a guest, an id equal to
     * the owner's logs the owner in (self), any other id logs in a distinct real user (non-owner).
     */
    private function loginViewerFor(?string $identityId, int $profileOwnerId): void
    {
        if ($identityId === null) {
            return;
        }

        if ($identityId === (string) $profileOwnerId) {
            $owner = User::findById($profileOwnerId);
            self::assertNotNull($owner);
            $this->currentUser->login($owner);
            return;
        }

        do {
            $other = $this->createUser(
                username: 'viewer' . random_int(1, PHP_INT_MAX),
                email: 'viewer' . random_int(1, PHP_INT_MAX) . '@example.com',
            );
        } while ((int) $other->getId() === $profileOwnerId);
        $this->currentUser->login($other);
    }
}
