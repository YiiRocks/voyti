<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Controller;

use YiiRocks\Voyti\Model\User;

trait ActorIdTrait
{
    private function actorId(): ?int
    {
        $identity = $this->currentUser->getIdentity();
        return $identity instanceof User ? $identity->getIdOrZero() : null;
    }
}
