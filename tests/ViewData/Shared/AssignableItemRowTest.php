<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\ViewData\Shared;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\ViewData\Shared\AssignableItemRow;

final class AssignableItemRowTest extends TestCase
{
    public function testFromItemsMarksAssignedNamesAsChecked(): void
    {
        $rows = AssignableItemRow::fromItems(['admin' => null, 'editor' => null], ['editor']);

        self::assertCount(2, $rows);
        self::assertSame('admin', $rows[0]->name);
        self::assertFalse($rows[0]->checked);
        self::assertSame('editor', $rows[1]->name);
        self::assertTrue($rows[1]->checked);
    }

    public function testFromItemsReturnsEmptyListForNoItems(): void
    {
        self::assertSame([], AssignableItemRow::fromItems([], []));
    }
}
