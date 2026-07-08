<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Controller;

use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use YiiRocks\Voyti\Controller\AdminController;
use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\Entity\UserProfile;
use YiiRocks\Voyti\Helper\AuthHelper;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Repository\UserProfileRepository;
use YiiRocks\Voyti\Repository\UserRepository;
use YiiRocks\Voyti\Repository\UserSessionHistoryRepository;
use YiiRocks\Voyti\Service\Password\ExpireService;
use YiiRocks\Voyti\Service\Password\PasswordGeneratorInterface;
use YiiRocks\Voyti\Service\Password\RecoveryService;
use YiiRocks\Voyti\Service\Rbac\UpdateAssignmentsService;
use YiiRocks\Voyti\Service\ServiceResult;
use YiiRocks\Voyti\Service\SwitchIdentityService;
use YiiRocks\Voyti\Service\User\BlockService;
use YiiRocks\Voyti\Service\User\ConfirmationService;
use YiiRocks\Voyti\Service\User\CreateService;
use YiiRocks\Voyti\tests\Support\ControllerHarness;
use YiiRocks\Voyti\tests\TestCase;
use Yiisoft\Hydrator\HydratorInterface;
use Yiisoft\Security\PasswordHasher;
use Yiisoft\Session\Flash\FlashInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\User\CurrentUser;
use Yiisoft\Validator\Result;
use Yiisoft\Validator\ValidatorInterface;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
final class AdminControllerTest extends TestCase
{
    private AuthHelper&MockObject $authHelper;
    private BlockService&MockObject $blockService;
    private ModuleConfig $config;
    private ConfirmationService&MockObject $confirmationService;
    private CreateService&MockObject $createService;
    private CurrentUser&MockObject $currentUser;
    private ExpireService&MockObject $expireService;
    private FlashInterface&MockObject $flash;
    private ControllerHarness $harness;
    private HydratorInterface&MockObject $hydrator;
    private PasswordGeneratorInterface&MockObject $passwordGenerator;
    private PasswordHasher $passwordHasher;
    private RecoveryService&MockObject $recoveryService;
    private ResponseFactoryInterface&MockObject $responseFactory;
    private SwitchIdentityService&MockObject $switchIdentityService;
    private TranslatorInterface $translator;
    private UpdateAssignmentsService&MockObject $updateAssignmentsService;
    private UserProfileRepository&MockObject $userProfileRepository;
    private UserRepository&MockObject $userRepository;
    private UserSessionHistoryRepository&MockObject $userSessionHistoryRepository;
    private ValidatorInterface&MockObject $validator;
    private WebViewRenderer&MockObject $viewRenderer;

    protected function setUp(): void
    {
        $this->config = new ModuleConfig();
        $this->harness = new ControllerHarness($this->config);
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->userProfileRepository = $this->createMock(UserProfileRepository::class);
        $this->translator = $this->createTranslator();
        $this->viewRenderer = $this->createMock(WebViewRenderer::class);
        $this->validator = $this->createMock(ValidatorInterface::class);
        $this->currentUser = $this->createMock(CurrentUser::class);
        $this->responseFactory = $this->createMock(ResponseFactoryInterface::class);
        $this->hydrator = $this->createMock(HydratorInterface::class);
        $this->flash = $this->createMock(FlashInterface::class);
        $this->passwordHasher = new PasswordHasher();
        $this->passwordGenerator = $this->createMock(PasswordGeneratorInterface::class);
        $this->createService = $this->createMock(CreateService::class);
        $this->blockService = $this->createMock(BlockService::class);
        $this->confirmationService = $this->createMock(ConfirmationService::class);
        $this->recoveryService = $this->createMock(RecoveryService::class);
        $this->expireService = $this->createMock(ExpireService::class);
        $this->switchIdentityService = $this->createMock(SwitchIdentityService::class);
        $this->updateAssignmentsService = $this->createMock(UpdateAssignmentsService::class);
        $this->userSessionHistoryRepository = $this->createMock(UserSessionHistoryRepository::class);
        $this->authHelper = $this->createMock(AuthHelper::class);
    }

    public function testAssignmentsGetShowsAssignments(): void
    {
        $controller = $this->createController();
        $request = new ServerRequest('GET', '/');

        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn('1');
        $this->userRepository->method('findById')->willReturn($user);
        $this->authHelper->method('getUnassignedItems')->willReturn([]);

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->expects($this->once())
            ->method('withViewPath')
            ->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('admin/_assignments', $this->anything())
            ->willReturn($response);

        $result = $controller->assignments($request, 1);

        $this->assertSame($response, $result);
    }

    public function testAssignmentsPostUpdates(): void
    {
        $controller = $this->createController();
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['items' => ['admin', 'editor']]);

        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn('1');
        $this->userRepository->method('findById')->willReturn($user);
        $this->updateAssignmentsService->expects($this->once())->method('run')->with(1, ['admin', 'editor']);
        $this->authHelper->method('getUnassignedItems')->willReturn([]);

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->expects($this->once())
            ->method('withViewPath')
            ->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->willReturn($response);

        $result = $controller->assignments($request, 1);

        $this->assertSame($response, $result);
    }

    public function testAssignmentsUserNotFoundShowsError(): void
    {
        $controller = $this->createController();
        $request = new ServerRequest('GET', '/');

        $this->userRepository->method('findById')->willReturn(null);

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->expects($this->once())
            ->method('withViewPath')
            ->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->willReturn($response);

        $result = $controller->assignments($request, 999);

        $this->assertSame($response, $result);
    }

    public function testBlockNonExistentUserStillRedirects(): void
    {
        $controller = $this->createController();

        $this->userRepository->method('findById')->willReturn(null);

        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->expects($this->once())
            ->method('createResponse')
            ->with(302)
            ->willReturn($response);
        $response->expects($this->once())
            ->method('withHeader')
            ->willReturnSelf();

        $result = $controller->block(999);

        $this->assertSame($response, $result);
    }

    public function testBlockTogglesUserBlock(): void
    {
        $controller = $this->createController();

        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn('1');
        $this->userRepository->method('findById')->willReturn($user);
        $this->blockService->expects($this->once())->method('run')->with($user);

        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->expects($this->once())
            ->method('createResponse')
            ->with(302)
            ->willReturn($response);
        $response->expects($this->once())
            ->method('withHeader')
            ->willReturnSelf();

        $result = $controller->block(1);

        $this->assertSame($response, $result);
    }

    public function testConfirmFailureShowsError(): void
    {
        $controller = $this->createController();

        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn('1');
        $this->userRepository->method('findById')->willReturn($user);
        $this->confirmationService->expects($this->once())->method('run')->willReturn(false);

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->expects($this->once())
            ->method('withViewPath')
            ->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->willReturn($response);

        $result = $controller->confirm(1);

        $this->assertSame($response, $result);
    }

    public function testConfirmSuccessful(): void
    {
        $controller = $this->createController();

        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn('1');
        $this->userRepository->method('findById')->willReturn($user);
        $this->confirmationService->expects($this->once())->method('run')->willReturn(true);

        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->expects($this->once())
            ->method('createResponse')
            ->with(302)
            ->willReturn($response);
        $response->expects($this->once())
            ->method('withHeader')
            ->willReturnSelf();

        $result = $controller->confirm(1);

        $this->assertSame($response, $result);
    }

    public function testCreateGetShowsForm(): void
    {
        $controller = $this->createController();
        $request = new ServerRequest('GET', '/');

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->expects($this->once())
            ->method('withViewPath')
            ->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('admin/create', $this->anything())
            ->willReturn($response);

        $result = $controller->create($request);

        $this->assertSame($response, $result);
    }

    public function testCreatePostSuccessful(): void
    {
        $controller = $this->createController();
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['register' => ['username' => 'newuser', 'email' => 'new@example.com', 'password' => '', 'passwordRepeat' => '']]);

        $this->passwordGenerator->method('generate')->willReturn('autogenerated123');
        $this->createService->expects($this->once())
            ->method('run')
            ->willReturn(ServiceResult::success('User created'));

        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->expects($this->once())
            ->method('createResponse')
            ->with(302)
            ->willReturn($response);
        $response->expects($this->once())
            ->method('withHeader')
            ->willReturnSelf();

        $result = $controller->create($request);

        $this->assertSame($response, $result);
    }

    public function testCreatePostWithServiceFailure(): void
    {
        $controller = $this->createController();
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['register' => ['username' => 'existing', 'email' => 'existing@example.com', 'password' => 'password123', 'passwordRepeat' => 'password123']]);

        $this->createService->expects($this->once())
            ->method('run')
            ->willReturn(ServiceResult::failure('Email already exists', ['Email already exists']));

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->expects($this->once())
            ->method('withViewPath')
            ->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->willReturn($response);

        $result = $controller->create($request);

        $this->assertSame($response, $result);
    }

    public function testDeleteDifferentUser(): void
    {
        $controller = $this->createController();
        $request = new ServerRequest('GET', '/');

        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn('1');
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn('2');
        $this->userRepository->method('findById')->willReturn($user);
        $this->userRepository->expects($this->once())->method('delete')->with($user);

        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->expects($this->once())
            ->method('createResponse')
            ->with(302)
            ->willReturn($response);
        $response->expects($this->once())
            ->method('withHeader')
            ->willReturnSelf();

        $result = $controller->delete($request, 2);

        $this->assertSame($response, $result);
    }

    public function testDeleteNonExistentUserShowsError(): void
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

        $result = $controller->delete($request, 999);

        $this->assertSame($response, $result);
    }

    public function testDeleteOwnUserShowsError(): void
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
            ->willReturn($response);

        $result = $controller->delete($request, 1);

        $this->assertSame($response, $result);
    }

    public function testForcePasswordChangeFailsShowsError(): void
    {
        $controller = $this->createController();

        $this->userRepository->method('findById')->willReturn(null);

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->expects($this->once())
            ->method('withViewPath')
            ->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->willReturn($response);

        $result = $controller->forcePasswordChange(999);

        $this->assertSame($response, $result);
    }

    public function testForcePasswordChangeUserFound(): void
    {
        $controller = $this->createController();

        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn('1');
        $this->userRepository->method('findById')->willReturn($user);
        $this->expireService->expects($this->once())->method('run')->willReturn(true);

        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->expects($this->once())
            ->method('createResponse')
            ->with(302)
            ->willReturn($response);
        $response->expects($this->once())
            ->method('withHeader')
            ->willReturnSelf();

        $result = $controller->forcePasswordChange(1);

        $this->assertSame($response, $result);
    }

    public function testIndexComputesTotalPagesWithMinimumOfOne(): void
    {
        $controller = $this->createController();
        $request = new ServerRequest('GET', '/');

        $this->userRepository->method('search')->willReturn([]);
        $this->userRepository->method('countByFilters')->willReturn(0);

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

        $controller->index($request);

        $this->assertArrayHasKey('totalPages', $captured);
        $this->assertSame(1, $captured['totalPages']);
        $this->assertSame(1, $captured['currentPage']);
    }

    public function testIndexShowsUserList(): void
    {
        $controller = $this->createController();
        $request = new ServerRequest('GET', '/');

        $this->userRepository->method('search')->willReturn([]);
        $this->userRepository->method('countByFilters')->willReturn(0);

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->expects($this->once())
            ->method('withViewPath')
            ->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('admin/index', $this->anything())
            ->willReturn($response);

        $result = $controller->index($request);

        $this->assertSame($response, $result);
    }

    public function testInfoShowsUserInfo(): void
    {
        $controller = $this->createController();

        $user = $this->createMock(User::class);
        $user->method('getProfile')->willReturn($this->createMock(UserProfile::class));
        $this->userRepository->method('findById')->willReturn($user);

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->expects($this->once())
            ->method('withViewPath')
            ->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('admin/_info', $this->anything())
            ->willReturn($response);

        $result = $controller->info(1);

        $this->assertSame($response, $result);
    }

    public function testInfoUserNotFoundShowsError(): void
    {
        $controller = $this->createController();

        $this->userRepository->method('findById')->willReturn(null);

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->expects($this->once())
            ->method('withViewPath')
            ->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->willReturn($response);

        $result = $controller->info(999);

        $this->assertSame($response, $result);
    }

    public function testPasswordResetUserFound(): void
    {
        $controller = $this->createController();

        $user = $this->createMock(User::class);
        $user->method('getEmail')->willReturn('test@example.com');
        $this->userRepository->method('findById')->willReturn($user);
        $this->recoveryService->expects($this->once())
            ->method('run')
            ->with('test@example.com')
            ->willReturn(ServiceResult::success('Email sent'));

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->expects($this->once())
            ->method('withViewPath')
            ->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->willReturn($response);

        $result = $controller->passwordReset(1);

        $this->assertSame($response, $result);
    }

    public function testPasswordResetUserNotFoundShowsError(): void
    {
        $controller = $this->createController();

        $this->userRepository->method('findById')->willReturn(null);

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->expects($this->once())
            ->method('withViewPath')
            ->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->willReturn($response);

        $result = $controller->passwordReset(999);

        $this->assertSame($response, $result);
    }

    public function testSwitchIdentity(): void
    {
        $controller = $this->createController();

        $this->switchIdentityService->expects($this->once())
            ->method('run')
            ->willReturn(ServiceResult::success());

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->expects($this->once())
            ->method('withViewPath')
            ->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->willReturn($response);

        $result = $controller->switchIdentity(1);

        $this->assertSame($response, $result);
    }

    public function testTerminateSessionsUserFound(): void
    {
        $controller = $this->createController();

        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn('1');
        $this->userRepository->method('findById')->willReturn($user);
        $this->userSessionHistoryRepository->method('findByUserId')->willReturn([]);

        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->expects($this->once())
            ->method('createResponse')
            ->with(302)
            ->willReturn($response);
        $response->expects($this->once())
            ->method('withHeader')
            ->willReturnSelf();

        $result = $controller->terminateSessions(1);

        $this->assertSame($response, $result);
    }

    public function testTerminateSessionsUserNotFoundShowsError(): void
    {
        $controller = $this->createController();

        $this->userRepository->method('findById')->willReturn(null);

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->expects($this->once())
            ->method('withViewPath')
            ->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->willReturn($response);

        $result = $controller->terminateSessions(999);

        $this->assertSame($response, $result);
    }

    public function testUpdateGetShowsForm(): void
    {
        $controller = $this->createController();
        $request = new ServerRequest('GET', '/');

        $user = $this->createMock(User::class);
        $user->method('getUsername')->willReturn('testuser');
        $user->method('getEmail')->willReturn('test@example.com');
        $user->method('getId')->willReturn('1');

        $this->userRepository->method('findById')->willReturn($user);

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->expects($this->once())
            ->method('withViewPath')
            ->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('admin/_account', $this->anything())
            ->willReturn($response);

        $result = $controller->update($request, 1);

        $this->assertSame($response, $result);
    }

    public function testUpdatePostSuccessful(): void
    {
        $controller = $this->createController();
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['user' => ['username' => 'updated', 'email' => 'updated@example.com', 'password' => ''], 'assignedItems' => []]);

        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn('1');
        $user->method('getUsername')->willReturn('testuser');
        $user->method('getEmail')->willReturn('test@example.com');
        $user->expects($this->once())->method('setUsername');
        $user->expects($this->once())->method('setEmail');
        $user->expects($this->once())->method('setUpdatedAt');
        $user->expects($this->once())->method('save');

        $this->userRepository->method('findById')->willReturn($user);
        $this->updateAssignmentsService->expects($this->once())->method('run');

        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->expects($this->once())
            ->method('createResponse')
            ->with(302)
            ->willReturn($response);
        $response->expects($this->once())
            ->method('withHeader')
            ->willReturnSelf();

        $result = $controller->update($request, 1);

        $this->assertSame($response, $result);
    }

    public function testUpdatePostWithPasswordChange(): void
    {
        $controller = $this->createController();
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['user' => ['username' => 'updated', 'email' => 'updated@example.com', 'password' => 'newpass'], 'assignedItems' => []]);

        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn('1');
        $user->method('getUsername')->willReturn('testuser');
        $user->method('getEmail')->willReturn('test@example.com');
        $user->expects($this->once())->method('setPasswordHash');
        $user->expects($this->once())->method('setPasswordChangedAt');
        $user->expects($this->once())->method('save');

        $this->userRepository->method('findById')->willReturn($user);
        $this->updateAssignmentsService->expects($this->once())->method('run');

        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->expects($this->once())
            ->method('createResponse')
            ->with(302)
            ->willReturn($response);
        $response->expects($this->once())
            ->method('withHeader')
            ->willReturnSelf();

        $result = $controller->update($request, 1);

        $this->assertSame($response, $result);
    }

    public function testUpdateProfileGetShowsForm(): void
    {
        $controller = $this->createController();
        $request = new ServerRequest('GET', '/');

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
            ->with('admin/_profile', $this->anything())
            ->willReturn($response);

        $result = $controller->updateProfile($request, 1);

        $this->assertSame($response, $result);
    }

    public function testUpdateProfilePostSuccessful(): void
    {
        $controller = $this->createController();
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['userProfile' => ['name' => 'Updated', 'publicEmail' => '', 'gravatarEmail' => '', 'location' => '', 'website' => '', 'timezone' => '', 'bio' => '']]);

        $this->validator->method('validate')->willReturn(new Result());
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn('1');
        $userProfile = $this->createMock(UserProfile::class);
        $userProfile->method('getName')->willReturn('Updated');
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

        $result = $controller->updateProfile($request, 1);

        $this->assertSame($response, $result);
    }

    public function testUpdateProfileUserNotFoundShowsError(): void
    {
        $controller = $this->createController();
        $request = new ServerRequest('GET', '/');

        $this->userRepository->method('findById')->willReturn(null);

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->expects($this->once())
            ->method('withViewPath')
            ->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->willReturn($response);

        $result = $controller->updateProfile($request, 999);

        $this->assertSame($response, $result);
    }

    public function testUpdateUserNotFoundShowsError(): void
    {
        $controller = $this->createController();
        $request = new ServerRequest('GET', '/');

        $this->userRepository->method('findById')->willReturn(null);

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->expects($this->once())
            ->method('withViewPath')
            ->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->willReturn($response);

        $result = $controller->update($request, 999);

        $this->assertSame($response, $result);
    }

    public function testUserSessionHistoryUserFound(): void
    {
        $controller = $this->createController();

        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn('1');
        $this->userRepository->method('findById')->willReturn($user);
        $this->userSessionHistoryRepository->method('findByUserId')->willReturn([]);

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->expects($this->once())
            ->method('withViewPath')
            ->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('admin/_session-history', $this->anything())
            ->willReturn($response);

        $result = $controller->userSessionHistory(1);

        $this->assertSame($response, $result);
    }

    public function testUserSessionHistoryUserNotFoundShowsError(): void
    {
        $controller = $this->createController();

        $this->userRepository->method('findById')->willReturn(null);

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->expects($this->once())
            ->method('withViewPath')
            ->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->willReturn($response);

        $result = $controller->userSessionHistory(999);

        $this->assertSame($response, $result);
    }

    private function createController(): AdminController
    {
        return $this->harness->createAdminController(
            userRepository: $this->userRepository,
            userProfileRepository: $this->userProfileRepository,
            translator: $this->translator,
            viewRenderer: $this->viewRenderer,
            validator: $this->validator,
            currentUser: $this->currentUser,
            responseFactory: $this->responseFactory,
            hydrator: $this->hydrator,
            flash: $this->flash,
            passwordHasher: $this->passwordHasher,
            passwordGenerator: $this->passwordGenerator,
            createService: $this->createService,
            blockService: $this->blockService,
            confirmationService: $this->confirmationService,
            recoveryService: $this->recoveryService,
            expireService: $this->expireService,
            switchIdentityService: $this->switchIdentityService,
            updateAssignmentsService: $this->updateAssignmentsService,
            userSessionHistoryRepository: $this->userSessionHistoryRepository,
            authHelper: $this->authHelper,
        );
    }
}
