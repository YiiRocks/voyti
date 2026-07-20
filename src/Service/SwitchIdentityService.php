<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Service;

use Psr\EventDispatcher\EventDispatcherInterface;
use YiiRocks\Voyti\Event\Auth\AfterLoginEvent;
use YiiRocks\Voyti\Event\User\UserEvent;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\ModuleConfig;
use Yiisoft\Session\SessionInterface;
use Yiisoft\User\CurrentUser;
use Yiisoft\User\Guest\GuestIdentityInterface;

/**
 * Lets an authenticated user (e.g. an admin) temporarily assume another user's identity and later
 * restore their original one, tracking the original identity's ID in the session.
 */
final readonly class SwitchIdentityService
{
    private const SESSION_KEY = 'voyti_original_admin_user';

    public function __construct(
        private ModuleConfig $config,
        private CurrentUser $currentUser,
        private SessionInterface $session,
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    public function getOriginalUser(): ?User
    {
        /** @var mixed $originalId */
        $originalId = $this->session->get(self::SESSION_KEY);
        if ($originalId === null) {
            return null;
        }

        return User::findById((int) $originalId);
    }

    public function isSwitched(): bool
    {
        return $this->session->has(self::SESSION_KEY);
    }

    /**
     * @param array<array-key, mixed> $serverParams
     */
    public function restore(array $serverParams = []): ServiceResult
    {
        /** @var mixed $originalId */
        $originalId = $this->session->get(self::SESSION_KEY);
        if ($originalId === null) {
            return ServiceResult::failure('No original identity to restore');
        }

        $originalUser = User::findById((int) $originalId);
        if ($originalUser === null) {
            return ServiceResult::failure('Original user not found');
        }

        $previousSessionId = $this->session->getId();
        $this->currentUser->login($originalUser);
        $this->session->remove(self::SESSION_KEY);
        $this->eventDispatcher->dispatch(
            new AfterLoginEvent($originalUser, previousSessionId: $previousSessionId, serverParams: $serverParams),
        );
        $this->eventDispatcher->dispatch(new UserEvent($originalUser, UserEvent::RESTORE_IDENTITY));

        return ServiceResult::success();
    }

    /**
     * @param array<array-key, mixed> $serverParams
     */
    public function run(int $id, array $serverParams = []): ServiceResult
    {
        if (!$this->config->enableSwitchIdentities) {
            return ServiceResult::failure('Switch identities is disabled');
        }

        $targetUser = User::findById($id);
        if ($targetUser === null) {
            return ServiceResult::failure('User not found');
        }

        if ($targetUser->isBlocked()) {
            return ServiceResult::failure('Cannot switch to a blocked user');
        }

        $currentIdentity = $this->currentUser->getIdentity();
        $currentIdentity = $currentIdentity instanceof GuestIdentityInterface ? null : $currentIdentity;
        if ($currentIdentity !== null) {
            if ((int) $currentIdentity->getId() === $id) {
                return ServiceResult::failure('Cannot switch to yourself');
            }

            $this->session->set(self::SESSION_KEY, $currentIdentity->getId());
        }

        $previousSessionId = $this->session->getId();
        $this->currentUser->login($targetUser);
        $this->eventDispatcher->dispatch(
            new AfterLoginEvent($targetUser, previousSessionId: $previousSessionId, serverParams: $serverParams),
        );
        $this->eventDispatcher->dispatch(new UserEvent($targetUser, UserEvent::SWITCH_IDENTITY));

        return ServiceResult::success();
    }
}
