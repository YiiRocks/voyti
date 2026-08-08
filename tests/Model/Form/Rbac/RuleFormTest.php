<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Model\Form\Rbac;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Model\Form\Rbac\RuleForm;
use YiiRocks\Voyti\tests\Support\TranslatorMockTrait;
use Yiisoft\Validator\Rule\Regex;
use Yiisoft\Validator\Rule\Required;

#[AllowMockObjectsWithoutExpectations]
final class RuleFormTest extends TestCase
{
    use TranslatorMockTrait;

    public function testGetPropertyLabels(): void
    {
        $form = new RuleForm($this->createTranslator());
        $labels = $form->getPropertyLabels();
        $this->assertArrayHasKey('name', $labels);
        $this->assertArrayHasKey('class', $labels);
    }

    public function testGetRules(): void
    {
        $form = new RuleForm($this->createTranslator());
        $rules = $form->getRules();
        $this->assertArrayHasKey('name', $rules);
        $this->assertArrayHasKey('class', $rules);
        $this->assertInstanceOf(Required::class, $rules['name'][0]);
        $this->assertInstanceOf(Regex::class, $rules['name'][1]);
        $this->assertInstanceOf(Required::class, $rules['class'][0]);
    }

    public function testGetValidationPropertyLabels(): void
    {
        $form = new RuleForm($this->createTranslator());
        $this->assertSame($form->getPropertyLabels(), $form->getValidationPropertyLabels());
    }
}
