<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Enum;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Enum\ServiceResultStatus;

final class ServiceResultStatusTest extends TestCase
{
    /**
     * @return iterable<string, array{ServiceResultStatus, string}>
     */
    public static function statusCaseProvider(): iterable
    {
        yield 'failure' => [ServiceResultStatus::FAILURE, 'FAILURE'];
        yield 'success' => [ServiceResultStatus::SUCCESS, 'SUCCESS'];
    }

    public function testCaseCount(): void
    {
        $cases = ServiceResultStatus::cases();
        self::assertCount(2, $cases);
    }

    public function testEnumValues(): void
    {
        self::assertTrue(ServiceResultStatus::FAILURE === ServiceResultStatus::FAILURE);
        self::assertTrue(ServiceResultStatus::SUCCESS === ServiceResultStatus::SUCCESS);
    }

    #[DataProvider('statusCaseProvider')]
    public function testStatusCase(ServiceResultStatus $status, string $expectedName): void
    {
        self::assertSame($expectedName, $status->name);
    }
}
