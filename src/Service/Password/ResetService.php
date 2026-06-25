<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Service\Password;

use Psr\EventDispatcher\EventDispatcherInterface;
use YiiRocks\Voyti\Entity\UserToken;
use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\Event\Security\ResetPasswordEvent;
use YiiRocks\Voyti\Event\User\UserEvent;
use YiiRocks\Voyti\ModuleConfig;
use Yiisoft\Security\PasswordHasher;
use YiiRocks\Voyti\Repository\UserTokenRepository;

final class ResetService
{
    public function __construct(
        private readonly PasswordHasher $passwordHasher,
        private readonly ModuleConfig $config,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly UserTokenRepository $userTokenRepository,
    ) {
    }

    public function run(string $password, User $user, ?UserToken $userToken = null): bool
    {
        $this->eventDispatcher->dispatch(new UserEvent($user));

        $user->setPasswordHash($this->passwordHasher->hash($password));
        $user->setPasswordChangedAt(time());
        $user->setUpdatedAt(time());
        $user->save();
        $result = true;

        $this->handleToken($userToken);

        $this->eventDispatcher->dispatch(new UserEvent($user));
        return $result;
    }

    private function handleToken(?UserToken $userToken): void
    {
        if ($userToken !== null) {
            $userToken->delete();
            $this->eventDispatcher->dispatch(new ResetPasswordEvent($userToken));
        }
    }
}
