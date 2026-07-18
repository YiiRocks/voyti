<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Validator\Rbac;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Validator\Rbac\ItemsValidator;
use Yiisoft\Rbac\ItemsStorageInterface;

#[AllowMockObjectsWithoutExpectations]
final class ItemsValidatorTest extends TestCase
{
    public function testValidateWithAllExistingItems(): void
    {
        $storage = $this->createMock(ItemsStorageInterface::class);
        $storage->method('exists')->willReturnMap([
            ['item1', true],
            ['item2', true],
        ]);

        $validator = new ItemsValidator($storage);
        $result = $validator->validate(['item1', 'item2']);

        $this->assertTrue($result->isValid());
        $this->assertCount(0, $result->getErrors());
    }

    public function testValidateWithAllMissingItems(): void
    {
        $storage = $this->createMock(ItemsStorageInterface::class);
        $storage->method('exists')->willReturn(false);

        $validator = new ItemsValidator($storage);
        $result = $validator->validate(['item1', 'item2', 'item3']);

        $this->assertFalse($result->isValid());
        $this->assertCount(3, $result->getErrors());
    }

    public function testValidateWithEmptyItems(): void
    {
        $storage = $this->createMock(ItemsStorageInterface::class);

        $validator = new ItemsValidator($storage);
        $result = $validator->validate([]);

        $this->assertTrue($result->isValid());
        $this->assertCount(0, $result->getErrors());
    }

    public function testValidateWithMissingItem(): void
    {
        $storage = $this->createMock(ItemsStorageInterface::class);
        $storage->method('exists')->willReturnMap([
            ['item1', true],
            ['item2', false],
        ]);

        $validator = new ItemsValidator($storage);
        $result = $validator->validate(['item1', 'item2']);

        $this->assertFalse($result->isValid());
        $this->assertCount(1, $result->getErrors());
        $this->assertStringContainsString('item2', $result->getErrors()[0]->getMessage());
    }
}
