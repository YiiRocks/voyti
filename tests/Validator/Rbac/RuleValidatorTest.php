<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Validator\Rbac;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\tests\Support\FakeAuthRule;
use YiiRocks\Voyti\Validator\Rbac\RuleValidator;

final class RuleValidatorTest extends TestCase
{

    public function testValidateReturnsErrorForClassNotImplementingRuleInterface(): void
    {
        $result = (new RuleValidator())->validate(self::class);

        self::assertFalse($result->isValid());
        self::assertSame(
            ["Class '" . self::class . "' must implement RuleInterface."],
            $result->getErrorMessages(),
        );
    }
    public function testValidateReturnsOnlyNotExistErrorForNonExistentClass(): void
    {
        $result = (new RuleValidator())->validate('NotARealClass');

        self::assertFalse($result->isValid());
        self::assertSame(
            ["Class 'NotARealClass' does not exist."],
            $result->getErrorMessages(),
        );
    }

    public function testValidateReturnsValidForRuleImplementingClass(): void
    {
        $result = (new RuleValidator())->validate(FakeAuthRule::class);

        self::assertTrue($result->isValid());
    }
}
