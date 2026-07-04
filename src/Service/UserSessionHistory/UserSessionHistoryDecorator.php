<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Service\UserSessionHistory;

use Psr\EventDispatcher\EventDispatcherInterface;
use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\Entity\UserSessionHistory;
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

        $userSessionHistory = new UserSessionHistory();
        $userSessionHistory->setUserId($user->getId() !== null ? (int) $user->getId() : 0);
        $userSessionHistory->setSessionId($this->session?->getId() ?? '');
        $userSessionHistory->setIp($this->config->disableIpLogging ? '127.0.0.1' : ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'));
        $userSessionHistory->setUserAgent($_SERVER['HTTP_USER_AGENT'] ?? null);
        $userSessionHistory->setCreatedAt(time());
        $userSessionHistory->setUpdatedAt(time());
        $userSessionHistory->save();

        $this->pruneOldSessions($user);
    }

    private function pruneOldSessions(User $user): void
    {
        if ($this->config->hasNumberSessionHistory()) {
            /**
             * @infection-ignore-all
             *
             * $userId is only used to build a query array value; SQLite's INTEGER column
             * affinity coerces the numeric string from getId() identically to the (int)
             * cast, so no query result can distinguish the cast from its absence.
             */
            $userId = $user->getId() !== null ? (int) $user->getId() : 0;
            $sessions = UserSessionHistory::query()
                ->where(['user_id' => $userId])
                ->orderBy(['created_at' => 'DESC'])
                ->all();

            /** @var int $limit */
            $limit = $this->config->numberSessionHistory;

            /**
             * @infection-ignore-all
             *
             * array_slice($sessions, $limit) is always empty when count($sessions) equals
             * $limit exactly, so ">" vs ">=" only disagree at a boundary where both delete
             * nothing; no session count can produce a different set of deleted rows.
             */
            $hasExcessSessions = count($sessions) > $limit;
            if ($hasExcessSessions) {
                /** @var list<UserSessionHistory> $toDelete */
                $toDelete = array_slice($sessions, $limit);
                foreach ($toDelete as $session) {
                    $session->delete();
                }
            }
        }
    }
}
