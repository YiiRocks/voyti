<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Service\UserSessionHistory;

use Psr\EventDispatcher\EventDispatcherInterface;
use YiiRocks\Voyti\Event\Session\SessionEvent;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Model\UserSessionHistory;
use YiiRocks\Voyti\ModuleConfig;
use Yiisoft\Session\SessionInterface;

final readonly class UserSessionHistoryDecorator
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
        private ModuleConfig $config,
        private ?SessionInterface $session = null,
    ) {
    }

    public function registerLogin(User $user): void
    {
        if (!$this->config->enableSessionHistory) {
            return;
        }

        $userId = $user->getIdOrZero();
        $sessionId = $this->session?->getId() ?? '';

        $userSessionHistory = new UserSessionHistory();
        $userSessionHistory->setUserId($userId);
        $userSessionHistory->setSessionId($sessionId);
        $userSessionHistory->setIp($this->config->disableIpLogging ? '127.0.0.1' : ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'));
        $userSessionHistory->setUserAgent($_SERVER['HTTP_USER_AGENT'] ?? null);
        $userSessionHistory->setCreatedAt(time());
        $userSessionHistory->setUpdatedAt(time());
        $userSessionHistory->save();

        $this->eventDispatcher->dispatch(new SessionEvent($userId, $sessionId, ['type' => SessionEvent::SESSION_CREATED]));

        $this->pruneOldSessions($user);
    }

    private function pruneOldSessions(User $user): void
    {
        if ($this->config->hasNumberSessionHistory()) {
            $userId = $user->getIdOrZero();
            $sessions = UserSessionHistory::query()
                ->where(['user_id' => $userId])
                ->orderBy(['created_at' => SORT_DESC])
                ->all();

            /** @var int $limit */
            $limit = $this->config->numberSessionHistory;

            /** @var list<UserSessionHistory> $toDelete */
            $toDelete = array_slice($sessions, $limit);
            foreach ($toDelete as $session) {
                $session->delete();
            }
        }
    }
}
