<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Controller;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use YiiRocks\Voyti\Entity\UserProfile;
use YiiRocks\Voyti\Form\Settings\UserProfileForm;
use YiiRocks\Voyti\Helper\AuthHelper;
use YiiRocks\Voyti\ModuleConfig;
use Yiisoft\Security\PasswordHasher;
use Yiisoft\Security\Random;
use YiiRocks\Voyti\Repository\UserProfileRepository;
use YiiRocks\Voyti\Repository\UserSessionHistoryRepository;
use YiiRocks\Voyti\Repository\UserRepository;
use YiiRocks\Voyti\Service\Password\ExpireService;
use YiiRocks\Voyti\Service\Password\RecoveryService;
use YiiRocks\Voyti\Service\Rbac\UpdateAssignmentsService;
use YiiRocks\Voyti\Service\SwitchIdentityService;
use YiiRocks\Voyti\Service\User\BlockService;
use YiiRocks\Voyti\Service\User\ConfirmationService;
use YiiRocks\Voyti\Service\User\CreateService;
use Yiisoft\Auth\IdentityInterface;
use Yiisoft\Http\Method;
use Yiisoft\Rbac\Assignment;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\Validator\ValidatorInterface;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

final class AdminController
{
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
    ) {
    }

    public function assignments(ServerRequestInterface $request, int $id): ResponseInterface
    {
        $user = $this->userRepository->findById($id);
        if ($user === null) {
            return $this->renderError('voyti.admin.user_not_found');
        }

        if ($request->getMethod() === Method::POST) {
            $body = $request->getParsedBody();
            $items = $body['items'] ?? [];
            $this->updateAuthAssignmentsService->run($id, $items);
        }

        $assignments = $this->authHelper->getAssignments($id);
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

        if ($request->getMethod() === Method::POST) {
            $body = $request->getParsedBody();
            $email = $body['email'] ?? '';
            $username = $body['username'] ?? '';
            $password = $body['password'] ?? Random::string(12);

            $result = $this->userCreateService->run($email, $username, $password);
            if ($result->isSuccess()) {
                return $this->renderSuccess('voyti.admin.user_created');
            }
            $errors = $result->getErrors();
        }

        return $this->renderView('admin/create', ['errors' => $errors]);
    }

    public function delete(ServerRequestInterface $request, int $id): ResponseInterface
    {
        $identity = $request->getAttribute(IdentityInterface::class);
        if ($identity !== null && $id === (int) $identity->getId()) {
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
        $queryParams = $request->getQueryParams();
        $filters = [
            'username' => $queryParams['username'] ?? '',
            'email' => $queryParams['email'] ?? '',
            'status' => $queryParams['status'] ?? '',
            'page' => (int)($queryParams['page'] ?? 1),
        ];

        $users = $this->userRepository->search($filters);
        $total = $this->userRepository->countByFilters($filters);
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
        return $this->renderView('admin/_info', ['user' => $user, 'config' => $this->config]);
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

        if ($request->getMethod() === Method::POST) {
            $body = $request->getParsedBody();
            $user->setUsername($body['username'] ?? $user->getUsername());
            $user->setEmail($body['email'] ?? $user->getEmail());
            if (!empty($body['password'])) {
                $user->setPasswordHash($this->passwordHasher->hash($body['password']));
                $user->setPasswordChangedAt(time());
            }
            $user->setUpdatedAt(time());
            $user->save();
            return $this->renderSuccess('voyti.admin.account_details_updated');
        }

        return $this->renderView('admin/_account', ['user' => $user, 'config' => $this->config]);
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
            $body = $request->getParsedBody();
            $model->load($body, 'userProfile');
            if ($model->isValidated() && $model->isValid()) {
                $userProfile->setName($model->name !== '' ? $model->name : null);
                $userProfile->setBio($model->bio !== '' ? $model->bio : null);
                $userProfile->setPublicEmail($model->publicEmail !== '' ? $model->publicEmail : null);
                $userProfile->save();
                return $this->renderSuccess('voyti.admin.profile_details_updated');
            }
        }

        return $this->renderView('admin/_profile', ['user' => $user, 'model' => $model, 'config' => $this->config]);
    }
}
