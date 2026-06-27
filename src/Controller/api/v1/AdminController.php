<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Controller\api\v1;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\Helper\InputDataTrait;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Repository\UserRepository;
use Yiisoft\DataResponse\ResponseFactory\DataResponseFactoryInterface;
use Yiisoft\Security\PasswordHasher;
use Yiisoft\Security\Random;
use Yiisoft\Translator\TranslatorInterface;

final class AdminController
{
    use InputDataTrait;

    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly UserRepository $userRepository,
        private readonly PasswordHasher $passwordHasher,
        private readonly ModuleConfig $config,
        private readonly DataResponseFactoryInterface $responseFactory,
    ) {
    }

    public function create(ServerRequestInterface $request): ResponseInterface
    {
        $body = $this->parsedBody($request);
        $email = $this->stringValue($body, 'email');
        $username = $this->stringValue($body, 'username');
        $password = $this->stringValue($body, 'password', Random::string(12));

        $existingUser = $this->userRepository->findByEmail($email);
        if ($existingUser !== null) {
            return $this->responseFactory->createResponse(['error' => 'Email already exists'], 400);
        }

        $existingUser = $this->userRepository->findByUsername($username);
        if ($existingUser !== null) {
            return $this->responseFactory->createResponse(['error' => 'Username already exists'], 400);
        }

        $user = new User();
        $user->setUsername($username);
        $user->setEmail($email);
        $user->setPasswordHash($this->passwordHasher->hash($password));
        $user->setAuthKey(Random::string());
        $user->setConfirmedAt(time());
        $user->setCreatedAt(time());
        $user->setUpdatedAt(time());
        $user->save();

        return $this->responseFactory->createResponse([
            'id' => $user->getId(),
            'username' => $user->getUsername(),
            'email' => $user->getEmail(),
            'message' => $this->translator->translate('voyti.api.user_created', category: 'voyti'),
        ], 201);
    }

    public function delete(int $id): ResponseInterface
    {
        $user = $this->userRepository->findById($id);
        if ($user === null) {
            return $this->responseFactory->createResponse(['error' => $this->translator->translate('voyti.api.not_found', category: 'voyti')], 404);
        }

        $this->userRepository->delete($user);
        return $this->responseFactory->createResponse(['message' => $this->translator->translate('voyti.api.user_deleted', category: 'voyti')]);
    }

    public function index(): ResponseInterface
    {
        $users = $this->userRepository->findAllUsers();
        $data = array_map(fn ($u) => [
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
        $user = $this->userRepository->findById($id);
        if ($user === null) {
            return $this->responseFactory->createResponse(['error' => $this->translator->translate('voyti.api.not_found', category: 'voyti')], 404);
        }

        $body = $this->parsedBody($request);
        if (isset($body['username']) && is_string($body['username'])) {
            $user->setUsername($body['username']);
        }
        if (isset($body['email']) && is_string($body['email'])) {
            $user->setEmail($body['email']);
        }
        if (isset($body['password']) && is_string($body['password']) && $body['password'] !== '') {
            $user->setPasswordHash($this->passwordHasher->hash($body['password']));
            $user->setPasswordChangedAt(time());
        }
        $user->setUpdatedAt(time());
        $user->save();

        return $this->responseFactory->createResponse([
            'id' => $user->getId(),
            'username' => $user->getUsername(),
            'email' => $user->getEmail(),
            'message' => $this->translator->translate('voyti.api.user_updated', category: 'voyti'),
        ]);
    }

    public function view(int $id): ResponseInterface
    {
        $user = $this->userRepository->findById($id);
        if ($user === null) {
            return $this->responseFactory->createResponse(['error' => $this->translator->translate('voyti.api.not_found', category: 'voyti')], 404);
        }

        return $this->responseFactory->createResponse([
            'id' => $user->getId(),
            'username' => $user->getUsername(),
            'email' => $user->getEmail(),
            'createdAt' => $user->getCreatedAt(),
        ]);
    }
}
