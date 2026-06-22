<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Service;

use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Repository\UserRepository;
use Yiisoft\Session\SessionInterface;
use Yiisoft\User\CurrentUser;
use Yiisoft\User\Guest\GuestIdentityInterface;

final class SwitchIdentityService
{
    public function __construct(
        private readonly ModuleConfig $config,
        private readonly UserRepository $userRepository,
        private readonly CurrentUser $currentUser,
        private readonly SessionInterface $session,
    ) {
    }

    public function restore(): ServiceResult
    {
        $originalId = $this->session->get($this->config->switchIdentitySessionKey);
        if ($originalId === null) {
            return ServiceResult::failure('No original identity to restore');
        }

        $originalUser = $this->userRepository->findById((int) $originalId);
        if ($originalUser === null) {
            return ServiceResult::failure('Original user not found');
        }

        $this->currentUser->logout();
        $this->currentUser->login($originalUser);
        $this->session->remove($this->config->switchIdentitySessionKey);

        return ServiceResult::success();
    }

    public function run(int $id): ServiceResult
    {
        if (!$this->config->enableSwitchIdentities) {
            return ServiceResult::failure('Switch identities is disabled');
        }

        $targetUser = $this->userRepository->findById($id);
        if ($targetUser === null) {
            return ServiceResult::failure('User not found');
        }

        if ($targetUser->isBlocked()) {
            return ServiceResult::failure('Cannot switch to a blocked user');
        }

        $currentIdentity = $this->currentUser->getIdentity();
        $currentIdentity = $currentIdentity instanceof GuestIdentityInterface ? null : $currentIdentity;
        if ($currentIdentity !== null) {
            $this->session->set($this->config->switchIdentitySessionKey, $currentIdentity->getId());
        }

        $this->currentUser->logout();
        $this->currentUser->login($targetUser);

        return ServiceResult::success();
    }
}
