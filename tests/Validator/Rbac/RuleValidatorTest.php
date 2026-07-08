<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Validator\Rbac;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Validator\Rbac\RuleValidator;
use Yiisoft\Rbac\CompositeRule;

final class RuleValidatorTest extends TestCase
{

    public function testValidateWithBuiltInClassNotImplementingRuleInterface(): void
    {
        $validator = new RuleValidator();
        $result = $validator->validate(\stdClass::class);

        $this->assertFalse($result->isValid());
        $this->assertStringContainsString('must implement RuleInterface', $result->getErrors()[0]->getMessage());
    }

    public function testValidateWithClassNotImplementingRuleInterface(): void
    {
        $validator = new RuleValidator();
        $result = $validator->validate(self::class);

        $this->assertFalse($result->isValid());
        $this->assertCount(1, $result->getErrors());
        $this->assertStringContainsString('must implement RuleInterface', $result->getErrors()[0]->getMessage());
    }

    public function testValidateWithNonExistentClass(): void
    {
        $validator = new RuleValidator();
        $result = $validator->validate('NonExistent\\RuleClass');

        $this->assertFalse($result->isValid());
        $this->assertCount(1, $result->getErrors());
        $this->assertStringContainsString('does not exist', $result->getErrors()[0]->getMessage());
    }
    public function testValidateWithValidRuleClass(): void
    {
        $validator = new RuleValidator();
        $result = $validator->validate(CompositeRule::class);

        $this->assertTrue($result->isValid());
        $this->assertCount(0, $result->getErrors());
    }
}
