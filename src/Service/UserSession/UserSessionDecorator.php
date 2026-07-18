<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Service\UserSession;

use Psr\EventDispatcher\EventDispatcherInterface;
use YiiRocks\Voyti\Event\Session\SessionEvent;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Model\UserSessions;
use YiiRocks\Voyti\ModuleConfig;
use Yiisoft\Session\SessionInterface;

final readonly class UserSessionDecorator
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
        private ModuleConfig $config,
        private ?SessionInterface $session = null,
    ) {
    }

    public function registerLogin(User $user, ?string $previousSessionId = null): void
    {
        $userId = $user->getIdOrZero();
        $sessionId = $this->session?->getId() ?? '';

        if ($previousSessionId !== null && $previousSessionId !== '' && $previousSessionId !== $sessionId) {
            $this->replaceSession($userId, $previousSessionId);
        }

        $userSession = new UserSessions();
        $userSession->setUserId($userId);
        $userSession->setSessionId($sessionId);
        $userSession->setIp($this->config->disableIpLogging ? '127.0.0.1' : ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'));
        $userSession->setUserAgent($_SERVER['HTTP_USER_AGENT'] ?? null);
        $userSession->setCreatedAt(time());
        $userSession->setUpdatedAt(time());
        $userSession->save();

        $this->eventDispatcher->dispatch(new SessionEvent($userId, $sessionId, ['type' => SessionEvent::SESSION_CREATED]));

        $this->pruneOldSessions($user);
    }

    private function pruneOldSessions(User $user): void
    {
        $userId = $user->getIdOrZero();

        $cutoff = time() - $this->config->rememberLoginLifespan;
        (new UserSessions())->deleteAll([
            'and',
            ['user_id' => $userId],
            ['<', 'created_at', $cutoff],
        ]);
    }

    private function replaceSession(int $userId, string $previousSessionId): void
    {
        $previous = UserSessions::findByUserIdAndSessionId($userId, $previousSessionId);
        if ($previous === null) {
            return;
        }

        $previous->delete();
        $this->eventDispatcher->dispatch(
            new SessionEvent($userId, $previousSessionId, ['type' => SessionEvent::SESSION_TERMINATED]),
        );
    }
}
