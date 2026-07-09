<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Helper;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Helper\ApiTokenHasher;

final class ApiTokenHasherTest extends TestCase
{
    public function testHashIsDeterministic(): void
    {
        self::assertSame(ApiTokenHasher::hash('my-token'), ApiTokenHasher::hash('my-token'));
    }

    public function testHashMatchesSha256(): void
    {
        self::assertSame(hash('sha256', 'my-token'), ApiTokenHasher::hash('my-token'));
    }

    public function testHashProducesDifferentValueForDifferentInput(): void
    {
        self::assertNotSame(ApiTokenHasher::hash('token-a'), ApiTokenHasher::hash('token-b'));
    }
}
