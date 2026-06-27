<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Repository;

use Yiisoft\Auth\IdentityInterface;
use Yiisoft\Auth\IdentityRepositoryInterface;

final class IdentityRepository implements IdentityRepositoryInterface
{
    public function __construct(
        private readonly UserRepository $userRepository,
    ) {
    }

    /**
     * @return \YiiRocks\Voyti\Entity\User|null
     */
    #[\Override]
    public function findIdentity(string $id): ?IdentityInterface
    {
        return $this->userRepository->findById((int) $id);
    }
}
