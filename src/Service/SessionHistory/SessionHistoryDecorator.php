<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Service\SessionHistory;

use Psr\EventDispatcher\EventDispatcherInterface;
use YiiRocks\Voyti\Entity\SessionHistory;
use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\Event\SessionEvent;
use YiiRocks\Voyti\ModuleConfig;
use Yiisoft\Session\SessionInterface;

final class SessionHistoryDecorator
{
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ModuleConfig $config,
        private readonly ?SessionInterface $session = null,
    ) {
    }

    public function registerLogin(User $user): void
    {
        if (!$this->config->enableSessionHistory) {
            return;
        }

        $sessionHistory = new SessionHistory();
        $sessionHistory->setUserId($user->getId() !== null ? (int) $user->getId() : 0);
        $sessionHistory->setSessionId($this->session?->getId() ?? '');
        $sessionHistory->setIp($this->config->disableIpLogging ? '127.0.0.1' : ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'));
        $sessionHistory->setUserAgent($_SERVER['HTTP_USER_AGENT'] ?? null);
        $sessionHistory->setCreatedAt(time());
        $sessionHistory->setUpdatedAt(time());
        $sessionHistory->save();

        $this->pruneOldSessions($user);
    }

    private function pruneOldSessions(User $user): void
    {
        if ($this->config->hasNumberSessionHistory()) {
            $userId = $user->getId() !== null ? (int) $user->getId() : 0;
            $sessions = SessionHistory::query()
                ->where(['user_id' => $userId])
                ->orderBy(['created_at' => 'DESC'])
                ->all();

            if (count($sessions) > $this->config->numberSessionHistory) {
                $toDelete = array_slice($sessions, $this->config->numberSessionHistory);
                foreach ($toDelete as $session) {
                    $session->delete();
                }
            }
        }
    }
}
