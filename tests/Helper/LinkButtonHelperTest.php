<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Helper;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Helper\LinkButtonHelper;
use Yiisoft\Form\Field\ResetButton;
use Yiisoft\Form\Field\SubmitButton;
use Yiisoft\Form\Theme\ThemeContainer;

final class LinkButtonHelperTest extends TestCase
{
    protected function setUp(): void
    {
        ThemeContainer::initialize();
    }

    protected function tearDown(): void
    {
        ThemeContainer::initialize();
    }

    public function testIgnoresArgumentsAfterTheFirst(): void
    {
        ThemeContainer::initialize(
            ['default' => ['fieldConfigs' => [SubmitButton::class => ['buttonClass()' => ['btn', 'btn-primary']]]]],
            'default',
        );

        self::assertSame('btn', LinkButtonHelper::submitButtonClass());
    }

    public function testIgnoresArgumentsAfterTheFirstForResetButton(): void
    {
        ThemeContainer::initialize(
            ['default' => ['fieldConfigs' => [ResetButton::class => ['buttonClass()' => ['btn', 'btn-secondary']]]]],
            'default',
        );

        self::assertSame('btn', LinkButtonHelper::resetButtonClass());
    }

    public function testReturnsEmptyStringWhenNoThemeIsConfigured(): void
    {
        self::assertSame('', LinkButtonHelper::submitButtonClass());
    }

    public function testReturnsEmptyStringWhenNoThemeIsConfiguredForResetButton(): void
    {
        self::assertSame('', LinkButtonHelper::resetButtonClass());
    }

    public function testReturnsResetButtonThemeClass(): void
    {
        ThemeContainer::initialize(
            ['default' => ['fieldConfigs' => [ResetButton::class => ['buttonClass()' => ['btn btn-outline-primary']]]]],
            'default',
        );

        self::assertSame('btn btn-outline-primary', LinkButtonHelper::resetButtonClass());
    }

    public function testReturnsThemeButtonClass(): void
    {
        ThemeContainer::initialize(
            ['default' => ['fieldConfigs' => [SubmitButton::class => ['buttonClass()' => ['btn btn-primary']]]]],
            'default',
        );

        self::assertSame('btn btn-primary', LinkButtonHelper::submitButtonClass());
    }
}
