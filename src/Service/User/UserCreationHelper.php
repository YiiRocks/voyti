<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Service\User;

use Psr\EventDispatcher\EventDispatcherInterface;
use RuntimeException;
use YiiRocks\Voyti\Event\Auth\AfterRegisterEvent;
use YiiRocks\Voyti\Event\User\UserEvent;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Model\UserProfile;
use YiiRocks\Voyti\Model\UserToken;
use YiiRocks\Voyti\Service\MailService;
use YiiRocks\Voyti\Service\Password\PasswordHistoryService;
use YiiRocks\Voyti\VoytiConfig;
use Yiisoft\Db\Exception\IntegrityException;
use Yiisoft\Security\PasswordHasher;
use Yiisoft\Security\Random;

/**
 * Shared user-persistence logic for account creation: builds a {@see User} with a hashed password,
 * checks email/username uniqueness, and persists it with its profile. `persistAndNotify()` requires
 * email confirmation (sending a confirmation token) when {@see VoytiConfig::$enableEmailConfirmation}
 * is on; `persistAndNotifySkippingConfirmation()` always persists as already-confirmed and sends a
 * welcome email instead (e.g. when the identity was already verified by a social provider).
 * `findUniquenessConflict()` then persisting isn't atomic, so a concurrent request can still slip
 * past it; `persist()` catches the resulting DB-level unique-constraint violation and rethrows it as
 * a {@see RuntimeException} carrying the same conflict message, instead of letting the race surface
 * as an uncaught 500.
 */
final readonly class UserCreationHelper
{
    public function __construct(
        private MailService $mailService,
        private EventDispatcherInterface $eventDispatcher,
        private PasswordHasher $passwordHasher,
        private VoytiConfig $config,
        private PasswordHistoryService $passwordHistoryService,
    ) {}

    public function buildUser(string $email, string $username, string $password): User
    {
        $user = new User();
        $user->setUsername($username);
        $user->setEmail($email);
        $user->setPasswordHash($this->passwordHasher->hash($password));
        $user->setAuthKey(Random::string());
        $user->setCreatedAt(time());
        $user->setUpdatedAt(time());
        return $user;
    }

    public function findUniquenessConflict(string $email, string $username): ?string
    {
        if (User::findByEmail($email) !== null) {
            return 'Email already exists';
        }

        if (User::findByUsername($username) !== null) {
            return 'Username already exists';
        }

        return null;
    }

    /**
     * Persists the user (with profile and confirmation token), dispatches the creation/registration
     * events, and sends the confirmation mail.
     *
     * @return bool Whether email confirmation is required before the account can be used.
     */
    public function persistAndNotify(User $user): bool
    {
        return $this->persist($user, skipConfirmation: false);
    }

    /**
     * Persists the user as already confirmed (e.g. when the identity was already established by a
     * social provider), dispatches the creation/registration events, and sends the welcome mail.
     *
     * @return bool Whether email confirmation is required before the account can be used.
     */
    public function persistAndNotifySkippingConfirmation(User $user): bool
    {
        return $this->persist($user, skipConfirmation: true);
    }

    private function persist(User $user, bool $skipConfirmation): bool
    {
        $userProfile = new UserProfile();

        if ($this->config->enableEmailConfirmation && !$skipConfirmation) {
            /** @infection-ignore-all The token's exact length is immaterial: it is stored only as a sha256 hash and emailed as an opaque click-through code, so any length works identically. */
            $rawCode = Random::string(32);
            $userToken = new UserToken();
            $userToken->setCreatedAt(time());
            $userToken->setCode(hash('sha256', $rawCode));

            $this->saveOrThrowConflict($user, static fn(): mixed => User::saveWithProfileAndToken($user, $userProfile, $userToken));
            $this->passwordHistoryService->record($user);
            $this->mailService->sendConfirmation($user, $rawCode);

            $this->eventDispatcher->dispatch(new UserEvent($user, UserEvent::CREATE));
            $this->eventDispatcher->dispatch(new AfterRegisterEvent($user));
            return true;
        }

        $user->setConfirmedAt(time());
        $this->saveOrThrowConflict($user, static fn(): mixed => User::saveWithProfile($user, $userProfile));
        $this->passwordHistoryService->record($user);
        $this->mailService->sendWelcome($user);

        $this->eventDispatcher->dispatch(new UserEvent($user, UserEvent::CREATE));
        $this->eventDispatcher->dispatch(new AfterRegisterEvent($user));
        return false;
    }

    /**
     * @throws RuntimeException carrying the same message {@see self::findUniquenessConflict()} would
     * have returned, if a concurrent request won the race and inserted the same email/username first.
     */
    private function saveOrThrowConflict(User $user, callable $save): void
    {
        try {
            $save();
        } catch (IntegrityException) {
            $conflict = $this->findUniquenessConflict($user->getEmail(), $user->getUsername()) ?? 'A user with this email or username already exists.';
            throw new RuntimeException($conflict);
        }
    }
}
