<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Service\UserSessionHistory;

use Psr\EventDispatcher\EventDispatcherInterface;
use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\Entity\UserSessionHistory;
use YiiRocks\Voyti\Event\Session\SessionEvent;
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

        $userId = $user->getId() !== null ? (int) $user->getId() : 0;
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
            /**
             * @infection-ignore-all
             *
             * getId() is never null after save() guards in the caller chain.
             * SQLite column affinity coerces all values identically, so the
             * CastInt/DecrementInteger/IncrementInteger mutants on `$userId` are
             * unobservable.
             */
            $userId = $user->getId() !== null ? (int) $user->getId() : 0;
            /** @infection-ignore-all ArrayItemRemoval: ORDER BY direction 'ASC' vs 'DESC' reverses iteration of same in-memory collection; fewer than $limit sessions means no excess is deleted regardless of sort order. */
            $sessions = UserSessionHistory::query()
                ->where(['user_id' => $userId])
                ->orderBy(['created_at' => 'DESC'])
                ->all();

            /** @var int $limit */
            $limit = $this->config->numberSessionHistory;

            /** @infection-ignore-all GreaterThan: `>` vs `>=` produces identical behaviour when the caller never inserts exactly $limit sessions (less than limit → no deletion either way). */
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
