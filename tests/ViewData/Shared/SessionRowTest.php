<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\ViewData\Shared;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Model\UserSessions;
use YiiRocks\Voyti\ViewData\Shared\SessionRow;

final class SessionRowTest extends TestCase
{
    public function testCreateWhenActive(): void
    {
        $session = $this->createSession();

        $row = SessionRow::create($session, 'UTC', 'en');

        self::assertSame('203.0.113.1', $row->ip);
        self::assertSame('curl', $row->userAgent);
        self::assertNotEmpty($row->lastSeenDisplay);
        self::assertFalse($row->isRevoked);
        self::assertNull($row->revokedAtDisplay);
    }

    public function testCreateWhenRevoked(): void
    {
        $session = $this->createSession();
        $session->setRevokedAt(time());

        $row = SessionRow::create($session, 'UTC', 'en');

        self::assertTrue($row->isRevoked);
        self::assertNotNull($row->revokedAtDisplay);
    }

    private function createSession(): UserSessions
    {
        $session = new UserSessions();
        $session->setUserId(1);
        $session->setSessionId('abc');
        $session->setIp('203.0.113.1');
        $session->setUserAgent('curl');
        $session->setCreatedAt(time());
        $session->setUpdatedAt(time());

        return $session;
    }
}
