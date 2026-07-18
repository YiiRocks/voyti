<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Controller;

use YiiRocks\Voyti\Model\User;

/**
 * Trait providing the current authenticated user's ID for audit-log attribution, relying on a
 * `$currentUser` property supplied by the using class.
 */
trait ActorIdTrait
{
    private function actorId(): ?int
    {
        $identity = $this->currentUser->getIdentity();
        return $identity instanceof User ? $identity->getIdOrZero() : null;
    }
}
