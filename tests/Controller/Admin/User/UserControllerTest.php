<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Controller\Admin\User;

use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use YiiRocks\Voyti\Controller\Admin\User\UserController;
use YiiRocks\Voyti\Helper\AuthHelper;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Model\UserProfile;
use YiiRocks\Voyti\Model\UserSessions;
use YiiRocks\Voyti\ModuleConfig;
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
use YiiRocks\Voyti\tests\Support\DatabaseSetupTrait;
use YiiRocks\Voyti\tests\Support\RedirectResponseMockTrait;
use YiiRocks\Voyti\tests\TestCase;
use Yiisoft\Data\Paginator\OffsetPaginator;
use Yiisoft\Hydrator\HydratorInterface;
use Yiisoft\Security\PasswordHasher;
use Yiisoft\Session\Flash\FlashInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\User\CurrentUser;
use Yiisoft\Validator\Result;
use Yiisoft\Validator\ValidatorInterface;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

#[AllowMockObjectsWithoutExpectations]
final class UserControllerTest extends TestCase
{
    use DatabaseSetupTrait;
    use RedirectResponseMockTrait;

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
        $this->passwordGenerator = $this->createMock(PasswordGeneratorInterface::class);
        $this->createService = $this->createMock(CreateService::class);
        $this->blockService = $this->createMock(BlockService::class);
        $this->confirmationService = $this->createMock(ConfirmationService::class);
        $this->recoveryService = $this->createMock(RecoveryService::class);
        $this->expireService = $this->createMock(ExpireService::class);
        $this->switchIdentityService = $this->createMock(SwitchIdentityService::class);
        $this->updateAssignmentsService = $this->createMock(UpdateAssignmentsService::class);
        $this->authHelper = $this->createMock(AuthHelper::class);
    }

    protected function tearDown(): void
    {
        $this->tearDownDatabase();
    }

    public function testAssignmentsGetShowsAssignments(): void
    {
        $user = $this->createUser();
        $controller = $this->createController();
        $request = new ServerRequest('GET', '/');

        $this->authHelper->method('getUnassignedItems')->willReturn([]);

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->expects($this->once())
            ->method('withViewPath')
            ->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('admin/user/_assignments', $this->anything())
            ->willReturn($response);

        $result = $controller->assignments($request, (int) $user->getId());

        $this->assertSame($response, $result);
    }

    public function testAssignmentsPostUpdates(): void
    {
        $user = $this->createUser();
        $userId = (int) $user->getId();
        $controller = $this->createController();
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['items' => ['admin', 'editor']]);

        $this->updateAssignmentsService->expects($this->once())->method('run')->with($userId, ['admin', 'editor']);
        $this->authHelper->method('getUnassignedItems')->willReturn([]);

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->expects($this->once())
            ->method('withViewPath')
            ->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->willReturn($response);

        $result = $controller->assignments($request, $userId);

        $this->assertSame($response, $result);
    }

    public function testAssignmentsUserNotFoundShowsError(): void
    {
        $this->assertNotFoundRendersError(
            static fn(UserController $controller): ResponseInterface => $controller->assignments(new ServerRequest('GET', '/'), 999999),
        );
    }

    public function testBlockNonExistentUserStillRedirects(): void
    {
        $controller = $this->createController();

        $response = $this->mockRedirectResponse($this->responseFactory);

        $result = $controller->block(999999);

        $this->assertSame($response, $result);
    }

    public function testBlockTogglesUserBlock(): void
    {
        $user = $this->createUser();
        $userId = (int) $user->getId();
        $controller = $this->createController();

        $this->blockService->expects($this->once())
            ->method('run')
            ->with($this->callback(static fn(User $u): bool => $u->getId() === $user->getId()));

        $response = $this->mockRedirectResponse($this->responseFactory);

        $result = $controller->block($userId);

        $this->assertSame($response, $result);
    }

    public function testConfirmFailureShowsError(): void
    {
        $user = $this->createUser();
        $controller = $this->createController();

        $this->confirmationService->expects($this->once())->method('run')->willReturn(false);

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->expects($this->once())
            ->method('withViewPath')
            ->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->willReturn($response);

        $result = $controller->confirm((int) $user->getId());

        $this->assertSame($response, $result);
    }

    public function testConfirmSuccessful(): void
    {
        $user = $this->createUser();
        $controller = $this->createController();

        $this->confirmationService->expects($this->once())->method('run')->willReturn(true);

        $response = $this->mockRedirectResponse($this->responseFactory);

        $result = $controller->confirm((int) $user->getId());

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
            ->with('admin/user/create', $this->anything())
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

        $response = $this->mockRedirectResponse($this->responseFactory);

        $result = $controller->create($request);

        $this->assertSame($response, $result);
    }

    public function testCreatePostWithAssignedItemsAssignsUser(): void
    {
        $createdUser = $this->createUserWithUsername('newuser');
        $createdUserId = (int) $createdUser->getId();

        $controller = $this->createController();
        $request = (new ServerRequest('POST', '/'))->withParsedBody([
            'register' => ['username' => 'newuser', 'email' => 'new@example.com', 'password' => 'password123', 'passwordRepeat' => 'password123'],
            'assignedItems' => ['admin'],
        ]);

        $this->hydrator->method('hydrate')->willReturnCallback(
            static function (object $object, array $data = []): void {
                foreach ($data as $key => $value) {
                    if (property_exists($object, $key)) {
                        $object->$key = $value;
                    }
                }
            },
        );
        $this->createService->method('run')->willReturn(ServiceResult::success('User created'));

        $this->updateAssignmentsService->expects($this->once())->method('run')->with($createdUserId, ['admin']);

        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->method('createResponse')->willReturn($response);
        $response->method('withHeader')->willReturnSelf();

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
        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn('999999');
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $user = $this->createUser();
        $userId = (int) $user->getId();

        $controller = $this->createController();
        $request = new ServerRequest('GET', '/');

        $response = $this->mockRedirectResponse($this->responseFactory);

        $result = $controller->delete($request, $userId);

        $this->assertSame($response, $result);
        $this->assertNull(User::findById($userId));
    }

    public function testDeleteNonExistentUserShowsError(): void
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

        $result = $controller->delete($request, 999999);

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
        $this->assertNotFoundRendersError(
            static fn(UserController $controller): ResponseInterface => $controller->forcePasswordChange(999999),
        );
    }

    public function testForcePasswordChangeUserFound(): void
    {
        $user = $this->createUser();
        $controller = $this->createController();

        $this->expireService->expects($this->once())->method('run')->willReturn(true);

        $response = $this->mockRedirectResponse($this->responseFactory);

        $result = $controller->forcePasswordChange((int) $user->getId());

        $this->assertSame($response, $result);
    }

    public function testIndexPassesPaginatorWithNoResults(): void
    {
        $controller = $this->createController();
        $request = new ServerRequest('GET', '/');

        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn('1');
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

        $controller->index($request);

        $this->assertArrayHasKey('paginator', $captured);
        $paginator = $captured['paginator'];
        $this->assertInstanceOf(OffsetPaginator::class, $paginator);
        $this->assertSame(0, $paginator->getTotalPages());
        $this->assertSame(1, $paginator->getCurrentPage());
    }

    public function testIndexShowsUserList(): void
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
            ->with('admin/user/index', $this->anything())
            ->willReturn($response);

        $result = $controller->index($request);

        $this->assertSame($response, $result);
    }

    public function testInfoShowsUserInfo(): void
    {
        $user = $this->createUserWithProfile();
        $controller = $this->createController();

        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn('1');
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->expects($this->once())
            ->method('withViewPath')
            ->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('admin/user/_info', $this->anything())
            ->willReturn($response);

        $result = $controller->show((int) $user->getId());

        $this->assertSame($response, $result);
    }

    public function testInfoUserNotFoundShowsError(): void
    {
        $this->assertNotFoundRendersError(
            static fn(UserController $controller): ResponseInterface => $controller->show(999999),
        );
    }

    public function testPasswordResetUserFound(): void
    {
        $user = $this->createUser('test@example.com');
        $controller = $this->createController();

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

        $result = $controller->passwordReset((int) $user->getId());

        $this->assertSame($response, $result);
    }

    public function testPasswordResetUserNotFoundShowsError(): void
    {
        $this->assertNotFoundRendersError(
            static fn(UserController $controller): ResponseInterface => $controller->passwordReset(999999),
        );
    }

    public function testSessionsUserFound(): void
    {
        $user = $this->createUser();
        $controller = $this->createController();

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->expects($this->once())
            ->method('withViewPath')
            ->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('admin/user/_sessions', $this->anything())
            ->willReturn($response);

        $result = $controller->sessions((int) $user->getId());

        $this->assertSame($response, $result);
    }

    public function testSessionsUserNotFoundShowsError(): void
    {
        $this->assertNotFoundRendersError(
            static fn(UserController $controller): ResponseInterface => $controller->sessions(999999),
        );
    }

    public function testSwitchIdentityFailureShowsError(): void
    {
        $controller = $this->createController();

        $this->switchIdentityService->expects($this->once())
            ->method('run')
            ->willReturn(ServiceResult::failure('Cannot switch identity'));

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

    public function testSwitchIdentityRestoreFailureShowsError(): void
    {
        $controller = $this->createController();

        $this->switchIdentityService->expects($this->once())
            ->method('restore')
            ->willReturn(ServiceResult::failure());

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->expects($this->once())
            ->method('withViewPath')
            ->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->willReturn($response);

        $result = $controller->switchIdentityRestore();

        $this->assertSame($response, $result);
    }

    public function testSwitchIdentityRestoreSuccessRedirects(): void
    {
        $controller = $this->createController();

        $this->switchIdentityService->expects($this->once())
            ->method('restore')
            ->willReturn(ServiceResult::success());

        $response = $this->mockRedirectResponse($this->responseFactory);

        $result = $controller->switchIdentityRestore();

        $this->assertSame($response, $result);
    }

    public function testSwitchIdentitySuccessRedirects(): void
    {
        $controller = $this->createController();

        $this->switchIdentityService->expects($this->once())
            ->method('run')
            ->willReturn(ServiceResult::success());

        $response = $this->mockRedirectResponse($this->responseFactory);

        $result = $controller->switchIdentity(1);

        $this->assertSame($response, $result);
    }

    public function testTerminateSessionsUserFound(): void
    {
        $user = $this->createUser();
        $userId = (int) $user->getId();
        $this->createSession($userId, 'sess-1');
        $controller = $this->createController();

        $response = $this->mockRedirectResponse($this->responseFactory);

        $result = $controller->terminateSessions($userId);

        $this->assertSame($response, $result);
        $this->assertSame([], UserSessions::findByUserId($userId));
    }

    public function testTerminateSessionsUserNotFoundShowsError(): void
    {
        $this->assertNotFoundRendersError(
            static fn(UserController $controller): ResponseInterface => $controller->terminateSessions(999999),
        );
    }

    public function testUpdateGetShowsForm(): void
    {
        $user = $this->createUser();
        $controller = $this->createController();
        $request = new ServerRequest('GET', '/');

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->expects($this->once())
            ->method('withViewPath')
            ->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('admin/user/_account', $this->anything())
            ->willReturn($response);

        $result = $controller->update($request, (int) $user->getId());

        $this->assertSame($response, $result);
    }

    public function testUpdatePostSuccessful(): void
    {
        $user = $this->createUser();
        $userId = (int) $user->getId();
        $controller = $this->createController();
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['user' => ['username' => 'updated', 'email' => 'updated@example.com', 'password' => ''], 'assignedItems' => []]);

        $this->updateAssignmentsService->expects($this->once())->method('run');

        $response = $this->mockRedirectResponse($this->responseFactory);

        $result = $controller->update($request, $userId);

        $this->assertSame($response, $result);
        $updated = User::findById($userId);
        $this->assertNotNull($updated);
        $this->assertSame('updated', $updated->getUsername());
        $this->assertSame('updated@example.com', $updated->getEmail());
    }

    public function testUpdatePostWithPasswordChange(): void
    {
        $user = $this->createUser();
        $userId = (int) $user->getId();
        $originalHash = $user->getPasswordHash();
        $controller = $this->createController();
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['user' => ['username' => 'updated', 'email' => 'updated@example.com', 'password' => 'newpass'], 'assignedItems' => []]);

        $this->updateAssignmentsService->expects($this->once())->method('run');

        $response = $this->mockRedirectResponse($this->responseFactory);

        $result = $controller->update($request, $userId);

        $this->assertSame($response, $result);
        $updated = User::findById($userId);
        $this->assertNotNull($updated);
        $this->assertNotSame($originalHash, $updated->getPasswordHash());
        $this->assertNotNull($updated->getPasswordChangedAt());
    }

    public function testUpdatePostWithPreviouslyUsedPasswordShowsError(): void
    {
        $this->config = new ModuleConfig(enablePasswordExpiration: true);
        $this->harness = new ControllerHarness($this->config);

        $user = new User();
        $user->setUsername('testuser');
        $user->setEmail('testuser@example.com');
        $user->setPasswordHash($this->passwordHasher->hash('originalpass'));
        $user->setAuthKey('key');
        $user->setCreatedAt(time());
        $user->setUpdatedAt(time());
        $user->save();
        $userId = (int) $user->getId();

        $controller = $this->createController();
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['user' => ['username' => 'updated', 'email' => 'updated@example.com', 'password' => 'originalpass'], 'assignedItems' => []]);

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->method('withViewPath')->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('admin/user/_account', $this->callback(
                static fn(array $params): bool => $params['errors'] !== [],
            ))
            ->willReturn($response);

        $result = $controller->update($request, $userId);

        $this->assertSame($response, $result);
        $updated = User::findById($userId);
        $this->assertNotNull($updated);
        $this->assertSame('testuser', $updated->getUsername());
    }

    public function testUpdateProfileGetCreatesNewProfileWhenNoneExists(): void
    {
        $user = $this->createUser();
        $controller = $this->createController();
        $request = new ServerRequest('GET', '/');

        $response = $this->createMock(ResponseInterface::class);
        $captured = [];
        $this->viewRenderer->method('withViewPath')->willReturnSelf();
        $this->viewRenderer->method('render')
            ->willReturnCallback(function (string $view, array $params) use (&$captured, $response): ResponseInterface {
                $captured = $params;
                return $response;
            });

        $result = $controller->updateProfile($request, (int) $user->getId());

        $this->assertSame($response, $result);
        $this->assertSame('', $captured['model']->name);
    }

    public function testUpdateProfileGetShowsForm(): void
    {
        $user = $this->createUserWithProfile('John');
        $controller = $this->createController();
        $request = new ServerRequest('GET', '/');

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->expects($this->once())
            ->method('withViewPath')
            ->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('admin/user/_profile', $this->anything())
            ->willReturn($response);

        $result = $controller->updateProfile($request, (int) $user->getId());

        $this->assertSame($response, $result);
    }

    public function testUpdateProfilePostSuccessful(): void
    {
        $user = $this->createUserWithProfile('Original');
        $userId = (int) $user->getId();
        $controller = $this->createController();
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['userProfile' => ['name' => 'Updated', 'publicEmail' => '', 'gravatarEmail' => '', 'location' => '', 'website' => '', 'timezone' => '', 'bio' => '', 'birthday' => '1990-05-15']]);

        $this->validator->method('validate')->willReturn(new Result());
        $this->hydrator->method('hydrate')->willReturnCallback(
            static function (object $object, array $data = []): void {
                foreach ($data as $key => $value) {
                    if (property_exists($object, $key)) {
                        $object->$key = $value;
                    }
                }
            },
        );

        $response = $this->mockRedirectResponse($this->responseFactory);

        $result = $controller->updateProfile($request, $userId);

        $this->assertSame($response, $result);
        $updated = UserProfile::findByUserId($userId);
        $this->assertNotNull($updated);
        $this->assertSame('Updated', $updated->getName());
        $this->assertSame('1990-05-15', $updated->getBirthday()?->format('Y-m-d'));
    }

    public function testUpdateProfileUserNotFoundShowsError(): void
    {
        $this->assertNotFoundRendersError(
            static fn(UserController $controller): ResponseInterface => $controller->updateProfile(new ServerRequest('GET', '/'), 999999),
        );
    }

    public function testUpdateUserNotFoundShowsError(): void
    {
        $this->assertNotFoundRendersError(
            static fn(UserController $controller): ResponseInterface => $controller->update(new ServerRequest('GET', '/'), 999999),
        );
    }

    private function assertNotFoundRendersError(callable $invoke): void
    {
        $controller = $this->createController();

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->expects($this->once())
            ->method('withViewPath')
            ->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->willReturn($response);

        $result = $invoke($controller);

        $this->assertSame($response, $result);
    }

    private function createController(): UserController
    {
        return $this->harness->createUserController(
            translator: $this->translator,
            viewRenderer: $this->viewRenderer,
            validator: $this->validator,
            currentUser: $this->currentUser,
            responseFactory: $this->responseFactory,
            hydrator: $this->hydrator,
            flash: $this->flash,
            passwordGenerator: $this->passwordGenerator,
            createService: $this->createService,
            blockService: $this->blockService,
            confirmationService: $this->confirmationService,
            recoveryService: $this->recoveryService,
            expireService: $this->expireService,
            switchIdentityService: $this->switchIdentityService,
            updateAssignmentsService: $this->updateAssignmentsService,
            authHelper: $this->authHelper,
        );
    }

    private function createSession(int $userId, string $sessionId): UserSessions
    {
        $session = new UserSessions();
        $session->setUserId($userId);
        $session->setSessionId($sessionId);
        $session->setIp('203.0.113.1');
        $session->setCreatedAt(time());
        $session->setUpdatedAt(time());
        $session->save();

        return $session;
    }

    private function createUser(string $email = 'testuser@example.com'): User
    {
        $user = new User();
        $user->setUsername('testuser');
        $user->setEmail($email);
        $user->setPasswordHash('hash');
        $user->setAuthKey('key');
        $user->setCreatedAt(time());
        $user->setUpdatedAt(time());
        $user->save();

        return $user;
    }

    private function createUserWithProfile(string $name = 'John'): User
    {
        $user = $this->createUser();

        $profile = new UserProfile();
        $profile->setUserId((int) $user->getId());
        $profile->setName($name);
        $profile->save();

        return $user;
    }

    private function createUserWithUsername(string $username): User
    {
        $user = new User();
        $user->setUsername($username);
        $user->setEmail($username . '@example.com');
        $user->setPasswordHash('hash');
        $user->setAuthKey('key');
        $user->setCreatedAt(time());
        $user->setUpdatedAt(time());
        $user->save();

        return $user;
    }
}
