<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\ViewData\Account;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\tests\Support\FakeUrlGenerator;
use YiiRocks\Voyti\tests\Support\UserSessionFactoryTrait;
use YiiRocks\Voyti\ViewData\Account\SessionRow;

final class SessionRowTest extends TestCase
{
    use UserSessionFactoryTrait;

    public function testCreateDoesNotFlagOtherSession(): void
    {
        $session = $this->buildUserSession('abc');

        $row = SessionRow::create($session, 'other', 'UTC', 'en', new FakeUrlGenerator());

        self::assertFalse($row->isCurrentSession);
    }

    public function testCreateFlagsCurrentSession(): void
    {
        $session = $this->buildUserSession('abc');

        $row = SessionRow::create($session, 'abc', 'UTC', 'en', new FakeUrlGenerator());

        self::assertTrue($row->isCurrentSession);
        self::assertSame('203.0.113.1', $row->session->ip);
        self::assertSame('//voyti/user-account-sessions-terminate?sessionId=abc', $row->formSubmitUrl);
    }
}
