<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Controller;

use Psr\Http\Message\ResponseInterface;
use YiiRocks\Voyti\Model\User;
use Yiisoft\User\Guest\GuestIdentityInterface;

/**
 * Adds a guard that resolves the current authenticated `User`, redirecting guests to login and
 * rendering an error if the identity has no backing user row. Requires the consumer to have
 * `$currentUser` and `$url` properties and to use {@see RedirectTrait} and {@see RenderTrait}.
 */
trait RequireUserTrait
{
    private function requireUser(): User|ResponseInterface
    {
        $identity = $this->currentUser->getIdentity();
        if ($identity instanceof GuestIdentityInterface) {
            return $this->redirect($this->url->generate('voyti/session-login'));
        }

        $user = User::findById((int) ($identity->getId() ?? 0));
        if ($user === null) {
            return $this->renderError('voyti.settings.user_not_found');
        }

        return $user;
    }
}
