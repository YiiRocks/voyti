<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Controller\api\v1;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\DataResponse\DataResponseFactoryInterface;
use Yiisoft\Translator\TranslatorInterface;
use YiiRocks\Voyti\Helper\SecurityHelper;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Repository\UserRepository;
use YiiRocks\Voyti\Entity\User;

final class AdminController
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly UserRepository $userRepository,
        private readonly SecurityHelper $securityHelper,
        private readonly ModuleConfig $config,
        private readonly DataResponseFactoryInterface $responseFactory,
    ) {
    }

    public function index(): ResponseInterface
    {
        $users = $this->userRepository->findAll();
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

    public function view(int $id): ResponseInterface
    {
        $user = $this->userRepository->findById($id);
        if ($user === null) {
            return $this->responseFactory->createResponse(['error' => $this->translator->translate('voyti.api.not_found')], 404);
        }

        return $this->responseFactory->createResponse([
            'id' => $user->getId(),
            'username' => $user->getUsername(),
            'email' => $user->getEmail(),
            'createdAt' => $user->getCreatedAt(),
        ]);
    }

    public function create(ServerRequestInterface $request): ResponseInterface
    {
        $body = $request->getParsedBody();
        $email = $body['email'] ?? '';
        $username = $body['username'] ?? '';
        $password = $body['password'] ?? $this->securityHelper->generateRandomString(12);

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
        $user->setPasswordHash($this->securityHelper->hashPassword($password, $this->config->blowfishCost));
        $user->setAuthKey($this->securityHelper->generateRandomString());
        $user->setConfirmedAt(time());
        $user->setCreatedAt(time());
        $user->setUpdatedAt(time());
        $user->save();

        return $this->responseFactory->createResponse([
            'id' => $user->getId(),
            'username' => $user->getUsername(),
            'email' => $user->getEmail(),
            'message' => $this->translator->translate('voyti.api.user_created'),
        ], 201);
    }

    public function update(ServerRequestInterface $request, int $id): ResponseInterface
    {
        $user = $this->userRepository->findById($id);
        if ($user === null) {
            return $this->responseFactory->createResponse(['error' => $this->translator->translate('voyti.api.not_found')], 404);
        }

        $body = $request->getParsedBody();
        if (isset($body['username'])) {
            $user->setUsername($body['username']);
        }
        if (isset($body['email'])) {
            $user->setEmail($body['email']);
        }
        if (!empty($body['password'])) {
            $user->setPasswordHash($this->securityHelper->hashPassword($body['password'], $this->config->blowfishCost));
            $user->setPasswordChangedAt(time());
        }
        $user->setUpdatedAt(time());
        $user->save();

        return $this->responseFactory->createResponse([
            'id' => $user->getId(),
            'username' => $user->getUsername(),
            'email' => $user->getEmail(),
            'message' => $this->translator->translate('voyti.api.user_updated'),
        ]);
    }

    public function delete(int $id): ResponseInterface
    {
        $user = $this->userRepository->findById($id);
        if ($user === null) {
            return $this->responseFactory->createResponse(['error' => $this->translator->translate('voyti.api.not_found')], 404);
        }

        $this->userRepository->delete($user);
        return $this->responseFactory->createResponse(['message' => $this->translator->translate('voyti.api.user_deleted')]);
    }
}
