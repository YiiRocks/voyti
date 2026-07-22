<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Controller\api\v1\User;

use Psr\Http\Message\ResponseInterface;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Service\Password\PasswordGeneratorInterface;
use YiiRocks\Voyti\Service\Password\PasswordHistoryService;
use YiiRocks\Voyti\Service\User\UserCreationHelper;
use Yiisoft\DataResponse\ResponseFactory\DataResponseFactoryInterface;
use Yiisoft\Http\Status;
use Yiisoft\Input\Http\Attribute\Parameter\Body;
use Yiisoft\Router\HydratorAttribute\RouteArgument;
use Yiisoft\Translator\TranslatorInterface;

/**
 * REST CRUD endpoints for users under the `enableRestApi` route group, authenticated via
 * {@see ApiTokenAuthenticationMiddleware}. Returns JSON only, no view rendering.
 */
final readonly class UserController
{
    public function __construct(
        private TranslatorInterface $translator,
        private ModuleConfig $config,
        private DataResponseFactoryInterface $responseFactory,
        private PasswordGeneratorInterface $passwordGenerator,
        private PasswordHistoryService $passwordHistoryService,
        private UserCreationHelper $userCreationHelper,
    ) {}

    public function create(
        #[Body('email')]
        string $email = '',
        #[Body('username')]
        string $username = '',
        #[Body('password')]
        string $password = '',
    ): ResponseInterface {
        $password = $password !== '' ? $password : $this->passwordGenerator->generate(12);

        $conflict = $this->userCreationHelper->findUniquenessConflict($email, $username);
        if ($conflict !== null) {
            return $this->responseFactory->createResponse(['error' => $conflict], Status::BAD_REQUEST);
        }

        $user = $this->userCreationHelper->buildUser($email, $username, $password);
        $user->setConfirmedAt(time());
        $user->save();
        $this->passwordHistoryService->record($user);

        return $this->responseFactory->createResponse([
            'id' => $user->getId(),
            'username' => $user->getUsername(),
            'email' => $user->getEmail(),
            'message' => $this->translator->translate('voyti.api.user_created', category: 'voyti'),
        ], Status::CREATED);
    }

    public function delete(#[RouteArgument] int $id): ResponseInterface
    {
        $user = $this->resolveUser($id);
        if (!$user instanceof User) {
            return $user;
        }

        $user->delete();
        return $this->responseFactory->createResponse([
            'message' => $this->translator->translate('voyti.api.user_deleted', category: 'voyti'),
        ]);
    }

    public function index(): ResponseInterface
    {
        $users = User::findAllUsers();
        $data = array_map(fn($u) => [
            'id' => $u->getId(),
            'username' => $u->getUsername(),
            'email' => $u->getEmail(),
            'createdAt' => $u->getCreatedAt(),
            'confirmedAt' => $u->getConfirmedAt(),
            'blockedAt' => $u->getBlockedAt(),
        ], $users);

        return $this->responseFactory->createResponse($data);
    }

    public function update(
        #[RouteArgument]
        int $id,
        #[Body('password')]
        string $password = '',
        #[Body('username')]
        ?string $username = null,
        #[Body('email')]
        ?string $email = null,
    ): ResponseInterface {
        $user = $this->resolveUser($id);
        if (!$user instanceof User) {
            return $user;
        }

        if ($password !== '' && $this->passwordHistoryService->wasUsedRecently($user, $password)) {
            return $this->responseFactory->createResponse(
                ['error' => $this->translator->translate('voyti.api.password_previously_used', category: 'voyti')],
                Status::BAD_REQUEST,
            );
        }

        if ($username !== null) {
            $user->setUsername($username);
        }
        if ($email !== null) {
            $user->setEmail($email);
        }
        if ($password !== '') {
            $this->passwordHistoryService->applyPasswordChange($user, $password);
        } else {
            $user->setUpdatedAt(time());
            $user->save();
        }

        return $this->responseFactory->createResponse([
            'id' => $user->getId(),
            'username' => $user->getUsername(),
            'email' => $user->getEmail(),
            'message' => $this->translator->translate('voyti.api.user_updated', category: 'voyti'),
        ]);
    }

    public function view(#[RouteArgument] int $id): ResponseInterface
    {
        $user = $this->resolveUser($id);
        if (!$user instanceof User) {
            return $user;
        }

        return $this->responseFactory->createResponse([
            'id' => $user->getId(),
            'username' => $user->getUsername(),
            'email' => $user->getEmail(),
            'createdAt' => $user->getCreatedAt(),
        ]);
    }

    private function resolveUser(int $id): User|ResponseInterface
    {
        $user = User::findById($id);
        return $user ?? $this->responseFactory->createResponse(
            ['error' => $this->translator->translate('voyti.api.not_found', category: 'voyti')],
            Status::NOT_FOUND,
        );
    }
}
