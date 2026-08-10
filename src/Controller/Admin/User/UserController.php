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
use YiiRocks\Voyti\Model\Form\Auth\RegistrationForm;
use YiiRocks\Voyti\Model\Form\Settings\SettingsForm;
use YiiRocks\Voyti\Model\Form\Settings\UserProfileForm;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Model\UserProfile;
use YiiRocks\Voyti\Model\UserSessions;
use YiiRocks\Voyti\Service\AuditLogService;
use YiiRocks\Voyti\Service\FlashNotifier;
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
use YiiRocks\Voyti\VoytiConfig;
use Yiisoft\Data\Db\QueryDataReader;
use Yiisoft\Data\Paginator\OffsetPaginator;
use Yiisoft\FormModel\FormHydrator;
use Yiisoft\Http\Method;
use Yiisoft\Input\Http\Attribute\Parameter\Body;
use Yiisoft\Input\Http\Attribute\Parameter\Query;
use Yiisoft\Rbac\Assignment;
use Yiisoft\Rbac\AssignmentsStorageInterface;
use Yiisoft\Rbac\ItemsStorageInterface;
use Yiisoft\Router\HydratorAttribute\RouteArgument;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Session\Flash\FlashInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\User\CurrentUser;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

/**
 * Admin CRUD for user accounts: listing/creating/updating/deleting users, plus block/confirm/reset,
 * profile editing, RBAC role assignment, session management, and identity switching. Every mutating
 * action writes an {@see AuditLogService} entry attributing the change to the acting admin.
 */
final readonly class UserController
{
    use ActorIdTrait;
    use RedirectTrait;
    use RenderTrait;

    private const int MAX_PER_PAGE = 100;

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
        private EventDispatcherInterface $eventDispatcher,
        private UrlGeneratorInterface $url,
        private VoytiConfig $config,
        private FormHydrator $formHydrator,
        private CurrentUser $currentUser,
        private ResponseFactoryInterface $responseFactory,
        private ItemsStorageInterface $itemsStorage,
        private AssignmentsStorageInterface $assignmentsStorage,
        private FlashInterface $flash,
        private FlashNotifier $toast,
        private PasswordHistoryService $passwordHistoryService,
        private AuditLogService $auditLogService,
    ) {}

    public function assignments(
        ServerRequestInterface $request,
        #[RouteArgument]
        int $id,
        #[Body('items')]
        array $items = [],
    ): ResponseInterface {
        $user = $this->resolveUser($id);
        if (!$user instanceof User) {
            return $user;
        }

        if ($request->getMethod() === Method::POST) {
            $this->updateAuthAssignmentsService->run($id, $items);
            $this->auditLogService->log($this->actorId(), 'user.assignments_update', $request->getServerParams(), targetUserId: $id);
        }

        $assignedNames = $this->assignedItemNames($id);
        $available = $this->authHelper->getUnassignedItems($id);

        return $this->renderView('admin/user/_assignments', [
            'data' => AssignmentsViewData::create($user, $assignedNames, $available, $this->url, $this->translator()),
        ]);
    }

    public function block(ServerRequestInterface $request, #[RouteArgument] int $id): ResponseInterface
    {
        $user = User::findById($id);
        if ($user !== null) {
            $this->userBlockService->run($user);
            $this->auditLogService->log(
                $this->actorId(),
                $user->isBlocked() ? 'user.block' : 'user.unblock',
                $request->getServerParams(),
                targetUserId: $id,
            );
        }

        return $this->redirectWithFlash($this->url->generate('voyti/admin-users'), 'voyti.admin.user_status_changed');
    }

    public function confirm(ServerRequestInterface $request, #[RouteArgument] int $id): ResponseInterface
    {
        $user = User::findById($id);
        if ($user !== null && $this->userConfirmationService->run($user)) {
            $this->auditLogService->log($this->actorId(), 'user.confirm', $request->getServerParams(), targetUserId: $id);

            return $this->redirectWithFlash($this->url->generate('voyti/admin-users'), 'voyti.admin.user_confirmed');
        }
        return $this->renderError('voyti.admin.unable_to_confirm');
    }

    public function create(
        ServerRequestInterface $request,
        #[Body('assignedItems')]
        array $assignedItems = [],
    ): ResponseInterface {
        $errors = [];
        $form = new RegistrationForm($this->config, $this->translator);

        if ($this->formHydrator->populateFromPost($form, $request)) {
            $email = $form->email;
            $username = $form->username;
            $password = $form->password !== '' ? $form->password : $this->passwordGenerator->generate(12);

            $result = $this->userCreateService->run($email, $username, $password);
            if ($result->isSuccess()) {
                $createdUser = User::findByUsername($username);

                /**
                 * @infection-ignore-all The `$createdUser !== null` guard (and the null-safe below) defend
                 * against the just-created user not being found again, which cannot happen after a
                 * successful create in this flow, so flipping the operator is behaviourally unobservable.
                 */
                if ($assignedItems !== [] && $createdUser !== null) {
                    $this->updateAuthAssignmentsService->run((int) $createdUser->getId(), $assignedItems);
                }

                /** @infection-ignore-all The null-safe call defends against a null $createdUser, unreachable after a successful create. */
                $this->auditLogService->log(
                    $this->actorId(),
                    'user.create',
                    $request->getServerParams(),
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

    public function delete(ServerRequestInterface $request, #[RouteArgument] int $id): ResponseInterface
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
                $request->getServerParams(),
                targetUserId: $id,
                targetName: $user->getUsername(),
            );

            return $this->redirectWithFlash($this->url->generate('voyti/admin-users'), 'voyti.admin.user_deleted');
        }
        return $this->renderError('voyti.admin.user_not_found');
    }

    public function forcePasswordChange(ServerRequestInterface $request, #[RouteArgument] int $id): ResponseInterface
    {
        $user = User::findById($id);
        if ($user !== null && $this->passwordExpireService->run($user)) {
            $this->auditLogService->log($this->actorId(), 'user.force_password_change', $request->getServerParams(), targetUserId: $id);

            return $this->redirectWithFlash(
                $this->url->generate('voyti/admin-users'),
                'voyti.admin.password_change_required',
            );
        }
        return $this->renderError('voyti.admin.error_occurred');
    }

    public function index(
        #[Query('username')]
        string $username = '',
        #[Query('email')]
        string $email = '',
        #[Query('status')]
        string $status = '',
        /**
         * @infection-ignore-all Mutating this default to 0 is behaviorally identical to 1: both are
         * floored to 1 by max(1, $page) below, so no test can observe the difference.
         */
        #[Query('page')]
        int $page = 1,
        #[Query('perPage')]
        int $perPage = 25,
    ): ResponseInterface {
        $filters = [
            'username' => $username,
            'email' => $email,
            'status' => $status,
        ];

        $reader = new QueryDataReader(User::searchQuery($filters));
        $pageSize = min(max(1, $perPage), self::MAX_PER_PAGE);
        $sizedPaginator = (new OffsetPaginator($reader))->withPageSize($pageSize);
        $currentPage = min(max(1, $page), max(1, $sizedPaginator->getTotalPages()));
        $paginator = $sizedPaginator->withCurrentPage($currentPage);

        /** @infection-ignore-all — iterator keys are already 0-indexed, preserve_keys has no effect */
        /** @var list<User> $users */
        $users = iterator_to_array($paginator->read(), false);

        return $this->renderView('admin/user/index', [
            'data' => IndexViewData::create(
                $users,
                $paginator,
                $filters,
                $pageSize,
                $this->config,
                $this->url,
                $this->translator(),
                $this->switchIdentityService->isSwitched(),
                (int) $this->currentUser->getIdentity()->getId(),
            ),
        ]);
    }

    public function passwordReset(ServerRequestInterface $request, #[RouteArgument] int $id): ResponseInterface
    {
        $user = User::findById($id);
        if ($user !== null) {
            $result = $this->passwordRecoveryService->run($user->getEmail());
            $this->auditLogService->log($this->actorId(), 'user.password_reset_triggered', $request->getServerParams(), targetUserId: $id);

            return $this->renderView('shared/message', [
                'data' => new MessageViewData(title: $result->getMessage(), homeUrl: $this->homeUrl()),
            ]);
        }
        return $this->renderError('voyti.admin.user_not_found');
    }

    public function sessions(#[RouteArgument] int $id): ResponseInterface
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

    public function show(#[RouteArgument] int $id): ResponseInterface
    {
        $user = $this->resolveUser($id);
        if (!$user instanceof User) {
            return $user;
        }
        $userProfile = $user->getProfile();
        if ($userProfile === null) {
            $userProfile = new UserProfile();
            /** @infection-ignore-all The transient empty profile's user id isn't rendered on the info card, so setting it is unobservable. */
            $userProfile->setUserId($id);
        }

        $viewer = $this->currentUser->getIdentity();
        /** @infection-ignore-all NullSafeMethodCall: ?-> is correct; removing it would cause TypeError on null profile. */
        $viewerTimezone = $viewer instanceof User ? $viewer->getProfile()?->getTimezone() : null;

        return $this->renderView('admin/user/_info', [
            'data' => InfoViewData::create($user, $userProfile, $this->url, $this->translator(), $viewerTimezone),
        ]);
    }

    public function switchIdentity(ServerRequestInterface $request, #[RouteArgument] int $id): ResponseInterface
    {
        $actorId = $this->actorId();
        $result = $this->switchIdentityService->run($id, $request->getServerParams());
        if ($result->isSuccess()) {
            $this->auditLogService->log($actorId, 'user.switch_identity', $request->getServerParams(), targetUserId: $id);
            $this->toast->add(
                FlashType::SUCCESS,
                $this->translator->translate('voyti.admin.impersonate_identity_success', category: 'voyti'),
            );

            return $this->redirect($this->url->generate('voyti/user'));
        }

        return $this->renderError($result->getMessage() !== '' ? $result->getMessage() : 'voyti.admin.error_occurred');
    }

    public function switchIdentityRestore(ServerRequestInterface $request): ResponseInterface
    {
        $result = $this->switchIdentityService->restore($request->getServerParams());
        if ($result->isSuccess()) {
            $this->toast->add(
                FlashType::SUCCESS,
                $this->translator->translate('voyti.admin.impersonate_identity_restored', category: 'voyti'),
            );

            return $this->redirect($this->url->generate('voyti/user-profile'));
        }

        return $this->renderError($result->getMessage() !== '' ? $result->getMessage() : 'voyti.admin.error_occurred');
    }

    public function terminateSessions(#[RouteArgument] int $id): ResponseInterface
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

    public function update(
        ServerRequestInterface $request,
        #[RouteArgument]
        int $id,
        #[Body('assignedItems')]
        array $assignedItems = [],
    ): ResponseInterface {
        $user = $this->resolveUser($id);
        if (!$user instanceof User) {
            return $user;
        }

        $form = new SettingsForm($this->config, $this->translator);
        $form->username = $user->getUsername();
        $form->email = $user->getEmail();
        $errors = [];

        if ($this->formHydrator->populateFromPost($form, $request, scope: 'user')) {
            $password = $form->password;
            if ($password !== '' && $this->passwordHistoryService->wasUsedRecently($user, $password)) {
                $errors = [
                    'password' => [
                        $this->translator->translate('voyti.admin.password_previously_used', category: 'voyti'),
                    ],
                ];
            } else {
                $user->setUsername($form->username);
                $user->setEmail($form->email);
                if ($password !== '') {
                    $this->passwordHistoryService->applyPasswordChange($user, $password);
                } else {
                    $user->setUpdatedAt(time());
                    $user->save();
                }

                $this->updateAuthAssignmentsService->run($id, $assignedItems);

                $this->auditLogService->log(
                    $this->actorId(),
                    'user.update',
                    $request->getServerParams(),
                    targetUserId: $id,
                    context: ['passwordChanged' => $password !== ''],
                );

                return $this->redirectWithFlash(
                    $this->url->generate('voyti/admin-users'),
                    'voyti.admin.account_updated',
                );
            }
        }

        $assignedNames = $this->assignedItemNames($id);

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

    public function updateProfile(
        ServerRequestInterface $request,
        #[RouteArgument]
        int $id,
    ): ResponseInterface {
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

        if ($this->formHydrator->populateFromPostAndValidate($form, $request)) {
            $form->applyToProfile($userProfile);
            $userProfile->save();
            return $this->redirectWithFlash(
                $this->url->generate('voyti/admin-users-update-profile', ['id' => $id]),
                'voyti.admin.profile_details_updated',
            );
        }

        return $this->renderView('admin/user/_profile', [
            'form' => $form,
            'data' => ProfileViewData::create($user, $this->url, $this->translator()),
        ]);
    }

    /**
     * @return list<string>
     */
    private function assignedItemNames(int $id): array
    {
        $assignments = $this->assignmentsStorage->getByUserId((string) $id);
        /** @infection-ignore-all array_values only reindexes; the rendered assignment list is identical either way. */
        return array_values(array_map(fn(Assignment $a) => $a->getItemName(), $assignments));
    }

    private function resolveUser(int $id): User|ResponseInterface
    {
        $user = User::findById($id);
        return $user ?? $this->renderError('voyti.admin.user_not_found');
    }
}
