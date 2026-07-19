<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Helper;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Helper\AgeHelper;

final class AgeHelperTest extends TestCase
{
    /**
     * @return iterable<string, array{string, int|null}>
     */
    public static function calculateProvider(): iterable
    {
        yield 'birthday already passed this year' => ['-10 years -1 day', 10];
        yield 'birthday is exactly today' => ['-10 years', 10];
        yield 'birthday not yet reached this year' => ['-10 years +1 day', 9];
        yield 'future birthday' => ['+10 years', null];
    }

    #[DataProvider('calculateProvider')]
    public function testCalculate(string $birthdayModifier, ?int $expected): void
    {
        $birthday = (new DateTimeImmutable())->modify($birthdayModifier);
        self::assertSame($expected, AgeHelper::calculate($birthday));
    }

    public function testCalculateReturnsNullForNull(): void
    {
        self::assertNull(AgeHelper::calculate(null));
    }

    public function testCalculateReturnsZeroWhenBirthdayIsNow(): void
    {
        self::assertSame(0, AgeHelper::calculate(new DateTimeImmutable()));
    }
}
