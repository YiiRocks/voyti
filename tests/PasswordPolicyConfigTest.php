<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use YiiRocks\Voyti\PasswordPolicyConfig;

final class PasswordPolicyConfigTest extends TestCase
{
    public static function invalidConfigurationProvider(): iterable
    {
        yield 'minimum length below one' => [['minLength' => 0]];
        yield 'maximum below minimum' => [['minLength' => 8, 'maxLength' => 7]];
        yield 'negative uppercase' => [['minUppercase' => -1]];
        yield 'negative lowercase' => [['minLowercase' => -1]];
        yield 'negative digits' => [['minDigits' => -1]];
        yield 'negative symbols' => [['minSymbols' => -1]];
        yield 'minimum counts exceed maximum' => [['maxLength' => 6, 'minUppercase' => 7]];
        yield 'combined minimum counts exceed maximum' => [[
            'maxLength' => 6,
            'minUppercase' => 2,
            'minLowercase' => 2,
            'minDigits' => 2,
            'minSymbols' => 2,
        ]];
    }

    #[DataProvider('invalidConfigurationProvider')]
    public function testRejectsInvalidConfiguration(array $options): void
    {
        $this->expectException(InvalidArgumentException::class);

        new PasswordPolicyConfig(...$options);
    }

    public function testDefaultsPreserveExistingPolicy(): void
    {
        $config = new PasswordPolicyConfig();

        self::assertSame(6, $config->minLength);
        self::assertSame(72, $config->maxLength);
        self::assertSame(0, $config->minUppercase);
        self::assertSame(0, $config->minLowercase);
        self::assertSame(0, $config->minDigits);
        self::assertSame(0, $config->minSymbols);
    }

    public function testAcceptsBoundaryValues(): void
    {
        $config = new PasswordPolicyConfig(
            minLength: 1,
            maxLength: 4,
            minUppercase: 1,
            minLowercase: 1,
            minDigits: 1,
            minSymbols: 1,
        );

        self::assertSame(1, $config->minLength);
        self::assertSame(4, $config->maxLength);
    }

    public function testAcceptsEqualMinimumAndMaximumLength(): void
    {
        $config = new PasswordPolicyConfig(minLength: 8, maxLength: 8);

        self::assertSame(8, $config->maxLength);
    }
}
