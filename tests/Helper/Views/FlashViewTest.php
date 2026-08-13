<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Helper\Views;

use YiiRocks\Voyti\Helper\FlashType;
use YiiRocks\Voyti\Helper\Views\FlashView;
use YiiRocks\Voyti\tests\TestCase;
use Yiisoft\Session\Flash\FlashInterface;

final class FlashViewTest extends TestCase
{
    public function testCreateNullsEmptyMessages(): void
    {
        $flash = $this->createStub(FlashInterface::class);
        $flash->method('get')->willReturn('');

        $result = FlashView::create($flash);

        self::assertNull($result['warning']);
        self::assertNull($result['success']);
    }

    public function testCreateSurfacesNonEmptyMessages(): void
    {
        $flash = $this->createStub(FlashInterface::class);
        $flash->method('get')->willReturnMap([
            [FlashType::WARNING, 'Warning message'],
            [FlashType::SUCCESS, 'Success message'],
        ]);

        $result = FlashView::create($flash);

        self::assertSame('Warning message', $result['warning']);
        self::assertSame('Success message', $result['success']);
    }
}
