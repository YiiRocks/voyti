<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Helper;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Helper\RecaptchaHelper;
use YiiRocks\Voyti\ModuleConfig;

final class RecaptchaHelperTest extends TestCase
{
    public function testIsAvailableReturnsTrueWhenPackageInstalled(): void
    {
        $this->assertTrue(RecaptchaHelper::isAvailable());
    }

    public function testRenderReturnsEmptyStringWhenRecaptchaDisabled(): void
    {
        $config = new ModuleConfig();
        $result = RecaptchaHelper::render(
            $this->createStub(\Yiisoft\FormModel\FormModelInterface::class),
            $config,
        );

        $this->assertSame('', $result);
    }


}
