<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Event\User;

use YiiRocks\Voyti\Model\User;

/**
 * Dispatched in `AccountController::update()` after account-level fields (username, email, password)
 * are saved, carrying the updated `User` and the list of field names that changed. Distinct from
 * {@see UserProfileEvent}, which covers cosmetic profile fields.
 */
final readonly class AfterAccountUpdateEvent
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
