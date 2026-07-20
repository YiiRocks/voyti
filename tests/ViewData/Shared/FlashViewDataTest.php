<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\ViewData\Shared;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Helper\FlashType;
use YiiRocks\Voyti\ViewData\Shared\FlashViewData;
use Yiisoft\Session\Flash\FlashInterface;

#[AllowMockObjectsWithoutExpectations]
final class FlashViewDataTest extends TestCase
{
    public function testFromFlashResolvesEmptyMessagesToNull(): void
    {
        $flash = $this->createMock(FlashInterface::class);
        $flash->method('get')->willReturn(null);

        $data = FlashViewData::fromFlash($flash);

        self::assertNull($data->warning);
        self::assertNull($data->success);
    }

    public function testFromFlashResolvesNonEmptyMessages(): void
    {
        $flash = $this->createMock(FlashInterface::class);
        $flash->method('get')->willReturnMap([
            [FlashType::WARNING, 'be careful'],
            [FlashType::SUCCESS, 'all good'],
        ]);

        $data = FlashViewData::fromFlash($flash);

        self::assertSame('be careful', $data->warning);
        self::assertSame('all good', $data->success);
    }
}
