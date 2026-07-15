<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Controller\Admin\User;

use DateTimeImmutable;
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
use YiiRocks\Voyti\Model\UserSessionHistory;
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
    ) {
    }

    public function assignments(ServerRequestInterface $request, int $id): ResponseInterface
    {
        $user = User::findById($id);
        if ($user === null) {
            return $this->renderError('voyti.admin.user_not_found');
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
        $assignedNames = array_map(fn (Assignment $a) => $a->getItemName(), $assignments);
        $available = $this->authHelper->getUnassignedItems($id);

        return $this->renderView('admin/user/_assignments', [
            'user' => $user,
            'assignments' => $assignedNames,
            'available' => $available,
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
        $model = new RegistrationForm($this->config, $this->translator);

        if ($request->getMethod() === Method::POST) {
            $body = $this->parsedBody($request);
            $this->hydrator->hydrate($model, $this->formData($body, $model->getFormName()));
            $email = $model->email;
            $username = $model->username;
            $password = $model->password !== '' ? $model->password : $this->passwordGenerator->generate(12);

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
            $errors = $result->getErrors();
        }

        $allItems = $this->itemsStorage->getAll();

        return $this->renderView('admin/user/create', [
            'model' => $model,
            'errors' => $errors,
            'allItems' => $allItems,
            'assignedItems' => [],
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
            $this->auditLogService->log($this->actorId(), 'user.delete', targetUserId: $id, targetName: $user->getUsername());

            return $this->redirectWithFlash($this->url->generate('voyti/admin-users'), 'voyti.admin.user_deleted');
        }
        return $this->renderError('voyti.admin.user_not_found');
    }

    public function forcePasswordChange(int $id): ResponseInterface
    {
        $user = User::findById($id);
        if ($user !== null && $this->passwordExpireService->run($user)) {
            $this->auditLogService->log($this->actorId(), 'user.force_password_change', targetUserId: $id);

            return $this->redirectWithFlash($this->url->generate('voyti/admin-users'), 'voyti.admin.password_change_required');
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

        return $this->renderView('admin/user/index', [
            'users' => iterator_to_array($paginator->read(), false),
            'paginator' => $paginator,
            'config' => $this->config,
            'filters' => $filters,
            'flash' => $this->flash,
            'isSwitched' => $this->switchIdentityService->isSwitched(),
            'originalUser' => $this->switchIdentityService->getOriginalUser(),
            'currentUserId' => (int) $this->currentUser->getIdentity()->getId(),
        ]);
    }

    public function passwordReset(int $id): ResponseInterface
    {
        $user = User::findById($id);
        if ($user !== null) {
            $result = $this->passwordRecoveryService->run($user->getEmail());
            $this->auditLogService->log($this->actorId(), 'user.password_reset_triggered', targetUserId: $id);

            return $this->renderView('shared/message', ['title' => $result->getMessage()]);
        }
        return $this->renderError('voyti.admin.user_not_found');
    }

    public function sessionHistory(int $id): ResponseInterface
    {
        $user = User::findById($id);
        if ($user === null) {
            return $this->renderError('voyti.admin.user_not_found');
        }

        $sessions = UserSessionHistory::findByUserId($id);
        return $this->renderView('admin/user/_session-history', [
            'user' => $user,
            'sessions' => $sessions,
            'flash' => $this->flash,
        ]);
    }

    public function show(int $id): ResponseInterface
    {
        $user = User::findById($id);
        if ($user === null) {
            return $this->renderError('voyti.admin.user_not_found');
        }
        return $this->renderView('admin/user/_info', [
            'user' => $user,
            'userProfile' => $user->getProfile(),
        ]);
    }

    public function switchIdentity(int $id): ResponseInterface
    {
        $result = $this->switchIdentityService->run($id);
        if ($result->isSuccess()) {
            $this->auditLogService->log($this->actorId(), 'user.switch_identity', targetUserId: $id);
            $this->flash->set(FlashType::SUCCESS, $this->translator->translate('voyti.admin.switch_identity_success', category: 'voyti'));

            return $this->redirect($this->url->generate('voyti/profile-update'));
        }

        return $this->renderError($result->getMessage() !== '' ? $result->getMessage() : 'voyti.admin.error_occurred');
    }

    public function switchIdentityRestore(): ResponseInterface
    {
        $result = $this->switchIdentityService->restore();
        if ($result->isSuccess()) {
            $this->flash->set(FlashType::SUCCESS, $this->translator->translate('voyti.admin.switch_identity_restored', category: 'voyti'));

            return $this->redirect($this->url->generate('voyti/profile-update'));
        }

        return $this->renderError($result->getMessage() !== '' ? $result->getMessage() : 'voyti.admin.error_occurred');
    }

    public function terminateSessions(int $id): ResponseInterface
    {
        $user = User::findById($id);
        if ($user === null) {
            return $this->renderError('voyti.admin.user_not_found');
        }

        $sessions = UserSessionHistory::findByUserId($id);
        foreach ($sessions as $session) {
            $session->delete();
        }

        return $this->redirectWithFlash(
            $this->url->generate('voyti/admin-users-session-history', ['id' => $id]),
            'voyti.admin.sessions_terminated',
        );
    }

    public function update(ServerRequestInterface $request, int $id): ResponseInterface
    {
        $user = User::findById($id);
        if ($user === null) {
            return $this->renderError('voyti.admin.user_not_found');
        }

        $model = new SettingsForm($this->config, $this->translator);
        $model->username = $user->getUsername();
        $model->email = $user->getEmail();
        $errors = [];

        if ($request->getMethod() === Method::POST) {
            $body = $this->parsedBody($request);
            /** @var mixed $rawUserData */
            $rawUserData = $body['user'] ?? null;
            $userData = is_array($rawUserData) ? $rawUserData : [];
            $password = $this->stringValue($userData, 'password');

            if ($password !== '' && $this->passwordHistoryService->wasUsedRecently($user, $password)) {
                $errors = ['password' => [$this->translator->translate('voyti.admin.password_previously_used', category: 'voyti')]];
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

                return $this->redirectWithFlash($this->url->generate('voyti/admin-users'), 'voyti.admin.account_updated');
            }
        }

        $assignments = $this->assignmentsStorage->getByUserId((string) $id);
        $assignedNames = array_map(fn (Assignment $a) => $a->getItemName(), $assignments);
        $allItems = $this->itemsStorage->getAll();

        return $this->renderView('admin/user/_account', [
            'user' => $user,
            'model' => $model,
            'errors' => $errors,
            'allItems' => $allItems,
            'assignedItems' => $assignedNames,
        ]);
    }

    public function updateProfile(ServerRequestInterface $request, int $id): ResponseInterface
    {
        $user = User::findById($id);
        if ($user === null) {
            return $this->renderError('voyti.admin.user_not_found');
        }

        $userProfile = $user->getProfile();
        if ($userProfile === null) {
            $userProfile = new UserProfile();
            $userProfile->setUserId($id);
        }

        $model = new UserProfileForm($this->translator);
        $model->name = $userProfile->getName() ?? '';
        $model->publicEmail = $userProfile->getPublicEmail() ?? '';
        $model->gravatarEmail = $userProfile->getGravatarEmail() ?? '';
        $model->location = $userProfile->getLocation() ?? '';
        $model->website = $userProfile->getWebsite() ?? '';
        $model->timezone = $userProfile->getTimezone() ?? '';
        $model->bio = $userProfile->getBio() ?? '';
        $model->birthday = $userProfile->getBirthday()?->format('Y-m-d') ?? '';

        if ($request->getMethod() === Method::POST) {
            $body = $this->parsedBody($request);
            $this->hydrator->hydrate($model, $this->formData($body, $model->getFormName()));
            $result = $this->validator->validate($model);
            $model->processValidationResult($result);
            if ($result->isValid()) {
                $userProfile->setName($model->name !== '' ? $model->name : null);
                $userProfile->setPublicEmail($model->publicEmail !== '' ? $model->publicEmail : null);
                $userProfile->setGravatarEmail($model->gravatarEmail !== '' ? $model->gravatarEmail : null);
                $userProfile->setLocation($model->location !== '' ? $model->location : null);
                $userProfile->setWebsite($model->website !== '' ? $model->website : null);
                $userProfile->setTimezone($model->timezone !== '' ? $model->timezone : null);
                $userProfile->setBio($model->bio !== '' ? $model->bio : null);
                $userProfile->setBirthday($model->birthday !== '' ? new DateTimeImmutable($model->birthday) : null);
                $userProfile->save();
                return $this->redirectWithFlash(
                    $this->url->generate('voyti/admin-users-update-profile', ['id' => $id]),
                    'voyti.admin.profile_details_updated',
                );
            }
        }

        return $this->renderView('admin/user/_profile', ['user' => $user, 'model' => $model, 'flash' => $this->flash]);
    }

    protected function viewPath(): string
    {
        return $this->config->viewPath;
    }

}
