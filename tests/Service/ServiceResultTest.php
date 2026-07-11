<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Service;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Enum\ServiceResultStatus;
use YiiRocks\Voyti\Service\ServiceResult;

final class ServiceResultTest extends TestCase
{

    public function testConstructorWithCustomStatus(): void
    {
        $result = new ServiceResult(ServiceResultStatus::SUCCESS, 'test', ['err']);

        self::assertTrue($result->isSuccess());
        self::assertSame('test', $result->getMessage());
        self::assertSame(['err'], $result->getErrors());
    }

    public function testFailureStaticFactory(): void
    {
        $result = ServiceResult::failure('Operation failed', ['error1', 'error2']);

        self::assertTrue($result->isFailure());
        self::assertFalse($result->isSuccess());
        self::assertSame('Operation failed', $result->getMessage());
        self::assertSame(['error1', 'error2'], $result->getErrors());
    }

    public function testFailureStaticFactoryWithDefaults(): void
    {
        $result = ServiceResult::failure();

        self::assertTrue($result->isFailure());
        self::assertFalse($result->isSuccess());
        self::assertSame('', $result->getMessage());
        self::assertSame([], $result->getErrors());
    }
    public function testSuccessStaticFactory(): void
    {
        $result = ServiceResult::success('Operation completed');

        self::assertTrue($result->isSuccess());
        self::assertFalse($result->isFailure());
        self::assertSame('Operation completed', $result->getMessage());
        self::assertSame([], $result->getErrors());
    }

    public function testSuccessStaticFactoryWithEmptyMessage(): void
    {
        $result = ServiceResult::success();

        self::assertTrue($result->isSuccess());
        self::assertFalse($result->isFailure());
        self::assertSame('', $result->getMessage());
        self::assertSame([], $result->getErrors());
    }
}
