<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Support;

use YiiRocks\Voyti\Model\UserSessions;

trait UserSessionFactoryTrait
{
    /**
     * Builds an in-memory `UserSessions`, without persisting it - for ViewData/unit tests that
     * receive a `UserSessions` as a plain argument and never look it up from the database.
     */
    private function buildUserSession(
        string $sessionId = 'abc',
        int $userId = 1,
        string $ip = '203.0.113.1',
        ?string $userAgent = 'curl',
    ): UserSessions {
        $session = new UserSessions();
        $session->setUserId($userId);
        $session->setSessionId($sessionId);
        $session->setIp($ip);
        $session->setUserAgent($userAgent);
        $session->setCreatedAt(time());
        $session->setUpdatedAt(time());

        return $session;
    }

    private function createUserSession(
        int $userId,
        string $sessionId,
        string $ip = '127.0.0.1',
        ?int $createdAt = null,
    ): UserSessions {
        $timestamp = $createdAt ?? time();

        $session = new UserSessions();
        $session->setUserId($userId);
        $session->setSessionId($sessionId);
        $session->setIp($ip);
        $session->setCreatedAt($timestamp);
        $session->setUpdatedAt($timestamp);
        $session->save();

        return $session;
    }
}
