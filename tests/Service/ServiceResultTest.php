<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Service;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Service\ServiceResult;

final class ServiceResultTest extends TestCase
{

    public function testFailureMessageAndErrorsCanBeEmpty(): void
    {
        $result = ServiceResult::failure();
        self::assertSame('', $result->getMessage());
        self::assertSame([], $result->getErrors());
    }

    public function testFailureWithMessageAndErrors(): void
    {
        $result = ServiceResult::failure('Something broke', ['code' => 500]);
        self::assertSame('Something broke', $result->getMessage());
        self::assertSame(['code' => 500], $result->getErrors());
    }

    public function testGetErrorsReturnsErrors(): void
    {
        $result = ServiceResult::failure('Error', ['field' => 'Invalid value']);
        self::assertSame(['field' => 'Invalid value'], $result->getErrors());
    }

    public function testGetMessageReturnsMessage(): void
    {
        $result = ServiceResult::success('Operation completed');
        self::assertSame('Operation completed', $result->getMessage());
    }

    public function testIsFailureReturnsFalseForSuccess(): void
    {
        $result = ServiceResult::success();
        self::assertFalse($result->isFailure());
    }

    public function testIsFailureReturnsTrueForFailure(): void
    {
        $result = ServiceResult::failure();
        self::assertTrue($result->isFailure());
    }

    public function testIsSuccessReturnsFalseForFailure(): void
    {
        $result = ServiceResult::failure();
        self::assertFalse($result->isSuccess());
    }

    public function testIsSuccessReturnsTrueForSuccess(): void
    {
        $result = ServiceResult::success();
        self::assertTrue($result->isSuccess());
    }
    public function testSuccessMessageCanBeEmpty(): void
    {
        $result = ServiceResult::success();
        self::assertSame('', $result->getMessage());
        self::assertSame([], $result->getErrors());
    }

    public function testSuccessWithMessage(): void
    {
        $result = ServiceResult::success('All good');
        self::assertSame('All good', $result->getMessage());
    }
}
