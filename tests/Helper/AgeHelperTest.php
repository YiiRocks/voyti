<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Helper;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Helper\AgeHelper;

final class AgeHelperTest extends TestCase
{

    public function testCalculateReturnsAgeForBirthdayEarlierThisYear(): void
    {
        $now = new DateTimeImmutable('2000-07-12');
        self::assertSame(10, AgeHelper::calculate(new DateTimeImmutable('1990-01-15'), $now));
    }

    public function testCalculateReturnsAgeOnExactBirthday(): void
    {
        $now = new DateTimeImmutable('2000-07-12');
        self::assertSame(10, AgeHelper::calculate(new DateTimeImmutable('1990-07-12'), $now));
    }

    public function testCalculateReturnsNullForFutureBirthday(): void
    {
        $now = new DateTimeImmutable('2000-07-12');
        self::assertNull(AgeHelper::calculate(new DateTimeImmutable('2030-01-01'), $now));
    }

    public function testCalculateReturnsNullForNull(): void
    {
        self::assertNull(AgeHelper::calculate(null));
    }

    public function testCalculateReturnsOneLessForBirthdayNotYetReachedThisYear(): void
    {
        $now = new DateTimeImmutable('2000-07-12');
        self::assertSame(9, AgeHelper::calculate(new DateTimeImmutable('1990-12-25'), $now));
    }

    public function testCalculateReturnsZeroWhenBirthdayEqualsNow(): void
    {
        $now = new DateTimeImmutable('2000-07-12');
        self::assertSame(0, AgeHelper::calculate($now, $now));
    }

    public function testCalculateUsesCurrentTimeWhenNowNotProvided(): void
    {
        $birthday = new DateTimeImmutable('-10 years');
        self::assertSame(10, AgeHelper::calculate($birthday));
    }
}
