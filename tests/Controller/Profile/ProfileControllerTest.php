<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Controller\Profile;

use Nyholm\Psr7\ServerRequest;
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
use YiiRocks\Voyti\tests\TestCase;
use Yiisoft\Hydrator\HydratorInterface;
use Yiisoft\Security\PasswordHasher;
use Yiisoft\Session\Flash\FlashInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\User\CurrentUser;
use Yiisoft\User\Guest\GuestIdentity;
use Yiisoft\User\Guest\GuestIdentityInterface;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
final class ProfileControllerTest extends TestCase
{
    use DatabaseSetupTrait;

    private AuthHelper&MockObject $authHelper;
    private ModuleConfig $config;
    private CurrentUser&MockObject $currentUser;
    private FlashInterface&MockObject $flash;
    private ControllerHarness $harness;
    private HydratorInterface&MockObject $hydrator;
    private PasswordHasher $passwordHasher;
    private ResponseFactoryInterface&MockObject $responseFactory;
    private TranslatorInterface $translator;
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
        $this->passwordHasher = new PasswordHasher();
    }

    protected function tearDown(): void
    {
        $this->tearDownDatabase();
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

    public function testShowProfileNotFound(): void
    {
        $config = new ModuleConfig(profileVisibility: ProfileVisibility::PUBLIC);
        $this->harness = new ControllerHarness($config);
        $this->currentUser->method('getIdentity')->willReturn(new GuestIdentity());

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

    public function testShowProfileVisibilityAdminDifferentUserAdminAllowed(): void
    {
        $config = new ModuleConfig(profileVisibility: ProfileVisibility::ADMIN);
        $this->harness = new ControllerHarness($config);
        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn('2');
        $this->currentUser->method('getIdentity')->willReturn($identity);
        $this->authHelper->method('isAdmin')->willReturn(true);

        $this->createUserWithProfile();

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

    public function testShowProfileVisibilityAdminDifferentUserNotAdminForbidden(): void
    {
        $config = new ModuleConfig(profileVisibility: ProfileVisibility::ADMIN);
        $this->harness = new ControllerHarness($config);
        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn('2');
        $this->currentUser->method('getIdentity')->willReturn($identity);
        $this->authHelper->method('isAdmin')->willReturn(false);

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

    public function testShowProfileVisibilityOwnerDifferentUserForbidden(): void
    {
        $config = new ModuleConfig(profileVisibility: ProfileVisibility::OWNER);
        $this->harness = new ControllerHarness($config);
        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn('2');
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

    public function testShowProfileVisibilityOwnerSameUserAllowed(): void
    {
        $config = new ModuleConfig(profileVisibility: ProfileVisibility::OWNER);
        $this->harness = new ControllerHarness($config);
        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn('1');
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $this->createUserWithProfile();

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

    public function testShowProfileVisibilityPublicNoAuth(): void
    {
        $config = new ModuleConfig(profileVisibility: ProfileVisibility::PUBLIC);
        $this->harness = new ControllerHarness($config);
        $this->currentUser->method('getIdentity')->willReturn(new GuestIdentity());

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

    public function testShowProfileVisibilityUsersAuthenticatedAllowed(): void
    {
        $config = new ModuleConfig(profileVisibility: ProfileVisibility::USERS);
        $this->harness = new ControllerHarness($config);
        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn('2');
        $this->currentUser->method('getIdentity')->willReturn($identity);

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

    public function testShowProfileVisibilityUsersNoAuthForbidden(): void
    {
        $config = new ModuleConfig(profileVisibility: ProfileVisibility::USERS);
        $this->harness = new ControllerHarness($config);
        $this->currentUser->method('getIdentity')->willReturn(new GuestIdentity());

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

    public function testUpdateGetCreatesNewProfileWhenNoneExists(): void
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

        $controller->update($request);

        $this->assertInstanceOf(UserProfile::class, $captured['userProfile']);
        $this->assertSame((int) $user->getId(), $captured['userProfile']->getUserId());
    }

    public function testUpdateGetDoesNotShowSwitchedBannerWhenNotSwitched(): void
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

        $controller->update($request);

        $this->assertFalse($captured['isSwitched']);
        $this->assertNull($captured['originalUser']);
    }

    public function testUpdateGetShowsFormWithExistingProfile(): void
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
            ->with('profile/update', $this->anything())
            ->willReturn($response);

        $result = $controller->update($request);

        $this->assertSame($response, $result);
    }

    public function testUpdateGetShowsSwitchedBanner(): void
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

        $controller->update($request);

        $this->assertTrue($captured['isSwitched']);
        $this->assertSame($originalUser->getId(), $captured['originalUser']->getId());
    }

    public function testUpdatePostUpdatesAndRedirects(): void
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

        $result = $controller->update($request);

        $this->assertSame($response, $result);
        $updatedProfile = UserProfile::findByUserId((int) $user->getId());
        $this->assertNotNull($updatedProfile);
        $this->assertSame('John', $updatedProfile->getName());
    }

    public function testUpdateWhenGuestShowsError(): void
    {
        $controller = $this->createController();
        $request = new ServerRequest('GET', '/');

        $this->currentUser->method('getIdentity')->willReturn($this->createMock(GuestIdentityInterface::class));

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->method('withViewPath')->willReturnSelf();
        $this->viewRenderer->method('render')->willReturn($response);

        $result = $controller->update($request);

        $this->assertSame($response, $result);
    }

    public function testUpdateWhenUserNotFoundShowsError(): void
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

    private function createController(): ProfileController
    {
        return $this->harness->createProfileController(
            translator: $this->translator,
            viewRenderer: $this->viewRenderer,
            currentUser: $this->currentUser,
            responseFactory: $this->responseFactory,
            hydrator: $this->hydrator,
            flash: $this->flash,
            authHelper: $this->authHelper,
        );
    }

    private function createUser(
        string $username = 'testuser',
        string $email = 'test@example.com',
        string $password = 'secret',
    ): User {
        $user = new User();
        $user->setUsername($username);
        $user->setEmail($email);
        $user->setPasswordHash($this->passwordHasher->hash($password));
        $user->setAuthKey('key');
        $user->setCreatedAt(time());
        $user->setUpdatedAt(time());
        $user->setConfirmedAt(time());
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
}
