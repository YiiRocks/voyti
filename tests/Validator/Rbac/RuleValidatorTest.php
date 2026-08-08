<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Validator\Rbac;

use PHPUnit\Framework\TestCase;
use stdClass;
use YiiRocks\Voyti\Validator\Rbac\RuleValidator;

final class RuleValidatorTest extends TestCase
{
    public function testValidateWithBuiltInClassNotImplementingRuleInterface(): void
    {
        $validator = new RuleValidator();
        $result = $validator->validate(stdClass::class);

        $this->assertFalse($result->isValid());
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
}
