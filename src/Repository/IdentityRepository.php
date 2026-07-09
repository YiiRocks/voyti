<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Repository;

use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\Entity\UserToken;
use YiiRocks\Voyti\Helper\ApiTokenHasher;
use YiiRocks\Voyti\ModuleConfig;
use Yiisoft\Auth\IdentityInterface;
use Yiisoft\Auth\IdentityRepositoryInterface;
use Yiisoft\Auth\IdentityWithTokenRepositoryInterface;

final readonly class IdentityRepository implements IdentityRepositoryInterface, IdentityWithTokenRepositoryInterface
{
    public function __construct(
        private UserRepository $userRepository,
        private UserTokenRepository $userTokenRepository,
        private ModuleConfig $config,
    ) {
    }

    /**
     * @return User|null
     */
    #[\Override]
    public function findIdentity(string $id): ?IdentityInterface
    {
        return $this->userRepository->findById((int) $id);
    }

    #[\Override]
    public function findIdentityByToken(string $token, ?string $type = null): ?IdentityInterface
    {
        $userToken = $this->userTokenRepository->findByCodeAndType(
            ApiTokenHasher::hash($token),
            UserToken::TYPE_API_ACCESS,
        );

        if ($userToken === null) {
            return null;
        }

        if (
            $this->config->apiTokenLifespan !== null
            && (time() - $userToken->getCreatedAt()) > $this->config->apiTokenLifespan
        ) {
            return null;
        }

        return $userToken->getUser();
    }
}
