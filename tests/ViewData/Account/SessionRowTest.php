<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\ViewData\Account;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Model\UserSessions;
use YiiRocks\Voyti\tests\Support\FakeUrlGenerator;
use YiiRocks\Voyti\ViewData\Account\SessionRow;

final class SessionRowTest extends TestCase
{
    public function testCreateDoesNotFlagOtherSession(): void
    {
        $session = $this->createSession('abc');

        $row = SessionRow::create($session, 'other', 'UTC', 'en', new FakeUrlGenerator());

        self::assertFalse($row->isCurrentSession);
    }

    public function testCreateFlagsCurrentSession(): void
    {
        $session = $this->createSession('abc');

        $row = SessionRow::create($session, 'abc', 'UTC', 'en', new FakeUrlGenerator());

        self::assertTrue($row->isCurrentSession);
        self::assertSame('203.0.113.1', $row->session->ip);
        self::assertSame('//voyti/user-account-sessions-terminate?sessionId=abc', $row->formSubmitUrl);
    }

    private function createSession(string $sessionId): UserSessions
    {
        $session = new UserSessions();
        $session->setUserId(1);
        $session->setSessionId($sessionId);
        $session->setIp('203.0.113.1');
        $session->setUserAgent('curl');
        $session->setCreatedAt(time());
        $session->setUpdatedAt(time());

        return $session;
    }
}
