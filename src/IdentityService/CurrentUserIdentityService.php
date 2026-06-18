<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\IdentityService;

use Override;
use Yiisoft\Auth\IdentityInterface;
use Yiisoft\User\CurrentUser;
use Yiisoft\User\Guest\GuestIdentityInterface;
use YiiRocks\Voyti\IdentityServiceInterface;

final class CurrentUserIdentityService implements IdentityServiceInterface
{
    public function __construct(
        private readonly CurrentUser $currentUser,
    ) {
    }

    #[Override]
    public function getIdentity(): ?IdentityInterface
    {
        $identity = $this->currentUser->getIdentity();

        return $identity instanceof GuestIdentityInterface ? null : $identity;
    }

    #[Override]
    public function login(IdentityInterface $identity, ?int $lifespan = null): void
    {
        $user = $this->currentUser;

        if ($lifespan !== null) {
            $user = $user->withAuthTimeout($lifespan);
        }

        $user->login($identity);
    }

    #[Override]
    public function logout(): void
    {
        $this->currentUser->logout();
    }
}
