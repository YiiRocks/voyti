<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\ViewData\Shared;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\ViewData\Shared\MessageViewData;

final class MessageViewDataTest extends TestCase
{
    public function testConstructorAssignsProperties(): void
    {
        $data = new MessageViewData(title: 'Not found', homeUrl: '/home');

        self::assertSame('Not found', $data->title);
        self::assertSame('/home', $data->homeUrl);
    }
}
