<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Service;

use Psr\EventDispatcher\EventDispatcherInterface;
use YiiRocks\Voyti\Entity\Token;
use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\Event\ResetPasswordEvent;
use YiiRocks\Voyti\Event\UserEvent;
use YiiRocks\Voyti\Helper\SecurityHelper;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Repository\TokenRepository;

final class ResetPasswordService
{
    public function __construct(
        private readonly SecurityHelper $securityHelper,
        private readonly ModuleConfig $config,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly TokenRepository $tokenRepository,
    ) {
    }

    public function run(string $password, User $user, ?Token $token = null): bool
    {
        $this->eventDispatcher->dispatch(new UserEvent($user));

        $user->setPasswordHash($this->securityHelper->hashPassword($password, $this->config->blowfishCost));
        $user->setPasswordChangedAt(time());
        $user->setUpdatedAt(time());
        $result = $user->save();

        if ($token !== null) {
            $token->delete();
        }

        if ($token !== null) {
            $this->eventDispatcher->dispatch(new ResetPasswordEvent($token));
        }
        $this->eventDispatcher->dispatch(new UserEvent($user));
        return $result;
    }
}
