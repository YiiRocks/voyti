<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\ViewData\Account;

use YiiRocks\Voyti\Model\UserSessions;
use YiiRocks\Voyti\tests\Support\FakeUrlGenerator;
use YiiRocks\Voyti\tests\Support\UserSessionFactoryTrait;
use YiiRocks\Voyti\tests\Support\VoytiConfigFactory;
use YiiRocks\Voyti\tests\TestCase;
use YiiRocks\Voyti\ViewData\Account\SessionRow;
use YiiRocks\Voyti\ViewData\Account\SessionsViewData;
use YiiRocks\Voyti\ViewData\Account\UpdateViewData;
use Yiisoft\Translator\Translator;

final class AccountViewDataTest extends TestCase
{
    use UserSessionFactoryTrait;

    public function testSessionRowCreateDoesNotFlagOtherSession(): void
    {
        $session = $this->buildUserSession('abc');

        $row = SessionRow::create($session, 'other', 'UTC', 'en', new FakeUrlGenerator());

        self::assertFalse($row->isCurrentSession);
    }

    public function testSessionRowCreateFlagsCurrentSession(): void
    {
        $session = $this->buildUserSession('abc');

        $row = SessionRow::create($session, 'abc', 'UTC', 'en', new FakeUrlGenerator());

        self::assertTrue($row->isCurrentSession);
        self::assertSame('203.0.113.1', $row->session->ip);
        self::assertSame('//voyti/user-account-sessions-terminate?sessionId=abc', $row->formSubmitUrl);
    }

    public function testSessionsCreateExcludesRevokedSessions(): void
    {
        $revoked = new UserSessions();
        $revoked->setUserId(1);
        $revoked->setSessionId('revoked');
        $revoked->setCreatedAt(time());
        $revoked->setUpdatedAt(time());
        $revoked->setRevokedAt(time());

        $active = new UserSessions();
        $active->setUserId(1);
        $active->setSessionId('active');
        $active->setCreatedAt(time());
        $active->setUpdatedAt(time());

        $translator = new Translator('en', null, 'voyti');

        $data = SessionsViewData::create(
            [$revoked, $active],
            'active',
            'UTC',
            VoytiConfigFactory::create(),
            new FakeUrlGenerator(),
            $translator,
        );

        self::assertCount(1, $data->sessions);
        self::assertTrue($data->sessions[0]->isCurrentSession);
    }

    public function testUpdateCreateAssignsUpdateUrlAndMenu(): void
    {
        $translator = new Translator('en', null, 'voyti');

        $data = UpdateViewData::create(VoytiConfigFactory::create(), new FakeUrlGenerator(), $translator);

        self::assertSame('//voyti/user-account', $data->formSubmitUrl);
        self::assertNotEmpty($data->menu->items);
    }
}
