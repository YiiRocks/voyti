<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Event\User;

use YiiRocks\Voyti\Exception\ActionPreventedException;
use YiiRocks\Voyti\Model\User;

/**
 * Dispatched in `AccountController::update()` before account-level fields (username, email,
 * password) are saved, carrying the `User` and the list of field names about to change. Distinct from
 * {@see UserProfileEvent}, which covers cosmetic profile fields. Cancellable: a listener may throw
 * {@see ActionPreventedException} to reject the update (e.g. compliance validation).
 */
final readonly class BeforeAccountUpdateEvent
{
    /**
     * @param list<string> $changedFields
     */
    public function __construct(
        private User $user,
        private array $changedFields,
    ) {}

    /**
     * @return list<string>
     */
    public function getChangedFields(): array
    {
        return $this->changedFields;
    }

    public function getUser(): User
    {
        return $this->user;
    }
}
