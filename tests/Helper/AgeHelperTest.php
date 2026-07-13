<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Helper;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Helper\AgeHelper;

final class AgeHelperTest extends TestCase
{

    /**
     * @return iterable<string, array{string, string, int|null}>
     */
    public static function calculateProvider(): iterable
    {
        yield 'age for birthday earlier this year' => ['1990-01-15', '2000-07-12', 10];
        yield 'age on exact birthday' => ['1990-07-12', '2000-07-12', 10];
        yield 'null for future birthday' => ['2030-01-01', '2000-07-12', null];
        yield 'one less for birthday not yet reached this year' => ['1990-12-25', '2000-07-12', 9];
        yield 'zero when birthday equals now' => ['2000-07-12', '2000-07-12', 0];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('calculateProvider')]
    public function testCalculate(string $birthday, string $now, ?int $expected): void
    {
        self::assertSame($expected, AgeHelper::calculate(new DateTimeImmutable($birthday), new DateTimeImmutable($now)));
    }

    public function testCalculateReturnsNullForNull(): void
    {
        self::assertNull(AgeHelper::calculate(null));
    }

    public function testCalculateUsesCurrentTimeWhenNowNotProvided(): void
    {
        $birthday = new DateTimeImmutable('-10 years');
        self::assertSame(10, AgeHelper::calculate($birthday));
    }
}
