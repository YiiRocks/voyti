<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Controller;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use YiiRocks\Voyti\Entity\UserProfile;
use YiiRocks\Voyti\Form\Auth\RegistrationForm;
use YiiRocks\Voyti\Form\Settings\SettingsForm;
use YiiRocks\Voyti\Form\Settings\UserProfileForm;
use YiiRocks\Voyti\Helper\AuthHelper;
use YiiRocks\Voyti\Helper\InputDataTrait;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Repository\UserProfileRepository;
use YiiRocks\Voyti\Repository\UserRepository;
use YiiRocks\Voyti\Repository\UserSessionHistoryRepository;
use YiiRocks\Voyti\Service\Password\ExpireService;
use YiiRocks\Voyti\Service\Password\RecoveryService;
use YiiRocks\Voyti\Service\Rbac\UpdateAssignmentsService;
use YiiRocks\Voyti\Service\SwitchIdentityService;
use YiiRocks\Voyti\Service\User\BlockService;
use YiiRocks\Voyti\Service\User\ConfirmationService;
use YiiRocks\Voyti\Service\User\CreateService;
use Yiisoft\Http\Method;
use Yiisoft\Hydrator\HydratorInterface;
use Yiisoft\Rbac\Assignment;
use Yiisoft\Rbac\AssignmentsStorageInterface;
use Yiisoft\Rbac\ItemsStorageInterface;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Security\PasswordHasher;
use Yiisoft\Security\Random;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\User\CurrentUser;
use Yiisoft\Validator\ValidatorInterface;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

final class AdminController
{
    use InputDataTrait;
    use RenderTrait;

    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly WebViewRenderer $viewRenderer,
        private readonly UserRepository $userRepository,
        private readonly UserProfileRepository $userProfileRepository,
        private readonly CreateService $userCreateService,
        private readonly BlockService $userBlockService,
        private readonly ConfirmationService $userConfirmationService,
        private readonly RecoveryService $passwordRecoveryService,
        private readonly ExpireService $passwordExpireService,
        private readonly SwitchIdentityService $switchIdentityService,
        private readonly UpdateAssignmentsService $updateAuthAssignmentsService,
        private readonly UserSessionHistoryRepository $userSessionHistoryRepository,
        private readonly AuthHelper $authHelper,
        private readonly PasswordHasher $passwordHasher,
        private readonly ValidatorInterface $validator,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly UrlGeneratorInterface $url,
        private readonly ModuleConfig $config,
        private readonly HydratorInterface $hydrator,
        private readonly CurrentUser $currentUser,
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly ItemsStorageInterface $itemsStorage,
        private readonly AssignmentsStorageInterface $assignmentsStorage,
    ) {
    }

    public function assignments(ServerRequestInterface $request, int $id): ResponseInterface
    {
        $user = $this->userRepository->findById($id);
        if ($user === null) {
            return $this->renderError('voyti.admin.user_not_found');
        }

        if ($request->getMethod() === Method::POST) {
            $body = $this->parsedBody($request);
            $items = $body['items'] ?? [];
            $items = is_array($items) ? array_values(array_filter($items, 'is_string')) : [];
            $this->updateAuthAssignmentsService->run($id, $items);
        }

        $assignments = $this->assignmentsStorage->getByUserId((string) $id);
        $assignedNames = array_map(fn (Assignment $a) => $a->getItemName(), $assignments);
        $available = $this->authHelper->getUnassignedItems($id);

        return $this->renderView('admin/_assignments', [
            'user' => $user,
            'config' => $this->config,
            'assignments' => $assignedNames,
            'available' => $available,
        ]);
    }

    public function block(int $id): ResponseInterface
    {
        $user = $this->userRepository->findById($id);
        if ($user !== null && $this->userBlockService->run($user)) {
            return $this->renderSuccess('voyti.admin.block_status_updated');
        }
        return $this->renderError('voyti.admin.unable_to_update_block');
    }

    public function confirm(int $id): ResponseInterface
    {
        $user = $this->userRepository->findById($id);
        if ($user !== null && $this->userConfirmationService->run($user)) {
            return $this->renderSuccess('voyti.admin.user_confirmed');
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
            $password = $model->password !== '' ? $model->password : Random::string(12);

            $result = $this->userCreateService->run($email, $username, $password);
            if ($result->isSuccess()) {
                $items = $body['assignedItems'] ?? [];
                $items = is_array($items) ? array_values(array_filter($items, 'is_string')) : [];
                if ($items !== []) {
                    $user = $this->userRepository->findByUsername($username);
                    if ($user !== null) {
                        $this->updateAuthAssignmentsService->run((int) $user->getId(), $items);
                    }
                }
                return $this->responseFactory->createResponse(302)
                    ->withHeader('Location', $this->url->generate('voyti/admin'));
            }
            $errors = $result->getErrors();
        }

        $allItems = $this->itemsStorage->getAll();

        return $this->renderView('admin/create', [
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
        $user = $this->userRepository->findById($id);
        if ($user !== null) {
            $this->userRepository->delete($user);
            return $this->renderSuccess('voyti.admin.user_deleted');
        }
        return $this->renderError('voyti.admin.user_not_found');
    }

    public function forcePasswordChange(int $id): ResponseInterface
    {
        $user = $this->userRepository->findById($id);
        if ($user !== null && $this->passwordExpireService->run($user)) {
            return $this->renderSuccess('voyti.admin.password_change_required');
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
            'page' => (int) ($queryParams['page'] ?? 1),
        ];

        $users = $this->userRepository->search($filters);
        $total = (int) $this->userRepository->countByFilters($filters);
        $limit = 50;
        $totalPages = max(1, (int)ceil($total / $limit));
        $currentPage = max(1, $filters['page']);

        return $this->renderView('admin/index', [
            'users' => $users,
            'config' => $this->config,
            'filters' => $filters,
            'totalPages' => $totalPages,
            'currentPage' => $currentPage,
        ]);
    }

    public function info(int $id): ResponseInterface
    {
        $user = $this->userRepository->findById($id);
        if ($user === null) {
            return $this->renderError('voyti.admin.user_not_found');
        }
        return $this->renderView('admin/_info', [
            'user' => $user,
            'userProfile' => $user->getProfile(),
            'config' => $this->config,
        ]);
    }

    public function passwordReset(int $id): ResponseInterface
    {
        $user = $this->userRepository->findById($id);
        if ($user !== null) {
            $result = $this->passwordRecoveryService->run($user->getEmail());
            return $this->renderView('shared/message', ['title' => $result->getMessage()]);
        }
        return $this->renderError('voyti.admin.user_not_found');
    }

    public function switchIdentity(int $id): ResponseInterface
    {
        $result = $this->switchIdentityService->run($id);
        return $this->renderView('shared/message', ['title' => $result->getMessage()]);
    }

    public function terminateSessions(int $id): ResponseInterface
    {
        $user = $this->userRepository->findById($id);
        if ($user === null) {
            return $this->renderError('voyti.admin.user_not_found');
        }

        $sessions = $this->userSessionHistoryRepository->findByUserId($id);
        foreach ($sessions as $session) {
            $session->delete();
        }

        return $this->renderSuccess('voyti.admin.sessions_terminated');
    }

    public function update(ServerRequestInterface $request, int $id): ResponseInterface
    {
        $user = $this->userRepository->findById($id);
        if ($user === null) {
            return $this->renderError('voyti.admin.user_not_found');
        }

        $model = new SettingsForm($this->translator);
        $model->username = $user->getUsername();
        $model->email = $user->getEmail();

        if ($request->getMethod() === Method::POST) {
            $body = $this->parsedBody($request);
            $user->setUsername($this->stringValue($body, 'username', $user->getUsername()));
            $user->setEmail($this->stringValue($body, 'email', $user->getEmail()));
            $password = $this->stringValue($body, 'password');
            if ($password !== '') {
                $user->setPasswordHash($this->passwordHasher->hash($password));
                $user->setPasswordChangedAt(time());
            }
            $user->setUpdatedAt(time());
            $user->save();

            $items = $body['assignedItems'] ?? [];
            $items = is_array($items) ? array_values(array_filter($items, 'is_string')) : [];
            $this->updateAuthAssignmentsService->run($id, $items);

            return $this->responseFactory->createResponse(302)
                ->withHeader('Location', $this->url->generate('voyti/admin'));
        }

        $assignments = $this->assignmentsStorage->getByUserId((string) $id);
        $assignedNames = array_map(fn (Assignment $a) => $a->getItemName(), $assignments);
        $allItems = $this->itemsStorage->getAll();

        return $this->renderView('admin/_account', [
            'user' => $user,
            'model' => $model,
            'errors' => [],
            'config' => $this->config,
            'allItems' => $allItems,
            'assignedItems' => $assignedNames,
        ]);
    }

    public function updateProfile(ServerRequestInterface $request, int $id): ResponseInterface
    {
        $user = $this->userRepository->findById($id);
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
        $model->bio = $userProfile->getBio() ?? '';
        $model->publicEmail = $userProfile->getPublicEmail() ?? '';

        if ($request->getMethod() === Method::POST) {
            $body = $this->parsedBody($request);
            $this->hydrator->hydrate($model, $this->formData($body, $model->getFormName()));
            $result = $this->validator->validate($model);
            $model->processValidationResult($result);
            if ($result->isValid()) {
                $userProfile->setName($model->name !== '' ? $model->name : null);
                $userProfile->setBio($model->bio !== '' ? $model->bio : null);
                $userProfile->setPublicEmail($model->publicEmail !== '' ? $model->publicEmail : null);
                $userProfile->save();
                return $this->renderSuccess('voyti.admin.profile_details_updated');
            }
        }

        return $this->renderView('admin/_profile', ['user' => $user, 'model' => $model, 'config' => $this->config]);
    }

    public function userSessionHistory(int $id): ResponseInterface
    {
        $user = $this->userRepository->findById($id);
        if ($user === null) {
            return $this->renderError('voyti.admin.user_not_found');
        }

        $sessions = $this->userSessionHistoryRepository->findByUserId($id);
        return $this->renderView('admin/_session-history', [
            'user' => $user,
            'sessions' => $sessions,
            'config' => $this->config,
        ]);
    }

    protected function viewPath(): string
    {
        return $this->config->viewPath;
    }
}
