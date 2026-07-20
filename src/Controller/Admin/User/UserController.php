<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Controller\Admin\User;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use YiiRocks\Voyti\Controller\ActorIdTrait;
use YiiRocks\Voyti\Controller\RedirectTrait;
use YiiRocks\Voyti\Controller\RenderTrait;
use YiiRocks\Voyti\Helper\AuthHelper;
use YiiRocks\Voyti\Helper\FlashType;
use YiiRocks\Voyti\Helper\InputDataTrait;
use YiiRocks\Voyti\Model\Form\Auth\RegistrationForm;
use YiiRocks\Voyti\Model\Form\Settings\SettingsForm;
use YiiRocks\Voyti\Model\Form\Settings\UserProfileForm;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Model\UserProfile;
use YiiRocks\Voyti\Model\UserSessions;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Service\AuditLogService;
use YiiRocks\Voyti\Service\Password\ExpireService;
use YiiRocks\Voyti\Service\Password\PasswordGeneratorInterface;
use YiiRocks\Voyti\Service\Password\PasswordHistoryService;
use YiiRocks\Voyti\Service\Password\RecoveryService;
use YiiRocks\Voyti\Service\Rbac\UpdateAssignmentsService;
use YiiRocks\Voyti\Service\SwitchIdentityService;
use YiiRocks\Voyti\Service\User\BlockService;
use YiiRocks\Voyti\Service\User\ConfirmationService;
use YiiRocks\Voyti\Service\User\CreateService;
use YiiRocks\Voyti\ViewData\Admin\User\AccountViewData;
use YiiRocks\Voyti\ViewData\Admin\User\AssignmentsViewData;
use YiiRocks\Voyti\ViewData\Admin\User\CreateViewData;
use YiiRocks\Voyti\ViewData\Admin\User\IndexViewData;
use YiiRocks\Voyti\ViewData\Admin\User\InfoViewData;
use YiiRocks\Voyti\ViewData\Admin\User\ProfileViewData;
use YiiRocks\Voyti\ViewData\Admin\User\SessionsViewData;
use YiiRocks\Voyti\ViewData\Shared\MessageViewData;
use Yiisoft\Data\Db\QueryDataReader;
use Yiisoft\Data\Paginator\OffsetPaginator;
use Yiisoft\Http\Method;
use Yiisoft\Hydrator\HydratorInterface;
use Yiisoft\Rbac\Assignment;
use Yiisoft\Rbac\AssignmentsStorageInterface;
use Yiisoft\Rbac\ItemsStorageInterface;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Session\Flash\FlashInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\User\CurrentUser;
use Yiisoft\Validator\ValidatorInterface;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

/**
 * Admin CRUD for user accounts: listing/creating/updating/deleting users, plus block/confirm/reset,
 * profile editing, RBAC role assignment, session management, and identity switching. Every mutating
 * action writes an {@see AuditLogService} entry attributing the change to the acting admin.
 */
final readonly class UserController
{
    use ActorIdTrait;
    use InputDataTrait;
    use RedirectTrait;
    use RenderTrait;

    public function __construct(
        private TranslatorInterface $translator,
        private WebViewRenderer $viewRenderer,
        private CreateService $userCreateService,
        private BlockService $userBlockService,
        private ConfirmationService $userConfirmationService,
        private RecoveryService $passwordRecoveryService,
        private ExpireService $passwordExpireService,
        private SwitchIdentityService $switchIdentityService,
        private UpdateAssignmentsService $updateAuthAssignmentsService,
        private AuthHelper $authHelper,
        private PasswordGeneratorInterface $passwordGenerator,
        private ValidatorInterface $validator,
        private EventDispatcherInterface $eventDispatcher,
        private UrlGeneratorInterface $url,
        private ModuleConfig $config,
        private HydratorInterface $hydrator,
        private CurrentUser $currentUser,
        private ResponseFactoryInterface $responseFactory,
        private ItemsStorageInterface $itemsStorage,
        private AssignmentsStorageInterface $assignmentsStorage,
        private FlashInterface $flash,
        private PasswordHistoryService $passwordHistoryService,
        private AuditLogService $auditLogService,
    ) {}

    public function assignments(ServerRequestInterface $request, int $id): ResponseInterface
    {
        $user = $this->resolveUser($id);
        if (!$user instanceof User) {
            return $user;
        }

        if ($request->getMethod() === Method::POST) {
            $body = $this->parsedBody($request);
            /** @var mixed $rawItems */
            $rawItems = $body['items'] ?? null;
            $items = is_array($rawItems) ? $rawItems : [];
            $this->updateAuthAssignmentsService->run($id, $items);
            $this->auditLogService->log($this->actorId(), 'user.assignments_update', targetUserId: $id);
        }

        $assignments = $this->assignmentsStorage->getByUserId((string) $id);
        $assignedNames = array_values(array_map(fn(Assignment $a) => $a->getItemName(), $assignments));
        $available = $this->authHelper->getUnassignedItems($id);

        return $this->renderView('admin/user/_assignments', [
            'data' => AssignmentsViewData::create($user, $assignedNames, $available, $this->url, $this->translator()),
        ]);
    }

    public function block(int $id): ResponseInterface
    {
        $user = User::findById($id);
        if ($user !== null) {
            $this->userBlockService->run($user);
            $this->auditLogService->log(
                $this->actorId(),
                $user->isBlocked() ? 'user.block' : 'user.unblock',
                targetUserId: $id,
            );
        }

        return $this->redirectWithFlash($this->url->generate('voyti/admin-users'), 'voyti.admin.user_status_changed');
    }

    public function confirm(int $id): ResponseInterface
    {
        $user = User::findById($id);
        if ($user !== null && $this->userConfirmationService->run($user)) {
            $this->auditLogService->log($this->actorId(), 'user.confirm', targetUserId: $id);

            return $this->redirectWithFlash($this->url->generate('voyti/admin-users'), 'voyti.admin.user_confirmed');
        }
        return $this->renderError('voyti.admin.unable_to_confirm');
    }

    public function create(ServerRequestInterface $request): ResponseInterface
    {
        $errors = [];
        $form = new RegistrationForm($this->config, $this->translator);

        if ($request->getMethod() === Method::POST) {
            $body = $this->parsedBody($request);
            $this->hydrator->hydrate($form, $this->formData($body, $form->getFormName()));
            $email = $form->email;
            $username = $form->username;
            $password = $form->password !== '' ? $form->password : $this->passwordGenerator->generate(12);

            $result = $this->userCreateService->run($email, $username, $password);
            if ($result->isSuccess()) {
                $createdUser = User::findByUsername($username);

                /** @var mixed $rawAssignedItems */
                $rawAssignedItems = $body['assignedItems'] ?? null;
                $items = is_array($rawAssignedItems) ? $rawAssignedItems : [];
                if ($items !== [] && $createdUser !== null) {
                    $this->updateAuthAssignmentsService->run((int) $createdUser->getId(), $items);
                }

                $this->auditLogService->log(
                    $this->actorId(),
                    'user.create',
                    targetUserId: $createdUser?->getIdOrZero(),
                    targetName: $username,
                );

                return $this->redirectWithFlash($this->url->generate('voyti/admin-users'), 'voyti.admin.user_created');
            }
            /** @var array<string, list<string>> $errors */
            $errors = $result->getErrors();
        }

        return $this->renderView('admin/user/create', [
            'form' => $form,
            'data' => CreateViewData::create($form, $this->itemsStorage->getAll(), [], $errors, $this->url, $this->translator()),
        ]);
    }

    public function delete(ServerRequestInterface $request, int $id): ResponseInterface
    {
        $identity = $this->currentUser->getIdentity();
        if ($id === (int) $identity->getId()) {
            return $this->renderError('voyti.admin.cannot_delete_self');
        }
        $user = User::findById($id);
        if ($user !== null) {
            $user->delete();
            $this->auditLogService->log(
                $this->actorId(),
                'user.delete',
                targetUserId: $id,
                targetName: $user->getUsername(),
            );

            return $this->redirectWithFlash($this->url->generate('voyti/admin-users'), 'voyti.admin.user_deleted');
        }
        return $this->renderError('voyti.admin.user_not_found');
    }

    public function forcePasswordChange(int $id): ResponseInterface
    {
        $user = User::findById($id);
        if ($user !== null && $this->passwordExpireService->run($user)) {
            $this->auditLogService->log($this->actorId(), 'user.force_password_change', targetUserId: $id);

            return $this->redirectWithFlash(
                $this->url->generate('voyti/admin-users'),
                'voyti.admin.password_change_required',
            );
        }
        return $this->renderError('voyti.admin.error_occurred');
    }

    public function index(ServerRequestInterface $request): ResponseInterface
    {
        $queryParams = $this->queryParams($request);
        $filters = [
            'username' => $this->stringValue($queryParams, 'username'),
            'email' => $this->stringValue($queryParams, 'email'),
            'status' => $this->stringValue($queryParams, 'status'),
        ];

        $reader = new QueryDataReader(User::searchQuery($filters));
        $paginator = (new OffsetPaginator($reader))->withPageSize(50);
        $requestedPage = max(1, (int) ($queryParams['page'] ?? 1));
        $paginator = $paginator->withCurrentPage(min($requestedPage, max(1, $paginator->getTotalPages())));

        /** @var list<User> $users */
        $users = iterator_to_array($paginator->read(), false);

        return $this->renderView('admin/user/index', [
            'data' => IndexViewData::create(
                $users,
                $paginator,
                $filters,
                $this->config,
                $this->url,
                $this->translator(),
                $this->switchIdentityService->isSwitched(),
                $this->switchIdentityService->getOriginalUser(),
                (int) $this->currentUser->getIdentity()->getId(),
            ),
        ]);
    }

    public function passwordReset(int $id): ResponseInterface
    {
        $user = User::findById($id);
        if ($user !== null) {
            $result = $this->passwordRecoveryService->run($user->getEmail());
            $this->auditLogService->log($this->actorId(), 'user.password_reset_triggered', targetUserId: $id);

            return $this->renderView('shared/message', [
                'data' => new MessageViewData(title: $result->getMessage(), homeUrl: $this->homeUrl()),
            ]);
        }
        return $this->renderError('voyti.admin.user_not_found');
    }

    public function sessions(int $id): ResponseInterface
    {
        $user = $this->resolveUser($id);
        if (!$user instanceof User) {
            return $user;
        }

        $sessions = UserSessions::findByUserId($id);
        $viewer = $this->currentUser->getIdentity();
        $viewerTimezone = $viewer instanceof User ? $viewer->getProfile()?->getTimezone() : null;

        return $this->renderView('admin/user/_sessions', [
            'data' => SessionsViewData::create($user, $sessions, $this->url, $this->translator(), $viewerTimezone),
        ]);
    }

    public function show(int $id): ResponseInterface
    {
        $user = $this->resolveUser($id);
        if (!$user instanceof User) {
            return $user;
        }
        $userProfile = $user->getProfile();
        if ($userProfile === null) {
            $userProfile = new UserProfile();
            $userProfile->setUserId($id);
        }

        $viewer = $this->currentUser->getIdentity();
        $viewerTimezone = $viewer instanceof User ? $viewer->getProfile()?->getTimezone() : null;

        return $this->renderView('admin/user/_info', [
            'data' => InfoViewData::create($user, $userProfile, $this->url, $this->translator(), $viewerTimezone),
        ]);
    }

    public function switchIdentity(ServerRequestInterface $request, int $id): ResponseInterface
    {
        $result = $this->switchIdentityService->run($id, $request->getServerParams());
        if ($result->isSuccess()) {
            $this->auditLogService->log($this->actorId(), 'user.switch_identity', targetUserId: $id);
            $this->flash->set(
                FlashType::SUCCESS,
                $this->translator->translate('voyti.admin.switch_identity_success', category: 'voyti'),
            );

            return $this->redirect($this->url->generate('voyti/profile-update'));
        }

        return $this->renderError($result->getMessage() !== '' ? $result->getMessage() : 'voyti.admin.error_occurred');
    }

    public function switchIdentityRestore(ServerRequestInterface $request): ResponseInterface
    {
        $result = $this->switchIdentityService->restore($request->getServerParams());
        if ($result->isSuccess()) {
            $this->flash->set(
                FlashType::SUCCESS,
                $this->translator->translate('voyti.admin.switch_identity_restored', category: 'voyti'),
            );

            return $this->redirect($this->url->generate('voyti/profile-update'));
        }

        return $this->renderError($result->getMessage() !== '' ? $result->getMessage() : 'voyti.admin.error_occurred');
    }

    public function terminateSessions(int $id): ResponseInterface
    {
        $user = $this->resolveUser($id);
        if (!$user instanceof User) {
            return $user;
        }

        $sessions = UserSessions::findByUserId($id);
        foreach ($sessions as $session) {
            if (!$session->isRevoked()) {
                $session->setRevokedAt(time());
                $session->save();
            }
        }

        return $this->redirectWithFlash(
            $this->url->generate('voyti/admin-users-sessions', ['id' => $id]),
            'voyti.admin.sessions_terminated',
        );
    }

    public function update(ServerRequestInterface $request, int $id): ResponseInterface
    {
        $user = $this->resolveUser($id);
        if (!$user instanceof User) {
            return $user;
        }

        $form = new SettingsForm($this->config, $this->translator);
        $form->username = $user->getUsername();
        $form->email = $user->getEmail();
        $errors = [];

        if ($request->getMethod() === Method::POST) {
            $body = $this->parsedBody($request);
            /** @var mixed $rawUserData */
            $rawUserData = $body['user'] ?? null;
            $userData = is_array($rawUserData) ? $rawUserData : [];
            $password = $this->stringValue($userData, 'password');

            if ($password !== '' && $this->passwordHistoryService->wasUsedRecently($user, $password)) {
                $errors = [
                    'password' => [
                        $this->translator->translate('voyti.admin.password_previously_used', category: 'voyti'),
                    ],
                ];
            } else {
                $user->setUsername($this->stringValue($userData, 'username', $user->getUsername()));
                $user->setEmail($this->stringValue($userData, 'email', $user->getEmail()));
                if ($password !== '') {
                    $this->passwordHistoryService->applyPasswordChange($user, $password);
                } else {
                    $user->setUpdatedAt(time());
                    $user->save();
                }

                /** @var mixed $rawAssignedItems */
                $rawAssignedItems = $body['assignedItems'] ?? null;
                $items = is_array($rawAssignedItems) ? $rawAssignedItems : [];
                $this->updateAuthAssignmentsService->run($id, $items);

                $this->auditLogService->log(
                    $this->actorId(),
                    'user.update',
                    targetUserId: $id,
                    context: ['passwordChanged' => $password !== ''],
                );

                return $this->redirectWithFlash(
                    $this->url->generate('voyti/admin-users'),
                    'voyti.admin.account_updated',
                );
            }
        }

        $assignments = $this->assignmentsStorage->getByUserId((string) $id);
        $assignedNames = array_values(array_map(fn(Assignment $a) => $a->getItemName(), $assignments));

        return $this->renderView('admin/user/_account', [
            'form' => $form,
            'data' => AccountViewData::create(
                $user,
                $form,
                $this->itemsStorage->getAll(),
                $assignedNames,
                $errors,
                $this->url,
                $this->translator(),
            ),
        ]);
    }

    public function updateProfile(ServerRequestInterface $request, int $id): ResponseInterface
    {
        $user = $this->resolveUser($id);
        if (!$user instanceof User) {
            return $user;
        }

        $userProfile = $user->getProfile();
        if ($userProfile === null) {
            $userProfile = new UserProfile();
            $userProfile->setUserId($id);
        }

        $form = UserProfileForm::fromProfile($userProfile, $this->translator);

        if ($request->getMethod() === Method::POST) {
            $body = $this->parsedBody($request);
            $this->hydrator->hydrate($form, $this->formData($body, $form->getFormName()));
            $result = $this->validator->validate($form);
            $form->processValidationResult($result);
            if ($result->isValid()) {
                $form->applyToProfile($userProfile);
                $userProfile->save();
                return $this->redirectWithFlash(
                    $this->url->generate('voyti/admin-users-update-profile', ['id' => $id]),
                    'voyti.admin.profile_details_updated',
                );
            }
        }

        return $this->renderView('admin/user/_profile', [
            'form' => $form,
            'data' => ProfileViewData::create($user, $this->url, $this->translator()),
        ]);
    }

    private function resolveUser(int $id): User|ResponseInterface
    {
        $user = User::findById($id);
        return $user ?? $this->renderError('voyti.admin.user_not_found');
    }
}
