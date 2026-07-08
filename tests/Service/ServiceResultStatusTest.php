<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Service;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Service\ServiceResultStatus;

final class ServiceResultStatusTest extends TestCase
{

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
    public function testFailureCase(): void
    {
        $status = ServiceResultStatus::FAILURE;
        self::assertSame('FAILURE', $status->name);
    }

    public function testSuccessCase(): void
    {
        $status = ServiceResultStatus::SUCCESS;
        self::assertSame('SUCCESS', $status->name);
    }
}
