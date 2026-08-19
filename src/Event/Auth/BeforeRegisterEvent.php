<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Event\Auth;

use YiiRocks\Voyti\Exception\ActionPreventedException;
use YiiRocks\Voyti\Model\User;

/**
 * Dispatched in `RegisterService::run()` after the validated form data has been hydrated into a
 * `User` but before it is persisted, carrying the validated `formData` array and the hydrated,
 * not-yet-saved `User`. Cancellable: a listener may throw {@see ActionPreventedException} to reject
 * the registration (e.g. quota enforcement, compliance validation).
 */
final readonly class BeforeRegisterEvent
{
    /**
     * @param array<array-key, mixed> $formData
     */
    public function __construct(
        private array $formData,
        private User $user,
    ) {}

    /**
     * @return array<array-key, mixed>
     */
    public function getFormData(): array
    {
        return $this->formData;
    }

    public function getUser(): User
    {
        return $this->user;
    }
}
