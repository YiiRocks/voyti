<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Service\Password;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Service\Password\RandomPasswordGenerator;

final class RandomPasswordGeneratorTest extends TestCase
{
    public function testGenerateProducesDifferentResults(): void
    {
        $generator = new RandomPasswordGenerator();

        $p1 = $generator->generate(12);
        $p2 = $generator->generate(12);
        self::assertNotSame($p1, $p2);
    }
    public function testGenerateReturnsStringOfExpectedLength(): void
    {
        $generator = new RandomPasswordGenerator();

        $password = $generator->generate(12);
        self::assertIsString($password);
        self::assertSame(12, strlen($password));
    }

    public function testGenerateWithDifferentLength(): void
    {
        $generator = new RandomPasswordGenerator();

        $password = $generator->generate(24);
        self::assertIsString($password);
        self::assertSame(24, strlen($password));
    }
}
