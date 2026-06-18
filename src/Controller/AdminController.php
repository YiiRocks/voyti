<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Controller;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\Aliases\Aliases;
use Yiisoft\Http\Method;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\Validator\ValidatorInterface;
use Yiisoft\View\ViewInterface;
use Yiisoft\Router\UrlGeneratorInterface;
use YiiRocks\Voyti\Helper\AuthHelper;
use YiiRocks\Voyti\Helper\SecurityHelper;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\RenderTrait;
use YiiRocks\Voyti\Repository\UserRepository;
use YiiRocks\Voyti\Repository\ProfileRepository;
use YiiRocks\Voyti\Service\UserCreateService;
use YiiRocks\Voyti\Service\UserBlockService;
use YiiRocks\Voyti\Service\UserConfirmationService;
use YiiRocks\Voyti\Service\PasswordRecoveryService;
use YiiRocks\Voyti\Service\PasswordExpireService;
use YiiRocks\Voyti\Service\SwitchIdentityService;
use YiiRocks\Voyti\Service\UpdateAuthAssignmentsService;
use YiiRocks\Voyti\Repository\SessionHistoryRepository;
use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\Event\UserEvent;

final class AdminController
{
    use RenderTrait;

    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly ViewInterface $view,
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly Aliases $aliases,
        private readonly UserRepository $userRepository,
        private readonly ProfileRepository $profileRepository,
        private readonly UserCreateService $userCreateService,
        private readonly UserBlockService $userBlockService,
        private readonly UserConfirmationService $userConfirmationService,
        private readonly PasswordRecoveryService $passwordRecoveryService,
        private readonly PasswordExpireService $passwordExpireService,
        private readonly SwitchIdentityService $switchIdentityService,
        private readonly UpdateAuthAssignmentsService $updateAuthAssignmentsService,
        private readonly SessionHistoryRepository $sessionHistoryRepository,
        private readonly AuthHelper $authHelper,
        private readonly SecurityHelper $securityHelper,
        private readonly ValidatorInterface $validator,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly UrlGeneratorInterface $url,
        private readonly ModuleConfig $config,
    ) {
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
        $total = $this->userRepository->count($filters);
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

    public function create(ServerRequestInterface $request): ResponseInterface
    {
        $errors = [];

        if ($request->getMethod() === Method::POST) {
            $body = $request->getParsedBody();
            $email = $body['email'] ?? '';
            $username = $body['username'] ?? '';
            $password = $body['password'] ?? $this->securityHelper->generateRandomString(12);

            $result = $this->userCreateService->run($email, $username, $password);
            if ($result->isSuccess()) {
                return $this->renderView('shared/message', ['title' => $this->translator->translate('voyti.admin.user_created'), 'translator' => $this->translator]);
            }
            $errors = $result->getErrors();
        }

        return $this->renderView('admin/create', ['errors' => $errors]);
    }

    public function update(ServerRequestInterface $request, int $id): ResponseInterface
    {
        $user = $this->userRepository->findById($id);
        if ($user === null) {
            return $this->renderView('shared/message', ['title' => $this->translator->translate('voyti.admin.user_not_found'), 'translator' => $this->translator]);
        }

        if ($request->getMethod() === Method::POST) {
            $body = $request->getParsedBody();
            $user->setUsername($body['username'] ?? $user->getUsername());
            $user->setEmail($body['email'] ?? $user->getEmail());
            if (!empty($body['password'])) {
                $user->setPasswordHash($this->securityHelper->hashPassword($body['password'], $this->config->blowfishCost));
                $user->setPasswordChangedAt(time());
            }
            $user->setUpdatedAt(time());
            $user->save();
            return $this->renderView('shared/message', ['title' => $this->translator->translate('voyti.admin.account_details_updated'), 'translator' => $this->translator]);
        }

        return $this->renderView('admin/_account', ['user' => $user, 'config' => $this->config]);
    }

    public function updateProfile(ServerRequestInterface $request, int $id): ResponseInterface
    {
        $user = $this->userRepository->findById($id);
        if ($user === null) {
            return $this->renderView('shared/message', ['title' => $this->translator->translate('voyti.admin.user_not_found'), 'translator' => $this->translator]);
        }

        $profile = $user->getProfile();
        if ($profile === null) {
            $profile = new \YiiRocks\Voyti\Entity\Profile();
            $profile->setUserId($id);
        }

        if ($request->getMethod() === Method::POST) {
            $body = $request->getParsedBody();
            $profile->load($body, 'profile');
            $profile->save();
            return $this->renderView('shared/message', ['title' => $this->translator->translate('voyti.admin.profile_details_updated'), 'translator' => $this->translator]);
        }

        return $this->renderView('admin/_profile', ['user' => $user, 'profile' => $profile, 'config' => $this->config]);
    }

    public function info(int $id): ResponseInterface
    {
        $user = $this->userRepository->findById($id);
        if ($user === null) {
            return $this->renderView('shared/message', ['title' => $this->translator->translate('voyti.admin.user_not_found'), 'translator' => $this->translator]);
        }
        return $this->renderView('admin/_info', ['user' => $user, 'config' => $this->config]);
    }

    public function assignments(ServerRequestInterface $request, int $id): ResponseInterface
    {
        $user = $this->userRepository->findById($id);
        if ($user === null) {
            return $this->renderView('shared/message', ['title' => $this->translator->translate('voyti.admin.user_not_found'), 'translator' => $this->translator]);
        }

        if ($request->getMethod() === Method::POST) {
            $body = $request->getParsedBody();
            $items = $body['items'] ?? [];
            $this->updateAuthAssignmentsService->run($id, $items);
        }

        $assignments = $this->authHelper->getAssignments($id);
        $assignedNames = array_map(fn(\Yiisoft\Rbac\Assignment $a) => $a->getItemName(), $assignments);
        $available = $this->authHelper->getUnassignedItems($id);

        return $this->renderView('admin/_assignments', [
            'user' => $user,
            'config' => $this->config,
            'assignments' => $assignedNames,
            'available' => $available,
        ]);
    }

    public function confirm(int $id): ResponseInterface
    {
        $user = $this->userRepository->findById($id);
        if ($user !== null && $this->userConfirmationService->run($user)) {
            return $this->renderView('shared/message', ['title' => $this->translator->translate('voyti.admin.user_confirmed'), 'translator' => $this->translator]);
        }
        return $this->renderView('shared/message', ['title' => $this->translator->translate('voyti.admin.unable_to_confirm'), 'translator' => $this->translator]);
    }

    public function delete(ServerRequestInterface $request, int $id): ResponseInterface
    {
        $identity = $request->getAttribute(\Yiisoft\Auth\IdentityInterface::class);
        if ($identity !== null && $id === (int) $identity->getId()) {
            return $this->renderView('shared/message', ['title' => $this->translator->translate('voyti.admin.cannot_delete_self'), 'translator' => $this->translator]);
        }
        $user = $this->userRepository->findById($id);
        if ($user !== null) {
            $this->userRepository->delete($user);
            return $this->renderView('shared/message', ['title' => $this->translator->translate('voyti.admin.user_deleted'), 'translator' => $this->translator]);
        }
        return $this->renderView('shared/message', ['title' => $this->translator->translate('voyti.admin.user_not_found'), 'translator' => $this->translator]);
    }

    public function block(int $id): ResponseInterface
    {
        $user = $this->userRepository->findById($id);
        if ($user !== null && $this->userBlockService->run($user)) {
            return $this->renderView('shared/message', ['title' => $this->translator->translate('voyti.admin.block_status_updated'), 'translator' => $this->translator]);
        }
        return $this->renderView('shared/message', ['title' => $this->translator->translate('voyti.admin.unable_to_update_block'), 'translator' => $this->translator]);
    }

    public function switchIdentity(int $id): ResponseInterface
    {
        $result = $this->switchIdentityService->run($id);
        return $this->renderView('shared/message', ['title' => $result->getMessage()]);
    }

    public function passwordReset(int $id): ResponseInterface
    {
        $user = $this->userRepository->findById($id);
        if ($user !== null) {
            $result = $this->passwordRecoveryService->run($user->getEmail());
            return $this->renderView('shared/message', ['title' => $result->getMessage()]);
        }
        return $this->renderView('shared/message', ['title' => $this->translator->translate('voyti.admin.user_not_found'), 'translator' => $this->translator]);
    }

    public function forcePasswordChange(int $id): ResponseInterface
    {
        $user = $this->userRepository->findById($id);
        if ($user !== null && $this->passwordExpireService->run($user)) {
            return $this->renderView('shared/message', ['title' => $this->translator->translate('voyti.admin.password_change_required'), 'translator' => $this->translator]);
        }
        return $this->renderView('shared/message', ['title' => $this->translator->translate('voyti.admin.error_occurred'), 'translator' => $this->translator]);
    }

    public function sessionHistory(int $id): ResponseInterface
    {
        $user = $this->userRepository->findById($id);
        if ($user === null) {
            return $this->renderView('shared/message', ['title' => $this->translator->translate('voyti.admin.user_not_found'), 'translator' => $this->translator]);
        }

        $sessions = $this->sessionHistoryRepository->findByUserId($id);
        return $this->renderView('admin/_session-history', [
            'user' => $user,
            'sessions' => $sessions,
            'config' => $this->config,
        ]);
    }

    public function terminateSessions(int $id): ResponseInterface
    {
        $user = $this->userRepository->findById($id);
        if ($user === null) {
            return $this->renderView('shared/message', ['title' => $this->translator->translate('voyti.admin.user_not_found'), 'translator' => $this->translator]);
        }

        $sessions = $this->sessionHistoryRepository->findByUserId($id);
        foreach ($sessions as $session) {
            $session->delete();
        }

        return $this->renderView('shared/message', ['title' => $this->translator->translate('voyti.admin.sessions_terminated'), 'translator' => $this->translator]);
    }
}
