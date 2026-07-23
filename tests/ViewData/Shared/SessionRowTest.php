<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\ViewData\Shared;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\tests\Support\UserSessionFactoryTrait;
use YiiRocks\Voyti\ViewData\Shared\SessionRow;

final class SessionRowTest extends TestCase
{
    use UserSessionFactoryTrait;

    public function testCreateWhenActive(): void
    {
        $session = $this->buildUserSession();

        $row = SessionRow::create($session, 'UTC', 'en');

        self::assertSame('203.0.113.1', $row->ip);
        self::assertSame('curl', $row->userAgent);
        self::assertNotEmpty($row->lastSeenDisplay);
        self::assertFalse($row->isRevoked);
        self::assertNull($row->revokedAtDisplay);
    }

    public function testCreateWhenRevoked(): void
    {
        $session = $this->buildUserSession();
        $session->setRevokedAt(time());

        $row = SessionRow::create($session, 'UTC', 'en');

        self::assertTrue($row->isRevoked);
        self::assertNotNull($row->revokedAtDisplay);
    }
}
