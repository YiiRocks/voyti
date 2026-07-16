<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Service;

use Psr\EventDispatcher\EventDispatcherInterface;
use YiiRocks\Voyti\Event\Auth\AfterLoginEvent;
use YiiRocks\Voyti\Event\User\UserEvent;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\ModuleConfig;
use Yiisoft\Session\SessionInterface;
use Yiisoft\User\CurrentUser;
use Yiisoft\User\Guest\GuestIdentityInterface;

final readonly class SwitchIdentityService
{
    public function __construct(
        private ModuleConfig $config,
        private CurrentUser $currentUser,
        private SessionInterface $session,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function getOriginalUser(): ?User
    {
        $sessionKey = $this->config->switchIdentitySessionKey;
        if ($sessionKey === null) {
            return null;
        }

        /** @var mixed $originalId */
        $originalId = $this->session->get($sessionKey);
        if ($originalId === null) {
            return null;
        }

        return User::findById((int) $originalId);
    }

    public function isSwitched(): bool
    {
        $sessionKey = $this->config->switchIdentitySessionKey;
        if ($sessionKey === null) {
            return false;
        }

        return $this->session->has($sessionKey);
    }

    public function restore(): ServiceResult
    {
        $sessionKey = $this->config->switchIdentitySessionKey;
        if ($sessionKey === null) {
            return ServiceResult::failure('No original identity to restore');
        }

        /** @var mixed $originalId */
        $originalId = $this->session->get($sessionKey);
        if ($originalId === null) {
            return ServiceResult::failure('No original identity to restore');
        }

        $originalUser = User::findById((int) $originalId);
        if ($originalUser === null) {
            return ServiceResult::failure('Original user not found');
        }

        $this->eventDispatcher->dispatch(new UserEvent($originalUser));
        $previousSessionId = $this->session->getId();
        $this->currentUser->login($originalUser);
        $this->session->remove($sessionKey);
        $this->eventDispatcher->dispatch(new AfterLoginEvent($originalUser, previousSessionId: $previousSessionId));
        $this->eventDispatcher->dispatch(new UserEvent($originalUser));

        return ServiceResult::success();
    }

    public function run(int $id): ServiceResult
    {
        if (!$this->config->enableSwitchIdentities) {
            return ServiceResult::failure('Switch identities is disabled');
        }

        $targetUser = User::findById($id);
        if ($targetUser === null) {
            return ServiceResult::failure('User not found');
        }

        if ($targetUser->isBlocked()) {
            return ServiceResult::failure('Cannot switch to a blocked user');
        }

        $currentIdentity = $this->currentUser->getIdentity();
        $currentIdentity = $currentIdentity instanceof GuestIdentityInterface ? null : $currentIdentity;
        if ($currentIdentity !== null) {
            if ((int) $currentIdentity->getId() === $id) {
                return ServiceResult::failure('Cannot switch to yourself');
            }

            $sessionKey = $this->config->switchIdentitySessionKey;
            if ($sessionKey === null) {
                return ServiceResult::failure('Switch identity session key is not configured');
            }

            $this->session->set($sessionKey, $currentIdentity->getId());
        }

        $this->eventDispatcher->dispatch(new UserEvent($targetUser));
        $previousSessionId = $this->session->getId();
        $this->currentUser->login($targetUser);
        $this->eventDispatcher->dispatch(new AfterLoginEvent($targetUser, previousSessionId: $previousSessionId));
        $this->eventDispatcher->dispatch(new UserEvent($targetUser));

        return ServiceResult::success();
    }
}
