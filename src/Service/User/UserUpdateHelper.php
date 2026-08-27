<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Service\User;

use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use YiiRocks\Voyti\Event\User\AfterAccountUpdateEvent;
use YiiRocks\Voyti\Event\User\BeforeAccountUpdateEvent;
use YiiRocks\Voyti\Exception\ActionPreventedException;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Service\Password\PasswordHistoryService;

/**
 * Shared account-update logic for callers that update a user's username, email, or password:
 * computing which of those fields actually changed, wrapping the mutation in the cancellable
 * {@see BeforeAccountUpdateEvent} / {@see AfterAccountUpdateEvent} pair, and persisting the result.
 * Kept as one class so callers' event-dispatch behavior can't drift out of sync with each other.
 * `apply()` takes the field mutation itself as a callback because callers can differ there — e.g.
 * `AccountController` routes email changes through `EmailChangeService`'s confirmation flow, while a
 * privileged caller might apply them directly.
 */
final readonly class UserUpdateHelper
{
    public function __construct(
        private ClockInterface $clock,
        private EventDispatcherInterface $eventDispatcher,
        private PasswordHistoryService $passwordHistoryService,
    ) {}

    /**
     * Dispatches `BeforeAccountUpdateEvent`, applies `$mutate` to `$user`, persists the change
     * (routing password changes through {@see PasswordHistoryService::applyPasswordChange()}), then
     * dispatches `AfterAccountUpdateEvent`. Both events are skipped when `$changedFields` is empty.
     *
     * @param list<string> $changedFields
     * @param callable(User): void $mutate Applies the non-password field changes to $user.
     *
     * @throws ActionPreventedException if a `BeforeAccountUpdateEvent` listener rejects the update;
     * $user is left unmutated in that case.
     */
    public function apply(User $user, array $changedFields, callable $mutate, string $password): void
    {
        if ($changedFields !== []) {
            $this->eventDispatcher->dispatch(new BeforeAccountUpdateEvent($user, $changedFields));
        }

        $mutate($user);

        if ($password !== '') {
            $this->passwordHistoryService->applyPasswordChange($user, $password);
        } else {
            $user->setUpdatedAt($this->clock->now()->getTimestamp());
            $user->save();
        }

        if ($changedFields !== []) {
            $this->eventDispatcher->dispatch(new AfterAccountUpdateEvent($user, $changedFields));
        }
    }

    /**
     * @return list<string>
     */
    public function changedFields(User $user, ?string $username, ?string $email, string $password): array
    {
        $changedFields = [];

        if ($username !== null && $username !== $user->getUsername()) {
            $changedFields[] = 'username';
        }
        if ($email !== null && $email !== $user->getEmail()) {
            $changedFields[] = 'email';
        }
        if ($password !== '') {
            $changedFields[] = 'password';
        }

        return $changedFields;
    }
}
