<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Validator;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use YiiRocks\Voyti\tests\Support\VoytiConfigFactory;
use YiiRocks\Voyti\tests\TestCase;
use YiiRocks\Voyti\Validator\PasswordComplexityRule;
use Yiisoft\Validator\Validator;

#[AllowMockObjectsWithoutExpectations]
final class PasswordComplexityRuleTest extends TestCase
{
    public static function passwordProvider(): iterable
    {
        yield 'meets all requirements' => ['Str0ng!Pass', true];
        yield 'missing uppercase' => ['str0ng!pass', false];
        yield 'missing lowercase' => ['STR0NG!PASS', false];
        yield 'missing digit' => ['Strong!Pass', false];
        yield 'missing special character' => ['Str0ngPass', false];
    }

    public function testRegexSkipsEmptyPassword(): void
    {
        $config = VoytiConfigFactory::create(enablePasswordComplexity: true);
        $rule = PasswordComplexityRule::rules($config, $this->createTranslator())[0];

        $validator = new Validator();
        $result = $validator->validate('', [$rule]);

        $this->assertTrue($result->isValid());
    }

    public function testRulesReturnsEmptyArrayWhenDisabled(): void
    {
        $config = VoytiConfigFactory::create(enablePasswordComplexity: false);
        $rules = PasswordComplexityRule::rules($config, $this->createTranslator());
        $this->assertSame([], $rules);
    }
}
