<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\ModuleConfig;

final class ParamsTest extends TestCase
{
    public function testDefaultMailPathIsRelativeToPackageRoot(): void
    {
        $mailPath = $this->voytiDefaults()['mailPath'];

        self::assertStringContainsString('/config/../resources/mail', str_replace('\\', '/', $mailPath));
        self::assertNotSame('/resources/mail', $mailPath);
    }

    public function testDefaultsConstructAValidModuleConfig(): void
    {
        $config = new ModuleConfig(...$this->voytiDefaults());

        self::assertSame('Voyti', $config->appName);
        self::assertTrue($config->enableRegistration);
    }

    /**
     * @return array<string, mixed>
     */
    private function voytiDefaults(): array
    {
        $params = require dirname(__DIR__) . '/config/params.php';

        return $params['yiirocks/voyti'];
    }
}
