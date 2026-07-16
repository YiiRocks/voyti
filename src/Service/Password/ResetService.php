<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Service\Password;

use Psr\EventDispatcher\EventDispatcherInterface;
use YiiRocks\Voyti\Event\Security\ResetPasswordEvent;
use YiiRocks\Voyti\Event\User\UserEvent;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Model\UserToken;
use YiiRocks\Voyti\ModuleConfig;

final readonly class ResetService
{
    public function __construct(
        private ModuleConfig $config,
        private EventDispatcherInterface $eventDispatcher,
        private PasswordHistoryService $passwordHistoryService,
    ) {
    }

    public function run(string $password, User $user, ?UserToken $userToken = null): bool
    {
        if ($this->passwordHistoryService->wasUsedRecently($user, $password)) {
            return false;
        }

        $this->eventDispatcher->dispatch(new UserEvent($user));

        $this->passwordHistoryService->applyPasswordChange($user, $password);

        $this->handleToken($userToken);

        $this->eventDispatcher->dispatch(new UserEvent($user));
        return true;
    }

    private function handleToken(?UserToken $userToken): void
    {
        if ($userToken !== null) {
            $userToken->delete();
            $this->eventDispatcher->dispatch(new ResetPasswordEvent($userToken));
        }
    }
}
