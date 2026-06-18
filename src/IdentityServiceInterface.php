<?php

declare(strict_types=1);

namespace YiiRocks\Voyti;

use Yiisoft\Auth\IdentityInterface;

interface IdentityServiceInterface
{
    public function getIdentity(): ?IdentityInterface;

    public function login(IdentityInterface $identity, ?int $lifespan = null): void;

    public function logout(): void;
}
