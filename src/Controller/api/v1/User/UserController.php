<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Controller\api\v1\User;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use YiiRocks\Voyti\Helper\InputDataTrait;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Service\Password\PasswordGeneratorInterface;
use YiiRocks\Voyti\Service\Password\PasswordHistoryService;
use YiiRocks\Voyti\Service\User\UserCreationHelper;
use Yiisoft\DataResponse\ResponseFactory\DataResponseFactoryInterface;
use Yiisoft\Http\Status;
use Yiisoft\Translator\TranslatorInterface;

/**
 * REST CRUD endpoints for users under the `enableRestApi` route group, authenticated via
 * {@see ApiTokenAuthenticationMiddleware}. Returns JSON only, no view rendering.
 */
final readonly class UserController
{
    use InputDataTrait;

    public function __construct(
        private TranslatorInterface $translator,
        private ModuleConfig $config,
        private DataResponseFactoryInterface $responseFactory,
        private PasswordGeneratorInterface $passwordGenerator,
        private PasswordHistoryService $passwordHistoryService,
        private UserCreationHelper $userCreationHelper,
    ) {}

    public function create(ServerRequestInterface $request): ResponseInterface
    {
        $body = $this->parsedBody($request);
        $email = $this->stringValue($body, 'email');
        $username = $this->stringValue($body, 'username');
        $password = $this->stringValue($body, 'password', $this->passwordGenerator->generate(12));

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

    public function delete(int $id): ResponseInterface
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

    public function update(ServerRequestInterface $request, int $id): ResponseInterface
    {
        $user = $this->resolveUser($id);
        if (!$user instanceof User) {
            return $user;
        }

        $body = $this->parsedBody($request);
        $password = isset($body['password']) && is_string($body['password']) ? $body['password'] : '';

        if ($password !== '' && $this->passwordHistoryService->wasUsedRecently($user, $password)) {
            return $this->responseFactory->createResponse(
                ['error' => $this->translator->translate('voyti.api.password_previously_used', category: 'voyti')],
                Status::BAD_REQUEST,
            );
        }

        if (isset($body['username']) && is_string($body['username'])) {
            $user->setUsername($body['username']);
        }
        if (isset($body['email']) && is_string($body['email'])) {
            $user->setEmail($body['email']);
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

    public function view(int $id): ResponseInterface
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
