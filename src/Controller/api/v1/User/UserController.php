<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Controller\api\v1\User;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use YiiRocks\Voyti\Helper\InputDataTrait;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Service\Password\PasswordGeneratorInterface;
use Yiisoft\DataResponse\ResponseFactory\DataResponseFactoryInterface;
use Yiisoft\Http\Status;
use Yiisoft\Security\PasswordHasher;
use Yiisoft\Security\Random;
use Yiisoft\Translator\TranslatorInterface;

final readonly class UserController
{
    use InputDataTrait;

    public function __construct(
        private TranslatorInterface $translator,
        private PasswordHasher $passwordHasher,
        private ModuleConfig $config,
        private DataResponseFactoryInterface $responseFactory,
        private PasswordGeneratorInterface $passwordGenerator,
    ) {
    }

    public function create(ServerRequestInterface $request): ResponseInterface
    {
        $body = $this->parsedBody($request);
        $email = $this->stringValue($body, 'email');
        $username = $this->stringValue($body, 'username');
        $password = $this->stringValue($body, 'password', $this->passwordGenerator->generate(12));

        $existingUser = User::findByEmail($email);
        if ($existingUser !== null) {
            return $this->responseFactory->createResponse(['error' => 'Email already exists'], Status::BAD_REQUEST);
        }

        $existingUser = User::findByUsername($username);
        if ($existingUser !== null) {
            return $this->responseFactory->createResponse(['error' => 'Username already exists'], Status::BAD_REQUEST);
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
        ], Status::CREATED);
    }

    public function delete(int $id): ResponseInterface
    {
        $user = User::findById($id);
        if ($user === null) {
            return $this->responseFactory->createResponse(['error' => $this->translator->translate('voyti.api.not_found', category: 'voyti')], Status::NOT_FOUND);
        }

        $user->delete();
        return $this->responseFactory->createResponse(['message' => $this->translator->translate('voyti.api.user_deleted', category: 'voyti')]);
    }

    public function index(): ResponseInterface
    {
        $users = User::findAllUsers();
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
        $user = User::findById($id);
        if ($user === null) {
            return $this->responseFactory->createResponse(['error' => $this->translator->translate('voyti.api.not_found', category: 'voyti')], Status::NOT_FOUND);
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
        $user = User::findById($id);
        if ($user === null) {
            return $this->responseFactory->createResponse(['error' => $this->translator->translate('voyti.api.not_found', category: 'voyti')], Status::NOT_FOUND);
        }

        return $this->responseFactory->createResponse([
            'id' => $user->getId(),
            'username' => $user->getUsername(),
            'email' => $user->getEmail(),
            'createdAt' => $user->getCreatedAt(),
        ]);
    }
}
