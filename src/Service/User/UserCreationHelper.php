<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Service\User;

use Psr\EventDispatcher\EventDispatcherInterface;
use YiiRocks\Voyti\Event\Auth\AfterRegisterEvent;
use YiiRocks\Voyti\Event\User\UserEvent;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Model\UserProfile;
use YiiRocks\Voyti\Model\UserToken;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Service\MailService;
use YiiRocks\Voyti\Service\Password\PasswordHistoryService;
use Yiisoft\Security\PasswordHasher;
use Yiisoft\Security\Random;

final readonly class UserCreationHelper
{
    public function __construct(
        private MailService $mailService,
        private EventDispatcherInterface $eventDispatcher,
        private PasswordHasher $passwordHasher,
        private ModuleConfig $config,
        private PasswordHistoryService $passwordHistoryService,
    ) {
    }

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
     * Persists the user (with profile and, if required, a confirmation token), dispatches the
     * creation/registration events, and sends the appropriate mail.
     *
     * @param bool $skipConfirmation Treat the account as confirmed even if email confirmation is
     *     otherwise required, e.g. when the identity was already established by a social provider.
     *
     * @return bool Whether email confirmation is required before the account can be used.
     */
    public function persistAndNotify(User $user, string $password, bool $skipConfirmation = false): bool
    {
        $userProfile = new UserProfile();

        if ($this->config->enableEmailConfirmation && !$skipConfirmation) {
            $userToken = new UserToken();
            $userToken->setCreatedAt(time());
            $userToken->setCode(Random::string(32));

            User::saveWithProfileAndToken($user, $userProfile, $userToken);
            $this->passwordHistoryService->record($user);
            $this->mailService->sendConfirmation($user, $userToken);

            $this->eventDispatcher->dispatch(new UserEvent($user, UserEvent::CREATE));
            $this->eventDispatcher->dispatch(new AfterRegisterEvent($user));
            return true;
        }

        $user->setConfirmedAt(time());
        User::saveWithProfile($user, $userProfile);
        $this->passwordHistoryService->record($user);
        $this->mailService->sendWelcome($user, $password);

        $this->eventDispatcher->dispatch(new UserEvent($user, UserEvent::CREATE));
        $this->eventDispatcher->dispatch(new AfterRegisterEvent($user));
        return false;
    }
}
