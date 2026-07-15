<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Controller;

use Psr\Http\Message\ResponseInterface;
use YiiRocks\Voyti\Model\User;

trait RequireUserTrait
{
    private function requireUser(): User|ResponseInterface
    {
        $identity = $this->currentUser->getIdentity();
        if ($identity instanceof \Yiisoft\User\Guest\GuestIdentityInterface) {
            return $this->renderError('voyti.settings.not_authenticated');
        }

        $user = User::findById((int) ($identity->getId() ?? 0));
        if ($user === null) {
            return $this->renderError('voyti.settings.user_not_found');
        }

        return $user;
    }
}
