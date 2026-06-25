<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Event\Auth;

use YiiRocks\Voyti\Entity\User;
use Yiisoft\Router\CurrentRoute;

final class AfterLoginEvent
{
    public function __construct(
        private readonly User $user,
        private readonly ?CurrentRoute $route = null,
    ) {
    }

    public function getRoute(): ?CurrentRoute
    {
        return $this->route;
    }

    public function getUser(): User
    {
        return $this->user;
    }
}
