<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Controller;

use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Http\Message\ResponseInterface;
use YiiRocks\Voyti\Controller\ProfileController;
use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\Entity\UserProfile;
use YiiRocks\Voyti\Helper\AuthHelper;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Repository\UserProfileRepository;
use YiiRocks\Voyti\Repository\UserRepository;
use YiiRocks\Voyti\tests\Support\ControllerHarness;
use YiiRocks\Voyti\tests\TestCase;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\User\CurrentUser;
use Yiisoft\User\Guest\GuestIdentity;
use Yiisoft\User\Guest\GuestIdentityInterface;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
final class ProfileControllerTest extends TestCase
{
    private AuthHelper&MockObject $authHelper;
    private ModuleConfig $config;
    private CurrentUser&MockObject $currentUser;
    private ControllerHarness $harness;
    private TranslatorInterface $translator;
    private UserProfileRepository&MockObject $userProfileRepository;
    private UserRepository&MockObject $userRepository;
    private WebViewRenderer&MockObject $viewRenderer;

    protected function setUp(): void
    {
        $this->config = new ModuleConfig();
        $this->harness = new ControllerHarness($this->config);
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->userProfileRepository = $this->createMock(UserProfileRepository::class);
        $this->translator = $this->createTranslator();
        $this->viewRenderer = $this->createMock(WebViewRenderer::class);
        $this->currentUser = $this->createMock(CurrentUser::class);
        $this->authHelper = $this->createMock(AuthHelper::class);
    }

    public function testIsAdminReturnsFalseForGuestIdentity(): void
    {
        $config = new ModuleConfig(profileVisibility: ProfileController::PROFILE_VISIBILITY_ADMIN);
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
        $config = new ModuleConfig(profileVisibility: ProfileController::PROFILE_VISIBILITY_ADMIN);
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
        $config = new ModuleConfig(profileVisibility: ProfileController::PROFILE_VISIBILITY_PUBLIC);
        $this->harness = new ControllerHarness($config);
        $this->currentUser->method('getIdentity')->willReturn(new GuestIdentity());

        $controller = $this->createController();
        $request = new ServerRequest('GET', '/');

        $this->userProfileRepository->method('findByUserId')->willReturn(null);

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
        $config = new ModuleConfig(profileVisibility: ProfileController::PROFILE_VISIBILITY_ADMIN);
        $this->harness = new ControllerHarness($config);
        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn('2');
        $this->currentUser->method('getIdentity')->willReturn($identity);
        $this->authHelper->method('isAdmin')->willReturn(true);

        $controller = $this->createController();
        $request = new ServerRequest('GET', '/');

        $userProfile = $this->createMock(UserProfile::class);
        $this->userProfileRepository->method('findByUserId')->willReturn($userProfile);

        $user = $this->createMock(User::class);
        $this->userRepository->method('findById')->willReturn($user);

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
        $config = new ModuleConfig(profileVisibility: ProfileController::PROFILE_VISIBILITY_ADMIN);
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
        $config = new ModuleConfig(profileVisibility: ProfileController::PROFILE_VISIBILITY_OWNER);
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
        $config = new ModuleConfig(profileVisibility: ProfileController::PROFILE_VISIBILITY_OWNER);
        $this->harness = new ControllerHarness($config);
        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn('1');
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $controller = $this->createController();
        $request = new ServerRequest('GET', '/');

        $userProfile = $this->createMock(UserProfile::class);
        $this->userProfileRepository->method('findByUserId')->willReturn($userProfile);

        $user = $this->createMock(User::class);
        $this->userRepository->method('findById')->willReturn($user);

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
        $config = new ModuleConfig(profileVisibility: ProfileController::PROFILE_VISIBILITY_PUBLIC);
        $this->harness = new ControllerHarness($config);
        $this->currentUser->method('getIdentity')->willReturn(new GuestIdentity());

        $controller = $this->createController();
        $request = new ServerRequest('GET', '/');

        $userProfile = $this->createMock(UserProfile::class);
        $this->userProfileRepository->method('findByUserId')->willReturn($userProfile);

        $user = $this->createMock(User::class);
        $this->userRepository->method('findById')->willReturn($user);

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
        $config = new ModuleConfig(profileVisibility: ProfileController::PROFILE_VISIBILITY_USERS);
        $this->harness = new ControllerHarness($config);
        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn('2');
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $controller = $this->createController();
        $request = new ServerRequest('GET', '/');

        $userProfile = $this->createMock(UserProfile::class);
        $this->userProfileRepository->method('findByUserId')->willReturn($userProfile);

        $user = $this->createMock(User::class);
        $this->userRepository->method('findById')->willReturn($user);

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
        $config = new ModuleConfig(profileVisibility: ProfileController::PROFILE_VISIBILITY_USERS);
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

    private function createController(): ProfileController
    {
        return $this->harness->createProfileController(
            userRepository: $this->userRepository,
            userProfileRepository: $this->userProfileRepository,
            translator: $this->translator,
            viewRenderer: $this->viewRenderer,
            currentUser: $this->currentUser,
            authHelper: $this->authHelper,
        );
    }
}
