<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\ViewData\Account;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Model\UserSessions;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\tests\Support\FakeUrlGenerator;
use YiiRocks\Voyti\ViewData\Account\SessionsViewData;
use Yiisoft\Translator\Translator;

final class SessionsViewDataTest extends TestCase
{
    public function testCreateMapsEachSessionToARow(): void
    {
        $session = new UserSessions();
        $session->setUserId(1);
        $session->setSessionId('abc');
        $session->setIp('203.0.113.1');
        $session->setUserAgent('curl');
        $session->setCreatedAt(time());
        $session->setUpdatedAt(time());

        $translator = new Translator('en', null, 'voyti');

        $data = SessionsViewData::create(
            [$session],
            'abc',
            'UTC',
            new ModuleConfig(),
            new FakeUrlGenerator(),
            $translator,
        );

        self::assertCount(1, $data->sessions);
        self::assertTrue($data->sessions[0]->isCurrentSession);
        self::assertNotEmpty($data->menu->items);
    }
}
