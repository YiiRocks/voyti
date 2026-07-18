<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Validator;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Validator\PasswordComplexityRule;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\Validator\Rule\Regex;
use Yiisoft\Validator\Validator;

#[AllowMockObjectsWithoutExpectations]
final class PasswordComplexityRuleTest extends TestCase
{
    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function passwordProvider(): iterable
    {
        yield 'meets all requirements' => ['Str0ng!Pass', true];
        yield 'missing uppercase' => ['str0ng!pass', false];
        yield 'missing lowercase' => ['STR0NG!PASS', false];
        yield 'missing digit' => ['Strong!Pass', false];
        yield 'missing special character' => ['Str0ngPass', false];
    }

    #[DataProvider('passwordProvider')]
    public function testRegexValidatesComplexity(string $password, bool $expectedValid): void
    {
        $config = new ModuleConfig(enablePasswordComplexity: true);
        $rule = PasswordComplexityRule::rules($config, $this->createTranslator())[0];

        $validator = new Validator();
        $result = $validator->validate($password, [$rule]);

        $this->assertSame($expectedValid, $result->isValid());
    }

    public function testRulesReturnsEmptyArrayWhenDisabled(): void
    {
        $config = new ModuleConfig(enablePasswordComplexity: false);
        $rules = PasswordComplexityRule::rules($config, $this->createTranslator());
        $this->assertSame([], $rules);
    }

    public function testRulesReturnsRegexRuleWhenEnabled(): void
    {
        $config = new ModuleConfig(enablePasswordComplexity: true);
        $rules = PasswordComplexityRule::rules($config, $this->createTranslator());
        $this->assertCount(1, $rules);
        $this->assertInstanceOf(Regex::class, $rules[0]);
    }

    private function createTranslator(): TranslatorInterface
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('translate')->willReturnCallback(fn(string $id) => $id);

        return $translator;
    }
}
